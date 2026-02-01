<?php
/**
 * Attendance System Functions
 */

/**
 * Generate a unique attendance code for a lecture
 * @param PDO $db Database connection
 * @param int $lecture_id ID of the lecture
 * @param int $lecturer_id ID of the lecturer
 * @param int $expiry_minutes Number of minutes until the code expires (default: 15)
 * @return array Result with status and message/code
 */
function generateAttendanceCode($db, $lecture_id, $lecturer_id, $expiry_minutes = 15) {
    try {
        // Verify the lecture exists and belongs to the lecturer
        $stmt = $db->prepare(
            "SELECT l.* FROM lectures l 
             JOIN course_assignments ca ON l.course_id = ca.course_id 
             WHERE l.lecture_id = ? AND ca.lecturer_id = ?"
        );
        $stmt->execute([$lecture_id, $lecturer_id]);
        $lecture = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$lecture) {
            return ['status' => 'error', 'message' => 'Lecture not found or access denied.'];
        }
        
        // Generate a random 6-character alphanumeric code (uppercase)
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        // Calculate expiry time
        $expiry_time = date('Y-m-d H:i:s', strtotime("+$expiry_minutes minutes"));
        
        // Update the lecture with the new code and expiry time
        $updateStmt = $db->prepare(
            "UPDATE lectures 
             SET secret_code = ?, 
                 code_expiry = ?,
                 is_active = 1,
                 updated_at = NOW()
             WHERE lecture_id = ?"
        );
        
        $updateStmt->execute([$code, $expiry_time, $lecture_id]);
        
        // Log the action
        logAction($db, $lecturer_id, 'attendance', 'generate_code', "Generated attendance code for lecture ID: $lecture_id");
        
        return [
            'status' => 'success',
            'code' => $code,
            'expires_at' => $expiry_time,
            'message' => 'Attendance code generated successfully.'
        ];
        
    } catch (PDOException $e) {
        error_log("Error generating attendance code: " . $e->getMessage());
        return ['status' => 'error', 'message' => 'Failed to generate attendance code. Please try again.'];
    }
}

/**

/**
 * Verify an attendance code and mark attendance if valid
 * @param PDO $db Database connection
 * @param int $lecture_id ID of the lecture
 * @param int $student_id ID of the student
 * @param string $code The code to verify
 * @return array Result with status and message
 */
function verifyAttendanceCode($db, $lecture_id, $student_id, $code) {
    try {
        // Start transaction
        $db->beginTransaction();
        
        // Get the lecture with the code and check if it's valid
        $stmt = $db->prepare(
            "SELECT l.*, c.course_id 
             FROM lectures l
             JOIN courses c ON l.course_id = c.course_id
             WHERE l.lecture_id = ? 
             AND l.secret_code = ? 
             AND l.is_active = 1 
             AND (l.code_expiry IS NULL OR l.code_expiry > NOW())"
        );
        $stmt->execute([$lecture_id, $code]);
        $lecture = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$lecture) {
            return ['status' => 'error', 'message' => 'Invalid or expired attendance code.'];
        }
        
        // Check if student is enrolled in the course
        $enrolled = $db->prepare(
            "SELECT 1 FROM student_courses 
             WHERE student_id = ? AND course_id = ?"
        );
        $enrolled->execute([$student_id, $lecture['course_id']]);
        
        if (!$enrolled->fetch()) {
            return ['status' => 'error', 'message' => 'You are not enrolled in this course.'];
        }
        
        // Check if student has already marked attendance
        $existing = $db->prepare(
            "SELECT 1 FROM attendance 
             WHERE lecture_id = ? AND student_id = ?"
        );
        $existing->execute([$lecture_id, $student_id]);
        
        if ($existing->fetch()) {
            // Update existing attendance
            $update = $db->prepare(
                "UPDATE attendance 
                 SET status = 'present', 
                     marked_at = NOW() 
                 WHERE lecture_id = ? AND student_id = ?"
            );
            $update->execute([$lecture_id, $student_id]);
            
            $message = 'Attendance updated successfully.';
        } else {
            // Insert new attendance record
            $insert = $db->prepare(
                "INSERT INTO attendance 
                 (lecture_id, student_id, status, marked_at) 
                 VALUES (?, ?, 'present', NOW())"
            );
            $insert->execute([$lecture_id, $student_id]);
            
            $message = 'Attendance marked successfully.';
        }
        
        // Log the action
        logAction($db, $student_id, 'attendance', 'mark', "Marked attendance for lecture ID: $lecture_id");
        
        $db->commit();
        
        return [
            'status' => 'success',
            'message' => $message,
            'lecture_title' => $lecture['title']
        ];
        
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Error verifying attendance code: " . $e->getMessage());
        return ['status' => 'error', 'message' => 'Failed to mark attendance. Please try again.'];
    }
}

