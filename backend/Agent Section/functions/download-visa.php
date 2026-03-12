<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (isset($_GET['file'])) {
    // Decode and sanitize the file path from the URL parameter
    $filePath = urldecode($_GET['file']);

    // Replace any backslashes with forward slashes for consistency with web URLs
    $filePath = str_replace('\\', '/', $filePath);

    // Define the upload directory (this should point to the correct location in the 'functions' folder)
    $uploadDir = $_SERVER['DOCUMENT_ROOT']; // Correct path to 'functions/uploads'

    // Combine the upload directory with the sanitized file path to create the full path
    $fullPath = $filePath;

    // Debugging output to verify paths
    error_log("Requested File Path (decoded and sanitized): " . $filePath);
    error_log("Full File Path: " . $fullPath);

    // Check if the file exists
    if (!file_exists($fullPath)) {
        error_log("Error: File not found at " . $fullPath);
        echo "Error: File not found at " . htmlspecialchars($fullPath) . "<br>";
        exit;
    }

    // Security check: Ensure the resolved file path is within the uploads directory
    if (strpos(realpath($fullPath), realpath($uploadDir)) === 0 && is_readable($fullPath)) {
        // Manually setting Content-Type based on file extension
        $fileExtension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        
        if ($fileExtension == 'jpg' || $fileExtension == 'jpeg') {
            header("Content-Type: image/jpeg");
        } elseif ($fileExtension == 'png') {
            header("Content-Type: image/png");
        } elseif ($fileExtension == 'gif') {
            header("Content-Type: image/gif");
        } elseif ($fileExtension == 'pdf') {
            header("Content-Type: application/pdf");
        } else {
            header("Content-Type: application/octet-stream"); // Default for other files
        }

        // Set the Content-Disposition to attachment for file download
        header("Content-Disposition: attachment; filename=\"" . basename($fullPath) . "\"");

        // Set the Content-Length for the file (optional, but it can improve performance)
        header("Content-Length: " . filesize($fullPath));

        // Output the file contents
        readfile($fullPath);
        exit;
    } else {
        error_log("Error: File not found or inaccessible.");
        echo "Error: File not found or inaccessible.<br>";
    }
} else {
    echo "Error: No file specified.";
}
?>
