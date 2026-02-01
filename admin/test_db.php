<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/db_connection.php';

echo "<h1>Database Connection Test</h1>";

// Check if connection was successful
if ($conn->connect_error) {
    die("<p style='color:red;'>Connection failed: " . $conn->connect_error . "</p>");
} else {
    echo "<p style='color:green;'>✅ Successfully connected to database: " . $conn->host_info . "</p>";
    
    // Test query
    $result = $conn->query("SHOW TABLES");
    if ($result) {
        echo "<h2>Tables in database:</h2>";
        echo "<ul>";
        while($row = $result->fetch_array()) {
            echo "<li>" . $row[0] . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color:red;'>Error executing query: " . $conn->error . "</p>";
    }
}

// Close connection
$conn->close();
?>
