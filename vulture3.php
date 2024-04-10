<?php

$os = get_os_type();

function get_os_type(): string {
    if (stripos(PHP_OS, 'WIN') !== false) {
        return 'win';
    }
    return 'lin';
}

function get_server_cpu_usage() {
    if (get_os_type() === 'win') {
        $cpuUsage = shell_exec('wmic cpu get loadpercentage /Value');
        if ($cpuUsage !== null) {
            $cpuUsage = explode("=", $cpuUsage)[1] ?? '';
        } else {
            $cpuUsage = '';
        }
        return trim($cpuUsage);
    }

    $load = sys_getloadavg();
    return $load[0]; // 1分間の平均負荷
}

function get_server_memory_usage(): float|int {
    if (get_os_type() === 'win') {
        $memory = shell_exec('wmic OS get FreePhysicalMemory /Value');
        $totalMemory = shell_exec('wmic computersystem get TotalPhysicalMemory /Value');
        if ($memory !== null && $totalMemory !== null) {
            $freeMemory = explode("=", $memory)[1] ?? '';
            $totalMemory = explode("=", $totalMemory)[1] ?? '';
        } else {
            $freeMemory = $totalMemory = '';
        }
        $freeMemory = (float) $freeMemory;
        $totalMemory = (float) $totalMemory;
    } else {
        $meminfo = file_get_contents('/proc/meminfo');
        preg_match_all('/\w+:\s+(\d+)/', $meminfo, $matches);
        $meminfo = array_combine($matches[0], $matches[1]);
        $totalMemory = (float) ($meminfo['MemTotal'] ?? 0);
        $freeMemory = (float) ($meminfo['MemFree'] ?? 0) + ($meminfo['Buffers'] ?? 0) + ($meminfo['Cached'] ?? 0);
    }

    return round((1 - $freeMemory / $totalMemory) * 100, 2);
}

function get_server_disk_space(): float|int {
    if (get_os_type() === 'win') {
        $diskSpace = shell_exec('wmic LogicalDisk Where DriveType="3" Get Size, FreeSpace /Value');
        if ($diskSpace !== null) {
            $diskSpace = explode("\n", $diskSpace) ?? [];
        } else {
            $diskSpace = [];
        }
        $totalSpace = 0;
        $freeSpace = 0;
        foreach ($diskSpace as $line) {
            if (str_contains($line, "Size")) {
                $totalSpace += (float) (explode("=", $line)[1] ?? 0);
            }
            if (str_contains($line, "FreeSpace")) {
                $freeSpace += (float) (explode("=", $line)[1] ?? 0);
            }
        }
    } else {
        $diskSpace = shell_exec('df -P | grep -vE "^Filesystem|tmpfs|cdrom"');
        $lines = explode("\n", $diskSpace);
        $totalSpace = 0;
        $freeSpace = 0;
        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', $line);
            if (count($parts) > 1) {
                $totalSpace += (float) ($parts[1] ?? 0);
                $freeSpace += (float) ($parts[3] ?? 0);
            }
        }
    }

    if ($totalSpace !== 0) {
        return round((1 - $freeSpace / $totalSpace) * 100, 2);
    }

    return 0;
}

function get_gpu_usage(): string {
    // NVIDIA GPUの場合
    if (is_nvidia_gpu()) {
        $gpuUsage = shell_exec('nvidia-smi --query-gpu=utilization.gpu --format=csv,noheader,nounits');
    }
    // AMD GPUの場合
    else if (is_amd_gpu()) {
        // AMDのGPU利用率を取得するコマンドを実行
        $gpuUsage = shell_exec('rocm-smi --showuse');
    }
    else {
        return "N/A";
    }

    return trim($gpuUsage);
}

// NVIDIA GPUを使用しているかどうかを確認する関数
function is_nvidia_gpu(): bool
{
    $output = shell_exec('lspci | grep -i nvidia');
    return !empty($output);
}

// AMD GPUを使用しているかどうかを確認する関数
function is_amd_gpu(): bool
{
    $output = shell_exec('lspci | grep -i amd');
    return !empty($output);
}


function get_network_status(): array {
    // ネットワークデバイスの情報を取得
    $networkData = [];
    if (get_os_type() === 'win') {
        $command = 'powershell "Get-NetAdapterStatistics | Select-Object -Property Name,ReceivedBytes,SentBytes | ConvertTo-Json"';
        $output = shell_exec($command);
        try {
            $stats = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $stats = [];
        }

        foreach ($stats as $stat) {
            $networkData[$stat['Name']] = [
                'rx_bytes' => $stat['ReceivedBytes'],
                'tx_bytes' => $stat['SentBytes']
            ];
        }

    } else {
        $data = file_get_contents('/proc/net/dev');
        $lines = explode("\n", $data);
        foreach ($lines as $line) {
            // 空行またはヘッダー行を無視
            if (trim($line) === '' || strpos($line, 'Inter-|') === 0 || strpos($line, 'face |') === 0) {
                continue;
            }

            $parts = preg_split('/\s+/', trim($line));
            $interfaceName = trim($parts[0], ':');

            // ネットワークインターフェイスごとの上り下りデータを取得
            $networkData[$interfaceName] = [
                'rx_bytes' => $parts[1], // 受信バイト数
                'tx_bytes' => $parts[9]  // 送信バイト数
            ];
        }

    }
    return $networkData;
}


