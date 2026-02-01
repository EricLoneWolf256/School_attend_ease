<?php
// Test database connection
require_once 'includes/db_connection.php';

// Test query
try {
    $result = $conn->query("SELECT * FROM users LIMIT 1");
    if ($result) {
        echo "Database connection successful!<br>";
        echo "Found " . $result->num_rows . " user(s) in the database.";
    } else {
        echo "Query failed: " . $conn->error;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// Close connection
$conn->close();
?>