/**
 * Get attendance list for a lecture
 */
function getAttendanceList($db, $lecture_id, $course_id) {
    // Debug: Log the parameters
    error_log("getAttendanceList called with lecture_id: $lecture_id, course_id: $course_id");
    
    // First, check if there are any students enrolled in this course
    $checkStudents = $db->prepare(
        "SELECT COUNT(*) as count FROM student_courses WHERE course_id = ?"
    );
    $checkStudents->execute([$course_id]);
    $studentCount = $checkStudents->fetch(PDO::FETCH_ASSOC)['count'];
    error_log("Number of students enrolled in course $course_id: $studentCount");
    
    // Check if there are any attendance records for this lecture
    $checkAttendance = $db->prepare(
        "SELECT COUNT(*) as count FROM attendance WHERE lecture_id = ?"
    );
    $checkAttendance->execute([$lecture_id]);
    $attendanceCount = $checkAttendance->fetch(PDO::FETCH_ASSOC)['count'];
    error_log("Number of attendance records for lecture $lecture_id: $attendanceCount");
    
    $stmt = $db->prepare(
        "SELECT s.user_id as student_id, st.student_number, 
                CONCAT(s.first_name, ' ', s.last_name) as student_name,
                IFNULL(a.status, 'absent') as status, 
                a.marked_at,
                a.feedback,
                CASE WHEN a.marked_at IS NOT NULL AND a.status = 'present' AND a.marked_at > ? THEN 1 ELSE 0 END as is_late
         FROM users s
         JOIN students st ON s.user_id = st.user_id
         JOIN student_courses sc ON s.user_id = sc.student_id
         LEFT JOIN attendance a ON s.user_id = a.student_id AND a.lecture_id = ?
         WHERE sc.course_id = ?
         ORDER BY a.status DESC, s.last_name, s.first_name"
    );
    
    // Get lecture start time for late calculation
    $lectureStmt = $db->prepare("SELECT start_time FROM lectures WHERE lecture_id = ?");
    $lectureStmt->execute([$lecture_id]);
    $lecture = $lectureStmt->fetch(PDO::FETCH_ASSOC);
    $lectureStartTime = $lecture ? $lecture['start_time'] : date('Y-m-d H:i:s');
    
    $stmt->execute([$lectureStartTime, $lecture_id, $course_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Log the number of results
    error_log("getAttendanceList returning " . count($results) . " records");
    if (count($results) > 0) {
        error_log("First record: " . print_r($results[0], true));
    }
    
    return $results;
}

/**
 * Handle manual attendance marking
 */
function handleManualAttendance($db, $lecture_id, $lecturer_id) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['mark_attendance'])) {
        return;
    }

    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = 'Invalid CSRF token.';
        return;
    }

    // Get lecture details to check if it's still active
    $lecture = getLectureDetails($db, $lecture_id, $lecturer_id);
    if (!$lecture) {
        $_SESSION['error'] = 'Lecture not found or access denied.';
        return;
    }

    // Get all students enrolled in the course
    $students = getCourseStudents($db, $lecture['course_id']);
    
    // Process each student's attendance
    foreach ($students as $student) {
        $student_id = $student['user_id'];
        $status = $_POST['attendance'][$student_id] ?? 'absent';
        $feedback = $_POST['feedback'][$student_id] ?? '';

        // Insert or update attendance record
        $stmt = $db->prepare(
            "INSERT INTO attendance (lecture_id, student_id, status, feedback, marked_at, is_late)
             VALUES (?, ?, ?, ?, NOW(), 
                CASE WHEN ? = 'present' AND NOW() > 
                    (SELECT CONCAT(scheduled_date, ' ', start_time) 
                     FROM lectures WHERE lecture_id = ?) 
                THEN 1 ELSE 0 END)
             ON DUPLICATE KEY UPDATE 
                status = VALUES(status),
                feedback = VALUES(feedback),
                marked_at = IF(VALUES(status) = 'present', NOW(), marked_at),
                is_late = VALUES(is_late)"
        );
        $stmt->execute([$lecture_id, $student_id, $status, $feedback, $status, $lecture_id]);
    }

    // Log the action
    logAction($db, $lecturer_id, 'attendance', 'manual_update', "Manually updated attendance for lecture $lecture_id");
    
    $_SESSION['success'] = 'Attendance has been updated successfully.';
    header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $lecture_id);
    exit;
}

