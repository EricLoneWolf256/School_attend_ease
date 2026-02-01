<?php
// Database configuration
$host = 'localhost';
$user = 'root';
$pass = '';

// Create connection
$conn = new mysqli($host, $user, $pass);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Successfully connected to MySQL server!\n\n";

// List all databases
$result = $conn->query("SHOW DATABASES");

if ($result->num_rows > 0) {
    echo "Available databases:\n";
    echo str_repeat("=", 50) . "\n";
    while($row = $result->fetch_array()) {
        echo "- " . $row[0] . "\n";
    }
} else {
    echo "No databases found\n";
}

// Check if attendance_system exists
$result = $conn->query("SHOW DATABASES LIKE 'attendance_system'");
if ($result->num_rows > 0) {
    echo "\n'attendance_system' database exists. Checking tables...\n";
    $conn->select_db('attendance_system');
    $tables = $conn->query("SHOW TABLES");
    
    if ($tables->num_rows > 0) {
        echo "\nTables in attendance_system database:\n";
        echo str_repeat("-", 50) . "\n";
        while($table = $tables->fetch_array()) {
            echo "- " . $table[0] . "\n";
            
            // If this is the students table, show its structure
            if ($table[0] === 'students') {
                $columns = $conn->query("DESCRIBE students");
                echo "  Columns in 'students' table:\n";
                while($col = $columns->fetch_assoc()) {
                    echo "    - " . $col['Field'] . " (" . $col['Type'] . ") " . 
                         ($col['Null'] === 'NO' ? 'NOT NULL ' : '') . 
                         ($col['Key'] ? "[{$col['Key'}] " : "") . 
                         ($col['Default'] !== null ? "DEFAULT '{$col['Default']}'" : "") . "\n";
                }
            }
        }
    } else {
        echo "No tables found in 'attendance_system' database\n";
    }
} else {
    echo "\n'attendance_system' database does not exist.\n";
}

$conn->close();
?>
