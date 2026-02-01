<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once '../includes/db_connection.php';

// Test database connection
echo "<h2>Testing Database Connection</h2>";

try {
    // Test connection
    if ($conn->ping()) {
        echo "<p style='color: green;'>✅ Database connection successful!</p>";
        
        // Test query
        $result = $conn->query("SELECT DATABASE() as db");
        if ($result) {
            $row = $result->fetch_assoc();
            echo "<p>Connected to database: <strong>" . htmlspecialchars($row['db']) . "</strong></p>";
        }
        
        // Check if tables exist
        $tables = $conn->query("SHOW TABLES");
        if ($tables->num_rows > 0) {
            echo "<h3>Tables in database:</h3><ul>";
            while ($table = $tables->fetch_array()) {
                echo "<li>" . htmlspecialchars($table[0]) . "</li>";
            }
            echo "</ul>";
        } else {
            echo "<p style='color: orange;'>⚠️ No tables found in the database.</p>";
        }
    } else {
        throw new Exception("Connection failed");
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    
    // Display connection details (for debugging)
    echo "<h3>Connection Details:</h3>";
    echo "<pre>";
    echo "Host: localhost\n";
    echo "Database: attendance_system\n";
    echo "User: root\n";
    echo "Password: (empty)\n";
    echo "</pre>";
    
    // Check if MySQL is running
    echo "<h3>MySQL Status:</h3>";
    $mysql_running = @fsockopen('localhost', 3306, $errno, $errstr, 5);
    if ($mysql_running) {
        echo "<p>✅ MySQL is running on port 3306</p>";
        fclose($mysql_running);
    } else {
        echo "<p style='color: red;'>❌ MySQL is not running or not accessible on port 3306</p>";
        echo "<p>Error: $errstr ($errno)</p>";
    }
}

// Test file permissions
function checkFilePermissions($path, $minPermissions = 0755) {
    if (!file_exists($path)) {
        return "<span style='color: red;'>❌ Does not exist</span>";
    }
    
    $permissions = fileperms($path);
    $permissions = substr(sprintf('%o', $permissions), -4);
    
    $isWritable = is_writable($path);
    $isReadable = is_readable($path);
    
    $status = "<span style='color: " . ($isWritable && $isReadable ? 'green' : 'red') . ";'>";
    $status .= $isWritable && $isReadable ? '✅' : '❌';
    $status .= " Permissions: $permissions";
    $status .= "</span>";
    
    return $status;
}

echo "<h3>File Permissions:</h3>";
echo "<ul>";
echo "<li>Database Config: " . checkFilePermissions(dirname(__FILE__) . '/../includes/db_connection.php') . "</li>";
echo "<li>Admin Directory: " . checkFilePermissions(dirname(__FILE__)) . "</li>";
echo "<li>Uploads Directory: " . checkFilePermissions(dirname(__FILE__) . '/../uploads') . "</li>";
echo "</ul>";

// PHP Info (uncomment if needed)
// echo "<h3>PHP Info:</h3>";
// phpinfo();
?>

<h3>Next Steps:</h3>
<ol>
    <li>Check if the MySQL service is running</li>
    <li>Verify the database 'attendance_system' exists</li>
    <li>Check the database user 'root' has proper permissions</li>
    <li>Ensure the database tables are properly imported</li>
    <li>Check the web server error logs for more details</li>
</ol>