/**
 * Handle generating attendance code
 */
function handleGenerateCode($db, $lecture_id, $lecturer_id) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['generate_code'])) {
        return;
    }

    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = 'Invalid CSRF token.';
        return;
    }

    // Check if lecture exists and belongs to lecturer
    $lecture = getLectureDetails($db, $lecture_id, $lecturer_id);
    if (!$lecture) {
        $_SESSION['error'] = 'Lecture not found or access denied.';
        return;
    }

    // Check if lecture has already ended
    if (strtotime($lecture['end_time']) < time()) {
        $_SESSION['error'] = 'Cannot generate code for a lecture that has already ended.';
        return;
    }

    // Generate a random 6-character code
    $code = strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour')); // Code expires in 1 hour

    // Save the code to the database
    $stmt = $db->prepare(
        "UPDATE lectures 
         SET attendance_code = ?, 
             code_generated_at = NOW(),
             code_expires_at = ?
         WHERE lecture_id = ?"
    );
    $stmt->execute([$code, $expires_at, $lecture_id]);

    // Log the action
    logAction($db, $lecturer_id, 'lecture', 'generate_code', "Generated attendance code for lecture $lecture_id");
    
    $_SESSION['success'] = "Attendance code generated: <strong>$code</strong> (expires at " . date('g:i A', strtotime($expires_at)) . ")";
    header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $lecture_id);
    exit;
}

/**
 * Export attendance data to CSV
 */
function handleExportAttendance($db, $lecture_id, $lecturer_id) {
    // Verify lecture exists and belongs to lecturer
    $lecture = getLectureDetails($db, $lecture_id, $lecturer_id);
    if (!$lecture) {
        $_SESSION['error'] = 'Lecture not found or access denied.';
        return;
    }

    // Get attendance data
    $attendance = getAttendanceList($db, $lecture_id, $lecture['course_id']);
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_lecture_' . $lecture_id . '.csv"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add CSV headers
    fputcsv($output, ['Student ID', 'Name', 'Status', 'Time', 'Feedback']);
    
    // Add data rows
    foreach ($attendance as $record) {
        fputcsv($output, [
            $record['student_number'],
            $record['student_name'],
            ucfirst($record['status']),
            $record['marked_at'] ? date('Y-m-d H:i', strtotime($record['marked_at'])) : 'N/A',
            $record['feedback'] ?? ''
        ]);
    }
    
    fclose($output);
    exit;
}

/**
 * Import attendance from CSV
 */
