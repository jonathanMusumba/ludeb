<?php
if (isset($_POST['file'])) {
    $file = '../downloads/' . $_POST['file'];
    
    if (file_exists($file)) {
        header("Content-Description: File Transfer");
        header("Content-Disposition: attachment; filename=\"" . basename($file) . "\"");
        header("Content-Type: application/vnd.ms-word");
        readfile($file);
        exit();
    } else {
        echo "File does not exist.";
    }
} else {
    echo "No file specified.";
}
?>
