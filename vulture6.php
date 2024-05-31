<?php

$MAX_FILESIZE = 5_000_000_000; // 5 GB

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['fileUpload'])) {
    $targetDir = $_POST['directory'];

    // ディレクトリが存在しない場合は作成する
    if (!file_exists($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
        throw new RuntimeException(sprintf('Directory "%s" was not created', $targetDir));
    }

    // ファイルパスを正しく結合する
    $targetFile = rtrim($targetDir, '/') . '/' . basename($_FILES["fileUpload"]["name"]);
    $uploadOk = 1;

    // ファイルが既に存在するかチェック
    if (file_exists($targetFile)) {
        echo "Sorry, the file already exists.";
        $uploadOk = 0;
    }

    // ファイルサイズのチェック
    if ($_FILES["fileUpload"]["size"] > $MAX_FILESIZE) {
        echo "File is too large.";
        $uploadOk = 0;
    }

    // $uploadOkが0の場合は、ファイルをアップロードしない
    if ($uploadOk === 0) {
        echo "File was not uploaded.";
    } else if (move_uploaded_file($_FILES["fileUpload"]["tmp_name"], $targetFile)) {
        echo "File " . htmlspecialchars(basename($_FILES["fileUpload"]["name"])) . " has been uploaded.";
    } else {
        echo "File upload failed.";
    }
    exit;
}

function listDirectories($dir): void {
    $directories = array_diff(scandir($dir), array('..', '.'));
    echo "<option value=\"./\">./</option>";
    foreach ($directories as $directory) {
        if (is_dir($directory)) {
            echo "<option value=\"$directory/\">$directory</option>";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>Vulture 6</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            background-color: #eee;
        }
        h1 {
            font-size: 4em;
            color: #3F51B5;
            font-weight: 300;
            margin: 0 0 20px 0;
            text-align: center;
        }
        #dropArea {
            width: 60%;
            height: 60%;
            border: 2px dashed #3F51B5;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            color: #555;
            background-color: #fff;
            box-shadow: 0 6px 10px 0 rgba(0,0,0,0.1);
            transition: all 0.3s;
        }

        .material-icons {
            font-size: 48px;
            color: #3F51B5;
        }
        #uploadForm {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: 20px;
            width: 60%;
        }
        #directory {
            padding: 10px;
            font-size: 16px;
            border-radius: 5px;
            border: 1px solid #ccc;
            width: 100%;
            box-sizing: border-box;
            margin-bottom: 20px;
        }
        #fileInput {
            display: none;
        }
    </style>
</head>
<body>
<h1>Vulture 6</h1>
<div id="dropArea">
    <i class="material-icons">cloud_upload</i>
    <p>Drop or click a file here to select a file</p>
</div>
<input type="file" id="fileInput" style="display: none;">


<form id="uploadForm" action="vulture6.php" method="post" enctype="multipart/form-data">
    <label for="directory" id="uploadTo">Upload to </label>
    <select name="directory" id="directory">
        <?php listDirectories('.'); ?>
    </select>
    <br>
    <input type="submit" value="Upload" style="display: none;">
</form>

<script>
    const dropArea = document.getElementById('dropArea');
    const fileInput = document.getElementById('fileInput');
    const form = document.getElementById('uploadForm');
    let dir = ".";

    dropArea.addEventListener('click', function() {
        fileInput.click();
    });

    dropArea.addEventListener('dragover', function(event) {
        event.preventDefault();
        dropArea.classList.add('hover');
    });

    dropArea.addEventListener('dragleave', function() {
        dropArea.classList.remove('hover');
    });

    dropArea.addEventListener('drop', function(event) {
        event.preventDefault();
        dropArea.classList.remove('hover');
        const files = event.dataTransfer.files;
        handleFiles(files);
    });

    fileInput.addEventListener('change', function() {
        handleFiles(fileInput.files);
    });

    document.getElementById('directory').addEventListener('change', function() {
        dir = this.options[this.selectedIndex].text;
    });

    function handleFiles(files) {
        form.reset();
        for (let i = 0; i < files.length; i++) {
            let file = files[i];
            let formData = new FormData(form);
            formData.append('fileUpload', file, file.name);
            formData.append('directory', dir);
            uploadFiles(formData);
        }
    }


    function uploadFiles(formData) {
        fetch('vulture6.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.text())
            .then(data => {
                alert('Upload Success: ' + data);
            })
            .catch(error => {
                alert('Upload Error: ' + error.message);
            });
    }
</script>
</body>
</html>
