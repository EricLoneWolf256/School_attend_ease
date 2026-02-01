<?php
// Simple connection test
$conn = new mysqli('localhost', 'root', '');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "MySQL connection successful!";

// Check if database exists
$db_selected = mysqli_select_db($conn, 'attendance_system');
if ($db_selected) {
    echo "<br>Database connection successful!";
} else {
    echo "<br>Error connecting to database: " . mysqli_error($conn);
}

// Check if tables exist
$tables = $conn->query("SHOW TABLES");
if ($tables->num_rows > 0) {
    echo "<h3>Database Tables:</h3><ul>";
    while($table = $tables->fetch_array()) {
        echo "<li>" . $table[0] . "</li>";
    }
    echo "</ul>";
} else {
    echo "<br>No tables found in the database.";
}

$conn->close();
?>
