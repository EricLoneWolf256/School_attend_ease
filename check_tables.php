<?php
// Include database connection
require_once 'includes/db_connection.php';

// Query to list all tables
$tables = $conn->query("SHOW TABLES");

echo "<h2>Database Tables</h2>";
if ($tables->num_rows > 0) {
    echo "<ul>";
    while ($table = $tables->fetch_array()) {
        $tableName = $table[0];
        echo "<li><strong>$tableName</strong>";
        
        // Get table structure
        $structure = $conn->query("DESCRIBE $tableName");
        if ($structure) {
            echo "<ul>";
            while ($column = $structure->fetch_assoc()) {
                $null = $column['Null'] === 'NO' ? 'NOT NULL' : 'NULL';
                $default = $column['Default'] !== null ? "DEFAULT '{$column['Default']}'" : '';
                echo "<li>{$column['Field']} ({$column['Type']}) $null $default";
                if ($column['Key'] === 'PRI') echo " <em>PRIMARY KEY</em>";
                if ($column['Extra'] === 'auto_increment') echo " <em>AUTO_INCREMENT</em>";
                echo "</li>";
            }
            echo "</ul>";
        } else {
            echo "<li>Error describing table: " . $conn->error . "</li>";
        }
        echo "</li>";
    }
    echo "</ul>";
} else {
    echo "No tables found in the database.";
}

// Close connection
$conn->close();
?>
