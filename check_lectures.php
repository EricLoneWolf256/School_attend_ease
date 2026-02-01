<?php
require_once 'config.php';
require_once 'includes/session.php';

// Check if user is logged in
if (!isLoggedIn()) {
    die('Please log in to view this page.');
}

$db = getDBConnection();

try {
    $query = "SELECT l.*, c.course_code, c.course_name, 
              u.first_name, u.last_name, u.email as lecturer_email
              FROM lectures l
              JOIN courses c ON l.course_id = c.course_id
              LEFT JOIN users u ON l.lecturer_id = u.user_id
              ORDER BY l.scheduled_date DESC, l.start_time DESC";
    
    $stmt = $db->query($query);
    $lectures = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Recent Lectures</h2>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Course</th><th>Title</th><th>Lecturer</th><th>Date</th><th>Time</th><th>Status</th></tr>";
    
    foreach ($lectures as $lecture) {
        $status = $lecture['is_active'] ? 'Active' : 'Inactive';
        $lecturer = !empty($lecture['first_name']) 
            ? $lecture['first_name'] . ' ' . $lecture['last_name'] 
            : 'Not assigned';
            
        echo "<tr>";
        echo "<td>" . htmlspecialchars($lecture['lecture_id']) . "</td>";
        echo "<td>" . htmlspecialchars($lecture['course_code']) . "</td>";
        echo "<td>" . htmlspecialchars($lecture['title']) . "</td>";
        echo "<td>" . htmlspecialchars($lecturer) . "</td>";
        echo "<td>" . htmlspecialchars($lecture['scheduled_date']) . "</td>";
        echo "<td>" . date('g:i A', strtotime($lecture['start_time'])) . " - " . 
                      date('g:i A', strtotime($lecture['end_time'])) . "</td>";
        echo "<td>" . $status . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
