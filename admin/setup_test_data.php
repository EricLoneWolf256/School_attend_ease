<?php
require_once '../config.php';

// Check if user is admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin privileges required.';
    redirect('../index.php');
}

$db = getDBConnection();

try {
    // Start transaction
    $db->beginTransaction();

    // 1. Add test students
    $students = [
        ['sarah_johnson', 'Sarah', 'Johnson', 'sarah.j@example.com', 'password123'],
        ['mike_williams', 'Mike', 'Williams', 'mike.w@example.com', 'password123'],
        ['emma_brown', 'Emma', 'Brown', 'emma.b@example.com', 'password123'],
        ['david_wilson', 'David', 'Wilson', 'david.w@example.com', 'password123'],
        ['lisa_miller', 'Lisa', 'Miller', 'lisa.m@example.com', 'password123']
    ];

    $studentIds = [];
    foreach ($students as $student) {
        $hashedPassword = password_hash($student[4], PASSWORD_DEFAULT);
        $stmt = $db->prepare("
            INSERT INTO users (username, first_name, last_name, email, password, role, created_at)
            VALUES (?, ?, ?, ?, ?, 'student', NOW())
            ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)
        
        $stmt->execute([$student[0], $student[1], $student[2], $student[3], $hashedPassword]);
        $studentIds[] = $db->lastInsertId();
    }

    // 2. Add test courses
    $courses = [
        ['CS101', 'Introduction to Computer Science', 'Basic programming concepts'],
        ['MATH201', 'Linear Algebra', 'Vectors, matrices, and linear transformations'],
        ['PHYS101', 'Physics I', 'Mechanics and thermodynamics']
    ];

    $courseIds = [];
    foreach ($courses as $course) {
        $stmt = $db->prepare("
            INSERT INTO courses (course_code, course_name, description, created_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE course_id=LAST_INSERT_ID(course_id)
        
        $stmt->execute($course);
        $courseIds[] = $db->lastInsertId();
    }

    // 3. Add test lecturer
    $hashedPassword = password_hash('lecturer123', PASSWORD_DEFAULT);
    $stmt = $db->prepare("
        INSERT INTO users (username, first_name, last_name, email, password, role, created_at)
        VALUES ('dr_smith', 'John', 'Smith', 'john.smith@university.edu', ?, 'lecturer', NOW())
        ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)
    
    $stmt->execute([$hashedPassword]);
    $lecturerId = $db->lastInsertId();

    // 4. Assign lecturer to courses
    foreach ($courseIds as $courseId) {
        $stmt = $db->prepare("
            INSERT IGNORE INTO course_assignments (lecturer_id, course_id, assigned_at)
            VALUES (?, ?, NOW())
        
        $stmt->execute([$lecturerId, $courseId]);
    }

    // 5. Create test lectures for the next 7 days
    $lectureTitles = [
        'Introduction to Programming',
        'Variables and Data Types',
        'Control Structures',
        'Functions and Scope',
        'Object-Oriented Programming',
        'File Handling',
        'Final Review'
    ];

    $lectureIds = [];
    $currentDate = new DateTime();
    for ($i = 0; $i < 7; $i++) {
        $lectureDate = clone $currentDate;
        $lectureDate->modify("+$i days");
        
        // Skip weekends
        if ($lectureDate->format('N') >= 6) continue;
        
        $startTime = new DateTime('09:00:00');
        $endTime = new DateTime('10:30:00');
        
        $stmt = $db->prepare("
            INSERT INTO lectures 
            (course_id, lecturer_id, title, description, scheduled_date, start_time, end_time, is_active, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, TRUE, NOW())
        
        $stmt->execute([
            $courseIds[0], // CS101
            $lecturerId,
            $lectureTitles[$i] ?? "Lecture " . ($i + 1),
            "Lecture description for " . ($lectureTitles[$i] ?? "Lecture " . ($i + 1)),
            $lectureDate->format('Y-m-d'),
            $startTime->format('H:i:s'),
            $endTime->format('H:i:s')
        ]);
        $lectureIds[] = $db->lastInsertId();
    }

    // 6. Mark attendance for some students
    $statuses = ['Present', 'Present', 'Present', 'Late', 'Absent', 'Present'];
    $feedback = [
        'Participated actively',
        'Asked good questions',
        'Needs to participate more',
        'Submitted assignment late',
        '',
        'Excellent performance'
    ];

    foreach ($lectureIds as $lectureId) {
        foreach ($studentIds as $index => $studentId) {
            if (isset($statuses[$index]) && $statuses[$index] !== 'Absent') {
                $stmt = $db->prepare("
                    INSERT INTO attendance (lecture_id, student_id, status, feedback, marked_at)
                    VALUES (?, ?, ?, ?, NOW())
                
                $stmt->execute([
                    $lectureId,
                    $studentId,
                    $statuses[$index] ?? 'Present',
                    $feedback[$index] ?? 'Marked present'
                ]);
            }
        }
    }

    $db->commit();
    $_SESSION['success'] = 'Test data has been successfully created!';

} catch (Exception $e) {
    $db->rollBack();
    $_SESSION['error'] = 'Error creating test data: ' . $e->getMessage();
}

redirect('attendance_report.php');
