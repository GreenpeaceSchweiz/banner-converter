<html>
    <head>
        <style type="text/css">
            span.banner-preview svg {
                max-width: 100px;
                height: auto;
            }
        </style>
    </head>
    <body>
        <h1>Banner CSV to file converter</h1>
<?php
/**
* Settings
*/
$debugOutput = false;

// Higher number --> more info
$debugVerbosity = 2;


if (empty($_FILES['file']['name'])) {
    ?>
    
        <form method="post" enctype="multipart/form-data">
            <label>CSV-Datei hochladen
                <input name="file" type="file" size="50" accept="text/*"> 
            </label>  <br>
            <button>Konvertieren</button>
        </form>
    <?php
}
else {
    $upload_dir = "upload";
    $file = $_FILES['file']['name'];

    $path = pathinfo($file);
    $filename = $path['filename'] . "-" . microtime();
    $ext = $path['extension'];

    $temp_name = $_FILES['file']['tmp_name'];
    $path_filename_ext = $upload_dir . "/" .  $filename . "." . $ext;

    // Create uploads dir if it doesn't exist
    if (! file_exists($upload_dir)) {
        mkdir($upload_dir);
    }

    // Check if file already exists
    if (file_exists($path_filename_ext)) {
        echo "Sorry, file already exists.";
    }
    else {
        // Upload file
        $result = move_uploaded_file($temp_name, $path_filename_ext);

        if ($result) {
            echo "File upload successful. Converting images. <br>";
        }
        else {
            echo "There was an error, file not saved.";
        }
    }


    processCSV($path_filename_ext);
}


function processCSV($file) {
    debug($file, 2);

    $row = 1;
    $bannerField = 0;
    $idField = 0;

    // create a directory
    $dir_name = 'banners-' . time();
    mkdir ($dir_name);

    if (($handle = fopen($file, "r")) !== FALSE) {
        debug('opened file for reading.', 2);

        while (($data = fgetcsv($handle, 0, ",", '"')) !== FALSE) {
            debug('Reading row ' . $row);

            $num = count($data);

            if ($row == 1){
                // find the index that contains the banner
                for ($i = 0; $i < $num; $i++) {
                    debug('Field name in row 1: ' . $data[$i] , 2);

                    if ($data[$i] == 'banner') {
                        $bannerField = $i;

                        debug('Found banner field in field ' . $i, 2);
                    }
                    if ($data[$i] == 'Eintrags-ID' || $data[$i] == 'Entry Id') {
                        $idField = $i;

                        debug('Found id Field field in field ' . $i, 2);
                    }
                }
                echo 'bannerField: ' . $bannerField . '<br>';
                echo 'idField: ' . $idField . '<br>';
            }
            else {
                saveImage($data[$bannerField], $data[$idField], $dir_name);
            }

            $row++;
        }
        fclose($handle);
    }
    else {
        echo 'Could not read uploaded file.';
    }


    echo '<h1>Generating zip file</h1>';

    $zipFile = zip($dir_name);

    echo '<h1>Finished generating<h1>';
    echo '<p><a href="' . $zipFile . '">Download Banners</a></p>';
}

function saveImage($imageData, $id, $dirname) {
    debug($imageData, 3);
    debug('Using ID ' . $id . ' for banner name', 2);

    if (!empty($imageData) && $imageData != 'banner') {
        // if the id isn't usable, generate one
        if (! is_numeric($id) || $id <= 0) {
            $id = microtime();
        }

        $filename = 'banner-' . $id . '.svg';

        // The data is either just URL encoded or base64 encoded in addition to that
        // Try to base64_decode. Data that's only URL encoded contains invalid characters and return false
        $base64decoded = base64_decode($imageData, true);
        if ($base64decoded !== false) {
            $imageData = $base64decoded;
        }

        // Remove type
        $imageData = substr($imageData, 34);

        // URL Decode
        $imageData = urldecode($imageData);

        // Save the image
        file_put_contents($dirname . '/' . $filename, $imageData);  

        debug('Converted image ' . $filename . ' (ID: ' . $id . '): <span class="banner-preview">' . $imageData . '</span>');
    }
    else {
        debug('No image data for ID: ' . $id);

    }
}

function zip($dirname) {
    // Get real path for our folder
    $rootPath = realpath($dirname);
    $zipFilename = $dirname . '.zip';

    // Initialize archive object
    $zip = new ZipArchive();
    $zip->open($zipFilename, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    // Create recursive directory iterator
    /** @var SplFileInfo[] $files */
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($rootPath),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $name => $file)
    {
        // Skip directories (they would be added automatically)
        if (!$file->isDir())
        {
            // Get real and relative path for current file
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($rootPath) + 1);

            // Add current file to archive
            $zip->addFile($filePath, $relativePath);
        }
    }

    // Zip archive will be created only after closing object
    $zip->close();

    return $zipFilename;
}

function debug($msg, $verbosity = 1) {
    global $debugOutput;
    global $debugVerbosity;

    if ($debugOutput && $verbosity <= $debugVerbosity) {
        if (is_array($msg) || is_object($msg)) {
            print_r($msg);
        }
        else {
            echo $msg . '<br>';
        }
    }
}

?>
</body>
</html>