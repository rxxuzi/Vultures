<?php
session_start(); // セッションの開始

class Stats {
    private string $ipAddress;
    private array $ports;
    private array $status = [];

    public function __construct($ipAddress, $ports) {
        $this->ipAddress = $ipAddress;
        $this->ports = $ports;
        foreach ($this->ports as $port) {
            $this->status[$port] = $this->connect($this->ipAddress, $port);
        }
    }

    public function __toString() {
        $html = '';
        foreach ($this->status as $port => $isOpen) {
            $id =  $this->ipAddress . '-' . $port;
            $html .= "<tr id='$id'>";
            $html .= "<td>" . $this->ipAddress . "</td>";
            $html .= "<td>" . $port . "</td>";
            if ($isOpen) {
                $html .= "<td class='open'>open</td>";
            } else {
                $html .= "<td class='close'>closed or not responding</td>";
            }
            $html .= "</tr>";
        }
        return $html;
    }

    public function connect($ipAddress, $port): bool {
        $connection = @fsockopen($ipAddress, $port, $errno, $errstr, 2); // 2秒のタイムアウト
        if (is_resource($connection)) {
            fclose($connection);
            return true;
        }
        return false;
    }
}

function mktr($ipAddress, $ports) : void{
    echo new Stats($ipAddress, $ports);
}

class Network {
    private array $listening;
    private array $established;

    public function __construct() {
        $this->listening = $this->getActiveConnections('LISTENING');
        $this->established = $this->getActiveConnections('ESTABLISHED');
    }

    private function getActiveConnections($state) {
        $output = shell_exec("netstat -an | findstr \"$state\"");
        return $this->parseNetstatOutput($output);
    }

    private function parseNetstatOutput($output) {
        $lines = explode("\n", $output);
        $connections = [];
        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 4) {
                $connections[] = [
                    'protocol' => $parts[0],
                    'local_address' => $parts[1],
                    'foreign_address' => $parts[2],
                    'state' => $parts[3]
                ];
            }
        }
        return $connections;
    }

    public function __toString() {
        $html = "<table border='1'>";
        $html .= "<tr><th>Protocol</th><th>Local Address</th><th>Foreign Address</th><th>State</th></tr>";
        foreach (['listening' => $this->listening, 'established' => $this->established] as $state => $connections) {
            foreach ($connections as $connection) {
                $html .= "<tr>";
                $html .= "<td>" . htmlspecialchars($connection['protocol']) . "</td>";
                $html .= "<td>" . htmlspecialchars($connection['local_address']) . "</td>";
                $html .= "<td>" . htmlspecialchars($connection['foreign_address']) . "</td>";
                if ($state === 'listening') {
                    $html .= "<td class=\"listening\">" . htmlspecialchars($connection['state']) . "</td>";
                } elseif ($state === 'established') {
                    $html .= "<td class=\"established\">" . htmlspecialchars($connection['state']) . "</td>";
                }
                $html .= "</tr>";
            }
        }
        $html .= "</table>";
        return $html;
    }
}
?>

<html>
<head>
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #4CAF50;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        .listening {
            background-color: #aaddff;
        }
        .established{
            background-color: #aaffdd;
        }

        .open {background-color: #aaffaa;}
        .close {background-color: #ffaaaa;}
    </style>
    <title>Vult 5</title>
</head>
<body>
<h1>Vulture 5</h1>
<p>This is the Vulture 5 page.</p>

<h2>Active Connections</h2>
<table>
    <thead>
        <tr>
            <th>IP Address</th>
            <th>Ports</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
    <?php
    $host = explode(':', $_SERVER['HTTP_HOST']);
    mktr($host[0], [$host[1]]);
    ?>
    </tbody>
</table>

<h2>Network Connections</h2>
<?=new Network()?>
</body>
</html>