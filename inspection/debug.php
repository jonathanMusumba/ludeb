<?php
// Create this as detailed_debug.php in your admin directory
echo "<h2>Debug Information</h2>";
echo "Current file: " . __FILE__ . "<br>";
echo "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "<br>";
echo "HTTP_HOST: " . $_SERVER['HTTP_HOST'] . "<br>";
echo "SCRIPT_NAME: " . $_SERVER['SCRIPT_NAME'] . "<br>";

// Check if this is actually detailed_report.php being loaded
if (strpos($_SERVER['SCRIPT_NAME'], 'detailed_report.php') !== false) {
    echo "<strong style='color: green;'>✓ detailed_report.php is being loaded</strong><br>";
} elseif (strpos($_SERVER['SCRIPT_NAME'], 'district_results.php') !== false) {
    echo "<strong style='color: red;'>✗ district_results.php is being loaded instead</strong><br>";
} else {
    echo "<strong style='color: orange;'>? Unknown file is being loaded</strong><br>";
}

echo "<br><h3>File Contents Check</h3>";

// Check the first few lines of detailed_report.php
if (file_exists('detailed_report.php')) {
    $content = file_get_contents('detailed_report.php');
    $first_lines = explode("\n", substr($content, 0, 500));
    echo "<strong>First few lines of detailed_report.php:</strong><br>";
    echo "<pre>" . htmlspecialchars(implode("\n", array_slice($first_lines, 0, 10))) . "</pre>";
    
    // Check if it contains any redirect
    if (strpos($content, 'district_results.php') !== false) {
        echo "<strong style='color: red;'>⚠ detailed_report.php contains reference to district_results.php</strong><br>";
        
        // Find the line with district_results.php
        $lines = explode("\n", $content);
        foreach ($lines as $num => $line) {
            if (strpos($line, 'district_results.php') !== false) {
                echo "Line " . ($num + 1) . ": " . htmlspecialchars(trim($line)) . "<br>";
            }
        }
    }
}

echo "<br><h3>Session and GET Parameters</h3>";
session_start();
echo "Session role: " . ($_SESSION['role'] ?? 'Not set') . "<br>";
echo "Session user_id: " . ($_SESSION['user_id'] ?? 'Not set') . "<br>";
echo "GET parameters: " . print_r($_GET, true) . "<br>";

echo "<br><h3>HTTP Headers</h3>";
foreach (getallheaders() as $name => $value) {
    echo "$name: $value<br>";
}
?>