function get_status_class_and_text($usage): array {
    if ($usage === "N/A") {
        return ['unknown-usage', 'Unknown'];
    }
    $usage = (float) $usage;
    if ($usage < 50) {
        return ['low-usage', 'Normal'];
    }

    if ($usage < 75) {
        return ['medium-usage', 'Moderate'];
    }

    return ['high-usage', 'Critical'];
}

function print_status($usage): void {
    [$class, $text] = get_status_class_and_text($usage);
    echo "<td class=\"$class\">$text</td>";
}

function get_server_process_list(): string {
    $processList = shell_exec('wmic process get description, processid /format:csv');
    $processes = explode("\n", $processList);
    array_shift($processes);  // ヘッダー行を削除
    $result = '<table class="process-list">';
    $trs = array();
    $i = 0;
    foreach ($processes as $process) {
        if (trim($process) !== "") {
            [$node, $description, $processId] = explode(",", $process);
            $trs[$i] = "<tr><td>$description</td><td>$processId</td></tr>";
            $i++;
        }
    }

    sort($trs);

    $result .= '<thead style="width: 100%">';
    $result .= '<tr><th>Description</th><th>Process ID</th></tr></thead>';
    $result .= '<tbody style="height: 500px; overflow-y: auto; width: 100%">';
    for ($j = 1, $jMax = count($trs); $j < $jMax; $j++){
        $result .= $trs[$j];
    }
    $result .= '</tbody></table>';

    return $result;
}
?>

<?php
$pc_name = gethostname();
$ssid = shell_exec('netsh wlan show interfaces | findstr SSID');
$ssid = explode(':', $ssid);
//$ssid = trim(end($ssid));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vult 3</title>
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
        }
        th, td {
            border: 1px solid black;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #ACACAC;
        }
        .high-usage {
            background-color: #ffaaaa;
        }
        .medium-usage {
            background-color: #ffffaa;
        }
        .low-usage {
            background-color: #aaffaa;
        }
        .file-list {
            display: block; /* 初期状態でリストを表示 */
        }
    </style>

    <style>
        .process-list {
            border-collapse: collapse;
            width: 100%;
        }
        .process-list th, .process-list td {
            border: 1px solid black;
            padding: 8px;
            text-align: left;
            width: 50%;
        }
        .scrollable-tbody {
            height: 500px;
            overflow-y: auto;
            width: 100%;
        }
    </style>
</head>
<body>
<h1>Server Status</h1>
<p>
    <strong>PC Name:</strong> <?php echo $pc_name; ?>
    <br>
    <strong>OS Type:</strong> <?php echo get_os_type()?>
</p>

<button id="reload">Reload</button>
<script>
    // if click #reload button, reload the page
    document.getElementById('reload').addEventListener('click', function() {
        window.location.reload();
    }
</script>
<table>
    <thead>
    <tr>
        <th>Metric</th>
        <th>Value</th>
        <th>Status</th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td>CPU Usage</td>
        <td><?php $cpuUsage = get_server_cpu_usage(); echo $cpuUsage; ?>%</td>
        <?php print_status($cpuUsage); ?>
    </tr>
    <tr>
        <td>Memory Usage</td>
        <td><?php $memoryUsage = get_server_memory_usage(); echo $memoryUsage; ?>%</td>
        <?php print_status($memoryUsage); ?>
    </tr>
    <tr>
        <td>Disk Space Usage</td>
        <td><?php $diskUsage = get_server_disk_space(); echo $diskUsage; ?>%</td>
        <?php print_status($diskUsage); ?>
    </tr>
    <tr>
        <td>GPU Usage</td>
        <td><?php $gpuUsage = get_gpu_usage(); echo $gpuUsage; ?>%</td>
        <?php print_status($gpuUsage); ?>
    </tr>
    </tbody>
</table>

<h2>Network Status</h2>
<table>
    <thead>
    <tr>
        <th>Interface</th>
        <th>RX Bytes</th>
        <th>TX Bytes</th>
    </tr>
    </thead>
    <tbody>
    <?php
    $networkStatus = get_network_status();
    foreach ($networkStatus as $interface => $data) {
        echo "<tr>";
        echo "<td>{$interface}</td>";
        echo "<td>{$data['rx_bytes']}</td>";
        echo "<td>{$data['tx_bytes']}</td>";
        echo "</tr>";
    }
    ?>
    </tbody>
</table>


<h2>Process List</h2>
<div class="scrollable-tbody">
    <?php echo get_server_process_list(); ?>
</div>

</body>
</html>