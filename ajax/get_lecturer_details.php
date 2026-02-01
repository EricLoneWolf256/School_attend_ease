<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';

header('Content-Type: application/json');

// Ensure user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

if (empty($_GET['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Lecturer ID is required']);
    exit();
}

$lecturerId = (int)$_GET['id'];

// Get lecturer details
$stmt = $conn->prepare("SELECT user_id, username, email, first_name, last_name FROM users WHERE user_id = ? AND role = 'lecturer'");
$stmt->bind_param("i", $lecturerId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Lecturer not found']);
    exit();
}

$lecturer = $result->fetch_assoc();
echo json_encode(['status' => 'success', 'data' => $lecturer]);
?>
