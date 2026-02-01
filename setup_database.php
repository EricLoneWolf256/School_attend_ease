<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
$config = [
    'host' => 'localhost',
    'user' => 'root',
    'pass' => '',
    'name' => 'attendance_system'
];

// Create connection
$conn = new mysqli($config['host'], $config['user'], $config['pass']);

// Check connection
if ($conn->connect_error) {
    die("<p style='color:red'>Connection failed: " . $conn->connect_error . "</p>");
}

echo "<h1>Database Setup</h1>";

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS `{$config['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($sql) === TRUE) {
    echo "<p style='color:green'>✓ Database '{$config['name']}' created or already exists</p>";
} else {
    die("<p style='color:red'>Error creating database: " . $conn->error . "</p>");
}

// Select the database
$conn->select_db($config['name']);

// Read the SQL file
$sqlFile = __DIR__ . '/database.sql';
if (!file_exists($sqlFile)) {
    die("<p style='color:red'>Error: database.sql file not found at $sqlFile</p>");
}

$sql = file_get_contents($sqlFile);

// Execute multi query
if ($conn->multi_query($sql)) {
    do {
        // Store first result set
        if ($result = $conn->store_result()) {
            $result->free();
        }
        // Prepare next result set
    } while ($conn->next_result());
    
    if ($conn->error) {
        echo "<p style='color:red'>Error executing SQL: " . $conn->error . "</p>";
    } else {
        echo "<p style='color:green'>✓ Database tables created successfully</p>";
        
        // Test database connection with the new database
        $testConn = new mysqli($config['host'], $config['user'], $config['pass'], $config['name']);
        if ($testConn->connect_error) {
            echo "<p style='color:red'>Error connecting to the database: " . $testConn->connect_error . "</p>";
        } else {
            echo "<p style='color:green'>✓ Successfully connected to the database</p>";
            
            // Check if admin user exists
            $result = $testConn->query("SELECT * FROM users WHERE username = 'admin'");
            if ($result->num_rows > 0) {
                echo "<p style='color:green'>✓ Admin user exists</p>";
                echo "<p>You can now log in with:</p>";
                echo "<ul>";
                echo "<li>Username: admin</li>";
                echo "<li>Password: admin123</li>";
                echo "</ul>";
            } else {
                echo "<p style='color:red'>❌ Admin user not found. The database might not have been set up correctly.</p>";
            }
            
            $testConn->close();
        }
    }
} else {
    echo "<p style='color:red'>Error executing SQL: " . $conn->error . "</p>";
}

$conn->close();
?>

<h2>Next Steps:</h2>
<ol>
    <li>Try <a href="test_lecturer_add.php">testing the lecturer addition</a> again</li>
    <li>Log in to the <a href="admin/">admin panel</a> with the admin credentials above</li>
    <li>Delete this file (<code>setup_database.php</code>) for security reasons after setup is complete</li>
</ol>

<style>
    body { 
        font-family: Arial, sans-serif; 
        margin: 20px; 
        line-height: 1.6;
        max-width: 800px;
        margin: 0 auto;
        padding: 20px;
    }
    h1 { 
        color: #2c3e50;
        border-bottom: 2px solid #3498db;
        padding-bottom: 10px;
    }
    h2 {
        color: #34495e;
        margin-top: 30px;
    }
    p, li {
        font-size: 16px;
        line-height: 1.6;
    }
    code {
        background: #f0f0f0;
        padding: 2px 5px;
        border-radius: 3px;
        font-family: monospace;
    }
    .success {
        color: #27ae60;
    }
    .error {
        color: #e74c3c;
    }
    .next-steps {
        background: #f8f9fa;
        border-left: 4px solid #3498db;
        padding: 15px;
        margin-top: 20px;
    }
</style>