function handleImportAttendance($db, $lecture_id, $lecturer_id) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['import_file'])) {
        return;
    }

    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = 'Invalid CSRF token.';
        return;
    }

    // Verify lecture exists and belongs to lecturer
    $lecture = getLectureDetails($db, $lecture_id, $lecturer_id);
    if (!$lecture) {
        $_SESSION['error'] = 'Lecture not found or access denied.';
        return;
    }

    $file = $_FILES['import_file'];
    
    // Check for errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = 'Error uploading file.';
        return;
    }

    // Check file type (CSV)
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($file_ext !== 'csv') {
        $_SESSION['error'] = 'Only CSV files are allowed.';
        return;
    }

    // Read the file
    $handle = fopen($file['tmp_name'], 'r');
    if ($handle === false) {
        $_SESSION['error'] = 'Error reading file.';
        return;
    }

    // Skip header row
    $header = fgetcsv($handle);
    $imported = 0;
    $errors = [];

    // Process each row
    while (($data = fgetcsv($handle)) !== false) {
        if (count($data) < 3) continue; // Skip invalid rows
        
        $student_number = $data[0]; // Assuming first column is student number
        $status = strtolower(trim($data[1])); // Second column is status
        $feedback = $data[2] ?? ''; // Third column is feedback (optional)

        // Validate status
        if (!in_array($status, ['present', 'late', 'absent'])) {
            $errors[] = "Invalid status '$status' for student $student_number";
            continue;
        }

        // Get student ID from student number
        $stmt = $db->prepare(
            "SELECT s.user_id 
             FROM students s
             JOIN student_courses sc ON s.user_id = sc.student_id
             WHERE s.student_number = ? AND sc.course_id = ?"
        );
        $stmt->execute([$student_number, $lecture['course_id']]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            $errors[] = "Student $student_number not found in this course";
            continue;
        }

        // Insert or update attendance
        $stmt = $db->prepare(
            "INSERT INTO attendance (lecture_id, student_id, status, feedback, marked_at, is_late)
             VALUES (?, ?, ?, ?, NOW(), 
                CASE WHEN ? = 'present' AND NOW() > 
                    (SELECT CONCAT(scheduled_date, ' ', start_time) 
                     FROM lectures WHERE lecture_id = ?) 
                THEN 1 ELSE 0 END)
             ON DUPLICATE KEY UPDATE 
                status = VALUES(status),
                feedback = VALUES(feedback),
                marked_at = IF(VALUES(status) = 'present', NOW(), marked_at),
                is_late = VALUES(is_late)"
        );
        $stmt->execute([$lecture_id, $student['user_id'], $status, $feedback, $status, $lecture_id]);
        $imported++;
    }

    fclose($handle);

    // Log the action
    logAction($db, $lecturer_id, 'attendance', 'import', "Imported $imported attendance records for lecture $lecture_id");
    
    if (!empty($errors)) {
        $_SESSION['warning'] = "Imported $imported records with " . count($errors) . " errors.";
        $_SESSION['import_errors'] = $errors;
    } else {
        $_SESSION['success'] = "Successfully imported $imported attendance records.";
    }
    
    header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $lecture_id);
    exit;
}

/**
 * Get all students in a course
 */
function getCourseStudents($db, $course_id) {
    $stmt = $db->prepare(
        "SELECT u.user_id, u.first_name, u.last_name, s.student_number
         FROM users u
         JOIN students s ON u.user_id = s.user_id
         JOIN student_courses sc ON u.user_id = sc.student_id
         WHERE sc.course_id = ?
         ORDER BY u.last_name, u.first_name"
    );
    $stmt->execute([$course_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Log an action to the activity log
 */
function logAction($db, $user_id, $entity_type, $action, $details = '', $entity_id = 0) {
    try {
        $stmt = $db->prepare(
            "INSERT INTO activity_log 
             (user_id, entity_type, entity_id, action, details, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        
        $stmt->execute([
            $user_id,
            $entity_type,
            $entity_id,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        return $db->lastInsertId();
    } catch (PDOException $e) {
        // Log to error log but don't show to user
        error_log("Failed to log action: " . $e->getMessage());
        return false;
    }
}
