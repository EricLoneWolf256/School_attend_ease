<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';

if (!is_logged_in()) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access Denied');
}

$type = $_GET['type'] ?? '';
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;

// Set content type to HTML for all reports
header('Content-Type: text/html; charset=utf-8');

// Function to format date
function formatDate($date) {
    return date('F j, Y', strtotime($date));
}

// Start HTML output
echo '<!DOCTYPE html>
<html>
<head>
    <title>Attendance Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            background-color: #f8f9fa;
        }
        .report-container {
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        h1, h2, h3, h4, h5 { 
            color: #2c3e50;
            margin-top: 20px;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 20px 0;
            font-size: 0.9em;
        }
        th, td { 
            border: 1px solid #dee2e6; 
            padding: 10px; 
            text-align: left; 
        }
        th { 
            background-color: #f8f9fa;
            font-weight: 600;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .mb-4 { margin-bottom: 1.5rem; }
        .print-button { 
            margin: 20px 0;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
        }
        .btn-print {
            background-color: #0d6efd;
            color: white;
            border: none;
            margin-right: 10px;
        }
        .btn-close {
            background-color: #6c757d;
            color: white;
            border: none;
        }
        @media print {
            .no-print { 
                display: none; 
            }
            body { 
                margin: 0; 
                padding: 20px;
                font-size: 12px;
            }
            .report-container {
                box-shadow: none;
                padding: 0;
            }
            table {
                font-size: 10px;
            }
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="report-container">
        <div class="no-print text-end mb-3">
            <button onclick="window.print()" class="print-button btn-print">
                <i class="fas fa-print"></i> Print Report
            </button>
            <button onclick="window.close()" class="print-button btn-close">
                <i class="fas fa-times"></i> Close
            </button>
        </div>';

// Process different report types
switch ($type) {
    case 'date_range':
        if (!$start_date || !$end_date) {
            die('<div class="alert alert-danger">Please provide both start and end dates</div>');
        }
        
        $stmt = $conn->prepare("
            SELECT 
                u.user_id,
                u.first_name,
                u.last_name,
                c.course_code,
                c.course_name,
                a.attendance_time,
                a.status
            FROM attendance a
            JOIN users u ON a.user_id = u.user_id
            LEFT JOIN courses c ON a.course_id = c.course_id
            WHERE DATE(a.attendance_time) BETWEEN ? AND ?
            ORDER BY a.attendance_time DESC
        ");
        $stmt->bind_param('ss', $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        echo "<h1>Attendance Report</h1>";
        echo "<h4>Date Range: " . formatDate($start_date) . " to " . formatDate($end_date) . "</h4>";
        
        if ($result->num_rows > 0) {
            echo '<div class="table-responsive">';
            echo '<table class="table table-bordered table-striped">';
            echo '<thead class="table-dark"><tr>
                    <th>Date & Time</th>
                    <th>Student</th>
                    <th>Course</th>
                    <th>Status</th>
                  </tr></thead><tbody>';
            
            while ($row = $result->fetch_assoc()) {
                echo '<tr>';
                echo '<td>' . date('M j, Y h:i A', strtotime($row['attendance_time'])) . '</td>';
                echo '<td>' . htmlspecialchars($row['last_name'] . ', ' . $row['first_name']) . '</td>';
                echo '<td>' . (!empty($row['course_code']) ? htmlspecialchars($row['course_code'] . ' - ' . $row['course_name']) : 'N/A') . '</td>';
                echo '<td><span class="badge ' . 
                     ($row['status'] == 'present' ? 'bg-success' : 
                      ($row['status'] == 'absent' ? 'bg-danger' : 'bg-warning')) . 
                     '">' . ucfirst(htmlspecialchars($row['status'])) . '</span></td>';
                echo '</tr>';
            }
            
            echo '</tbody></table></div>';
        } else {
            echo '<div class="alert alert-info">No attendance records found for the selected date range.</div>';
        }
        break;
        
    case 'student':
        $student_id = (int)($_GET['student_id'] ?? 0);
        if (!$student_id) {
            die('<div class="alert alert-danger">Invalid student ID</div>');
        }
        
        // Get student info
        $student = $conn->query("
            SELECT u.first_name, u.last_name, s.student_number, u.email, u.phone
            FROM users u
            LEFT JOIN students s ON u.user_id = s.user_id
            WHERE u.user_id = $student_id
        ")->fetch_assoc();
        
        if (!$student) {
            die('<div class="alert alert-danger">Student not found</div>');
        }
        
        $query = "
            SELECT 
                a.attendance_time,
                a.status,
                c.course_code,
                c.course_name
            FROM attendance a
            LEFT JOIN courses c ON a.course_id = c.course_id
            WHERE a.user_id = ?
        ";
        
        $params = [$student_id];
        $types = "i";
        
        if ($start_date && $end_date) {
            $query .= " AND DATE(a.attendance_time) BETWEEN ? AND ?";
            $params[] = $start_date;
            $params[] = $end_date;
            $types .= "ss";
        }
        
        $query .= " ORDER BY a.attendance_time DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Student Information
        echo '<div class="student-info mb-4">';
        echo '<h1>Student Attendance Report</h1>';
        echo '<div class="card">';
        echo '<div class="card-body">';
        echo '<h4 class="card-title">' . htmlspecialchars($student['last_name'] . ', ' . $student['first_name']) . '</h4>';
        
        $info = [];
        if (!empty($student['student_number'])) $info[] = '<strong>ID:</strong> ' . htmlspecialchars($student['student_number']);
        if (!empty($student['email'])) $info[] = '<strong>Email:</strong> ' . htmlspecialchars($student['email']);
        if (!empty($student['phone'])) $info[] = '<strong>Phone:</strong> ' . htmlspecialchars($student['phone']);
        if ($start_date && $end_date) {
            $info[] = '<strong>Period:</strong> ' . formatDate($start_date) . ' to ' . formatDate($end_date);
        }
        
        echo '<p class="card-text">' . implode(' | ', $info) . '</p>';
        echo '</div></div></div>';
        
        if ($result->num_rows > 0) {
            // Summary statistics
            $summary_query = "
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                    SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late
                FROM attendance 
                WHERE user_id = $student_id
            ";
            
            if ($start_date && $end_date) {
                $summary_query .= " AND DATE(attendance_time) BETWEEN '$start_date' AND '$end_date'";
            }
            
            $summary = $conn->query($summary_query)->fetch_assoc();
            $attendance_rate = $summary['total'] > 0 ? round(($summary['present'] / $summary['total']) * 100, 2) : 0;
            
            // Summary Cards
            echo '<div class="row mb-4">';
            
            $cards = [
                ['Total', $summary['total'], 'primary'],
                ['Present', $summary['present'], 'success'],
                ['Absent', $summary['absent'], 'danger'],
                ['Late', $summary['late'], 'warning'],
                ['Attendance Rate', $attendance_rate . '%', 'info']
            ];
            
            foreach ($cards as $card) {
                echo '<div class="col-md-4 mb-3">';
                echo '<div class="card h-100 border-left-' . $card[2] . '">';
                echo '<div class="card-body">';
                echo '<div class="row no-gutters align-items-center">';
                echo '<div class="col mr-2">';
                echo '<div class="text-xs font-weight-bold text-' . $card[2] . ' text-uppercase mb-1">' . $card[0] . '</div>';
                echo '<div class="h5 mb-0 font-weight-bold text-gray-800">' . $card[1] . '</div>';
                echo '</div>';
                echo '<div class="col-auto">';
                $icon = 'fa-calendar-check';
                if ($card[0] === 'Present') $icon = 'fa-check-circle';
                elseif ($card[0] === 'Absent') $icon = 'fa-times-circle';
                elseif ($card[0] === 'Late') $icon = 'fa-clock';
                elseif ($card[0] === 'Attendance Rate') $icon = 'fa-percentage';
                echo '<i class="fas ' . $icon . ' fa-2x text-gray-300"></i>';
                echo '</div></div></div></div></div>';
            }
            
            echo '</div>'; // End row
            
            // Detailed records
            echo '<h4>Attendance Records</h4>';
            echo '<div class="table-responsive">';
            echo '<table class="table table-bordered table-striped">';
            echo '<thead class="table-dark"><tr><th>Date & Time</th><th>Course</th><th>Status</th></tr></thead><tbody>';
            
            while ($row = $result->fetch_assoc()) {
                echo '<tr>';
                echo '<td>' . date('M j, Y h:i A', strtotime($row['attendance_time'])) . '</td>';
                echo '<td>' . (!empty($row['course_code']) ? htmlspecialchars($row['course_code'] . ' - ' . $row['course_name']) : 'N/A') . '</td>';
                echo '<td><span class="badge ' . 
                     ($row['status'] == 'present' ? 'bg-success' : 
                      ($row['status'] == 'absent' ? 'bg-danger' : 'bg-warning')) . 
                     '">' . ucfirst(htmlspecialchars($row['status'])) . '</span></td>';
                echo '</tr>';
            }
            
            echo '</tbody></table></div>';
        } else {
            echo '<div class="alert alert-info">No attendance records found for this student.</div>';
        }
        break;
        
    case 'course':
        $course_id = (int)($_GET['course_id'] ?? 0);
        if (!$course_id) {
            die('<div class="alert alert-danger">Invalid course ID</div>');
        }
        
        // Get course info
        $course = $conn->query("
            SELECT c.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as lecturer_name
            FROM courses c
            LEFT JOIN course_assignments ca ON c.course_id = ca.course_id
            LEFT JOIN users u ON ca.lecturer_id = u.user_id
            WHERE c.course_id = $course_id
        ")->fetch_assoc();
        
        if (!$course) {
            die('<div class="alert alert-danger">Course not found</div>');
        }
        
        $query = "
            SELECT 
                a.attendance_time,
                a.status,
                u.user_id,
                u.first_name,
                u.last_name,
                s.student_number
            FROM attendance a
            JOIN users u ON a.user_id = u.user_id
            LEFT JOIN students s ON u.user_id = s.user_id
            WHERE a.course_id = ?
        ";
        
        $params = [$course_id];
        $types = "i";
        
        if ($start_date && $end_date) {
            $query .= " AND DATE(a.attendance_time) BETWEEN ? AND ?";
            $params[] = $start_date;
            $params[] = $end_date;
            $types .= "ss";
        }
        
        $query .= " ORDER BY a.attendance_time DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Course Information
        echo '<div class="course-info mb-4">';
        echo '<h1>Course Attendance Report</h1>';
        echo '<div class="card">';
        echo '<div class="card-body">';
        echo '<h4 class="card-title">' . htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']) . '</h4>';
        
        $info = [];
        if (!empty($course['lecturer_name'])) $info[] = '<strong>Lecturer:</strong> ' . htmlspecialchars($course['lecturer_name']);
        if (!empty($course['description'])) $info[] = htmlspecialchars($course['description']);
        if ($start_date && $end_date) {
            $info[] = '<strong>Period:</strong> ' . formatDate($start_date) . ' to ' . formatDate($end_date);
        }
        
        echo '<p class="card-text">' . implode('<br>', $info) . '</p>';
        echo '</div></div></div>';
        
        if ($result->num_rows > 0) {
            // Group records by student
            $records = [];
            while ($row = $result->fetch_assoc()) {
                $student_id = $row['user_id'];
                if (!isset($records[$student_id])) {
                    $records[$student_id] = [
                        'name' => $row['last_name'] . ', ' . $row['first_name'],
                        'student_number' => $row['student_number'],
                        'records' => []
                    ];
                }
                $records[$student_id]['records'][] = $row;
            }
            
            // Summary statistics
            $summary_query = "
                SELECT 
                    COUNT(DISTINCT user_id) as total_students,
                    COUNT(*) as total_records,
                    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                    SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late
                FROM attendance 
                WHERE course_id = $course_id
            ";
            
            if ($start_date && $end_date) {
                $summary_query .= " AND DATE(attendance_time) BETWEEN '$start_date' AND '$end_date'";
            }
            
            $summary = $conn->query($summary_query)->fetch_assoc();
            $attendance_rate = $summary['total_records'] > 0 ? 
                round(($summary['present'] / $summary['total_records']) * 100, 2) : 0;
            
            // Summary Cards
            echo '<div class="row mb-4">';
            
            $cards = [
                ['Total Students', $summary['total_students'], 'primary'],
                ['Total Records', $summary['total_records'], 'info'],
                ['Present', $summary['present'], 'success'],
                ['Absent', $summary['absent'], 'danger'],
                ['Late', $summary['late'], 'warning'],
                ['Attendance Rate', $attendance_rate . '%', 'secondary']
            ];
            
            foreach ($cards as $card) {
                echo '<div class="col-md-4 mb-3">';
                echo '<div class="card h-100 border-left-' . $card[2] . '">';
                echo '<div class="card-body">';
                echo '<div class="row no-gutters align-items-center">';
                echo '<div class="col mr-2">';
                echo '<div class="text-xs font-weight-bold text-' . $card[2] . ' text-uppercase mb-1">' . $card[0] . '</div>';
                echo '<div class="h5 mb-0 font-weight-bold text-gray-800">' . $card[1] . '</div>';
                echo '</div>';
                echo '<div class="col-auto">';
                $icon = 'fa-chart-bar';
                if ($card[0] === 'Total Students') $icon = 'fa-users';
                elseif ($card[0] === 'Present') $icon = 'fa-check-circle';
                elseif ($card[0] === 'Absent') $icon = 'fa-times-circle';
                elseif ($card[0] === 'Late') $icon = 'fa-clock';
                elseif ($card[0] === 'Attendance Rate') $icon = 'fa-percentage';
                echo '<i class="fas ' . $icon . ' fa-2x text-gray-300"></i>';
                echo '</div></div></div></div></div>';
            }
            
            echo '</div>'; // End row
            
            // Student-wise summary
            echo '<h4>Student Attendance Summary</h4>';
            echo '<div class="table-responsive mb-4">';
            echo '<table class="table table-bordered table-striped">';
            echo '<thead class="table-dark"><tr>
                    <th>Student</th>
                    <th>ID</th>
                    <th>Total</th>
                    <th>Present</th>
                    <th>Absent</th>
                    <th>Late</th>
                    <th>Rate</th>
                  </tr></thead><tbody>';
            
            foreach ($records as $student_id => $student) {
                $summary = [
                    'total' => count($student['records']),
                    'present' => 0,
                    'absent' => 0,
                    'late' => 0
                ];
                
                foreach ($student['records'] as $record) {
                    if ($record['status'] === 'present') $summary['present']++;
                    elseif ($record['status'] === 'absent') $summary['absent']++;
                    elseif ($record['status'] === 'late') $summary['late']++;
                }
                
                $rate = $summary['total'] > 0 ? round(($summary['present'] / $summary['total']) * 100, 1) : 0;
                
                echo '<tr>';
                echo '<td>' . htmlspecialchars($student['name']) . '</td>';
                echo '<td>' . (!empty($student['student_number']) ? htmlspecialchars($student['student_number']) : 'N/A') . '</td>';
                echo '<td>' . $summary['total'] . '</td>';
                echo '<td>' . $summary['present'] . '</td>';
                echo '<td>' . $summary['absent'] . '</td>';
                echo '<td>' . $summary['late'] . '</td>';
                echo '<td>' . $rate . '%</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table></div>';
        } else {
            echo '<div class="alert alert-info">No attendance records found for this course.</div>';
        }
        break;
        
    case 'lecturer':
        $lecturer_id = (int)($_GET['lecturer_id'] ?? 0);
        if (!$lecturer_id) {
            die('<div class="alert alert-danger">Invalid lecturer ID</div>');
        }
        
        // Get lecturer info with subquery to handle missing departments table
        $lecturer = $conn->query("
            SELECT u.*, 
                   (SELECT d.department_name 
                    FROM departments d 
                    WHERE d.department_id = u.department_id 
                    LIMIT 1) as department_name
            FROM users u
            WHERE u.user_id = $lecturer_id AND u.role = 'lecturer'
        ")->fetch_assoc();
        
        if (!$lecturer) {
            die('<div class="alert alert-danger">Lecturer not found</div>');
        }
        
        // Get courses taught by this lecturer
        $courses = $conn->query("
            SELECT c.course_id, c.course_code, c.course_name
            FROM course_assignments ca
            JOIN courses c ON ca.course_id = c.course_id
            WHERE ca.lecturer_id = $lecturer_id
            ORDER BY c.course_code
        ");
        
        // Lecturer Information
        echo '<div class="lecturer-info mb-4">';
        echo '<h1>Lecturer Report</h1>';
        echo '<div class="card">';
        echo '<div class="card-body">';
        echo '<h4 class="card-title">' . htmlspecialchars($lecturer['last_name'] . ', ' . $lecturer['first_name']) . '</h4>';
        
        $info = [];
        if (!empty($lecturer['email'])) $info[] = '<strong>Email:</strong> ' . htmlspecialchars($lecturer['email']);
        if (!empty($lecturer['phone'])) $info[] = '<strong>Phone:</strong> ' . htmlspecialchars($lecturer['phone']);
        if (!empty($lecturer['department_name'])) $info[] = '<strong>Department:</strong> ' . htmlspecialchars($lecturer['department_name']);
        if ($start_date && $end_date) {
            $info[] = '<strong>Period:</strong> ' . formatDate($start_date) . ' to ' . formatDate($end_date);
        }
        
        echo '<p class="card-text">' . implode(' | ', $info) . '</p>';
        echo '</div></div></div>';
        
        if ($courses->num_rows > 0) {
            $total_summary = [
                'total_students' => 0,
                'total_records' => 0,
                'present' => 0,
                'absent' => 0,
                'late' => 0
            ];
            
            while ($course = $courses->fetch_assoc()) {
                echo '<div class="course-section mb-5">';
                echo '<h4>' . htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']) . '</h4>';
                
                // Get attendance summary for this course
                $query = "
                    SELECT 
                        COUNT(DISTINCT a.user_id) as total_students,
                        COUNT(*) as total_records,
                        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present,
                        SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent,
                        SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late
                    FROM attendance a
                    WHERE a.course_id = ?
                ";
                
                $params = [$course['course_id']];
                $types = "i";
                
                if ($start_date && $end_date) {
                    $query .= " AND DATE(a.attendance_time) BETWEEN ? AND ?";
                    $params[] = $start_date;
                    $params[] = $end_date;
                    $types .= "ss";
                }
                
                $stmt = $conn->prepare($query);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $summary = $stmt->get_result()->fetch_assoc();
                
                // Add to total summary
                $total_summary['total_students'] += $summary['total_students'];
                $total_summary['total_records'] += $summary['total_records'];
                $total_summary['present'] += $summary['present'];
                $total_summary['absent'] += $summary['absent'];
                $total_summary['late'] += $summary['late'];
                
                $attendance_rate = $summary['total_records'] > 0 ? 
                    round(($summary['present'] / $summary['total_records']) * 100, 2) : 0;
                
                // Course summary
                echo '<div class="row mb-3">';
                
                $cards = [
                    ['Students', $summary['total_students'], 'primary'],
                    ['Records', $summary['total_records'], 'info'],
                    ['Present', $summary['present'], 'success'],
                    ['Absent', $summary['absent'], 'danger'],
                    ['Late', $summary['late'], 'warning'],
                    ['Rate', $attendance_rate . '%', 'secondary']
                ];
                
                foreach ($cards as $card) {
                    echo '<div class="col-md-4 mb-2">';
                    echo '<div class="card h-100 border-left-' . $card[2] . '">';
                    echo '<div class="card-body p-2">';
                    echo '<div class="row no-gutters align-items-center">';
                    echo '<div class="col mr-2">';
                    echo '<div class="text-xs font-weight-bold text-' . $card[2] . ' text-uppercase mb-1">' . $card[0] . '</div>';
                    echo '<div class="h6 mb-0 font-weight-bold text-gray-800">' . $card[1] . '</div>';
                    echo '</div></div></div></div></div>';
                }
                
                echo '</div>'; // End row
                
                // Get recent attendance for this course
                $query = "
                    SELECT 
                        a.attendance_time,
                        a.status,
                        CONCAT(u.last_name, ', ', u.first_name) as student_name,
                        s.student_number
                    FROM attendance a
                    JOIN users u ON a.user_id = u.user_id
                    LEFT JOIN students s ON u.user_id = s.user_id
                    WHERE a.course_id = ?
                ";
                
                $params = [$course['course_id']];
                $types = "i";
                
                if ($start_date && $end_date) {
                    $query .= " AND DATE(a.attendance_time) BETWEEN ? AND ?";
                    $params[] = $start_date;
                    $params[] = $end_date;
                    $types .= "ss";
                }
                
                $query .= " ORDER BY a.attendance_time DESC LIMIT 5";
                
                $stmt = $conn->prepare($query);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $recent_attendance = $stmt->get_result();
                
                if ($recent_attendance->num_rows > 0) {
                    echo '<h5>Recent Attendance</h5>';
                    echo '<div class="table-responsive">';
                    echo '<table class="table table-sm table-bordered">';
                    echo '<thead class="table-light"><tr><th>Date & Time</th><th>Student</th><th>Status</th></tr></thead><tbody>';
                    
                    while ($row = $recent_attendance->fetch_assoc()) {
                        echo '<tr>';
                        echo '<td>' . date('M j, Y h:i A', strtotime($row['attendance_time'])) . '</td>';
                        echo '<td>' . htmlspecialchars($row['student_name']);
                        if (!empty($row['student_number'])) {
                            echo ' <span class="text-muted">(' . htmlspecialchars($row['student_number']) . ')</span>';
                        }
                        echo '</td>';
                        echo '<td><span class="badge ' . 
                             ($row['status'] == 'present' ? 'bg-success' : 
                              ($row['status'] == 'absent' ? 'bg-danger' : 'bg-warning')) . 
                             '">' . ucfirst(htmlspecialchars($row['status'])) . '</span></td>';
                        echo '</tr>';
                    }
                    
                    echo '</tbody></table></div>';
                }
                
                echo '</div>'; // End course section
            }
            
            // Total summary
            $total_attendance_rate = $total_summary['total_records'] > 0 ? 
                round(($total_summary['present'] / $total_summary['total_records']) * 100, 2) : 0;
            
            echo '<div class="total-summary mt-4 pt-3 border-top">';
            echo '<h4>Overall Summary</h4>';
            echo '<div class="row">';
            
            $total_cards = [
                ['Total Students', $total_summary['total_students'], 'primary'],
                ['Total Records', $total_summary['total_records'], 'info'],
                ['Present', $total_summary['present'], 'success'],
                ['Absent', $total_summary['absent'], 'danger'],
                ['Late', $total_summary['late'], 'warning'],
                ['Overall Rate', $total_attendance_rate . '%', 'secondary']
            ];
            
            foreach ($total_cards as $card) {
                echo '<div class="col-md-4 mb-3">';
                echo '<div class="card h-100 border-left-' . $card[2] . '">';
                echo '<div class="card-body">';
                echo '<div class="row no-gutters align-items-center">';
                echo '<div class="col mr-2">';
                echo '<div class="text-xs font-weight-bold text-' . $card[2] . ' text-uppercase mb-1">' . $card[0] . '</div>';
                echo '<div class="h5 mb-0 font-weight-bold text-gray-800">' . $card[1] . '</div>';
                echo '</div>';
                echo '<div class="col-auto">';
                $icon = 'fa-chart-pie';
                if ($card[0] === 'Total Students') $icon = 'fa-users';
                elseif ($card[0] === 'Total Records') $icon = 'fa-list-ol';
                elseif ($card[0] === 'Present') $icon = 'fa-check-circle';
                elseif ($card[0] === 'Absent') $icon = 'fa-times-circle';
                elseif ($card[0] === 'Late') $icon = 'fa-clock';
                elseif ($card[0] === 'Overall Rate') $icon = 'fa-percentage';
                echo '<i class="fas ' . $icon . ' fa-2x text-gray-300"></i>';
                echo '</div></div></div></div></div>';
            }
            
            echo '</div>'; // End row
            echo '</div>'; // End total-summary
        } else {
            echo '<div class="alert alert-info">No courses assigned to this lecturer.</div>';
        }
        break;
        
    default:
        echo '<div class="alert alert-danger">Invalid report type specified.</div>';
        break;
}

echo '</div></div>'; // Close container and report-container

// Add print script with improved functionality
echo '
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function printReport() {
    window.print();
}

// Add event listeners for print and close buttons
document.addEventListener("keydown", function(e) {
    // Ctrl+P for print
    if ((e.ctrlKey || e.metaKey) && e.key === "p") {
        e.preventDefault();
        window.print();
    }
    // Escape key to close
    if (e.key === "Escape") {
        window.close();
    }
});

// Auto-print if specified in URL
if (window.location.search.includes("autoprint=1")) {
    window.print();
}
</script>';

echo '</body></html>';
?>
