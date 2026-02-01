<?php
// Include configuration file
require_once __DIR__ . '/../config.php';

// Database configuration
$db_host = 'localhost';
$db_name = 'attendance_system';
$db_user = 'root';
$db_pass = '';

// Create connection
try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Set charset to utf8mb4
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    // Log the error (in a production environment, you'd log to a file)
    error_log("Database connection error: " . $e->getMessage());
    
    // Display a user-friendly error message
    die("<div style='font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: 50px auto; border: 1px solid #f5c6cb; border-radius: 5px; background-color: #f8d7da; color: #721c24;'>
            <h2 style='color: #721c24;'>Database Connection Error</h2>
            <p>We're sorry, but we're experiencing technical difficulties. Our team has been notified and we're working to resolve the issue.</p>
            <p>Please try again later or contact support if the problem persists.</p>
            <p><small>Error details: " . htmlspecialchars($e->getMessage()) . "</small></p>
        </div>");
}

// Function to execute a query with parameters
function executeQuery($query, $params = [], $types = '') {
    global $conn;
    
    $stmt = $conn->prepare($query);
    
    if ($stmt === false) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Query execution failed: " . $stmt->error);
    }
    
    return $stmt;
}

// Function to fetch a single row
function fetchOne($query, $params = [], $types = '') {
    $stmt = executeQuery($query, $params, $types);
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Function to fetch all rows
function fetchAll($query, $params = [], $types = '') {
    $stmt = executeQuery($query, $params, $types);
    $result = $stmt->get_result();
    $rows = [];
    
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    
    return $rows;
}

// Function to get the last inserted ID
function lastInsertId() {
    global $conn;
    return $conn->insert_id;
}
?>
