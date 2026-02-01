<?php
require_once 'includes/db_connection.php';

// Function to safely execute a query and return results
function safeQuery($conn, $query, $params = []) {
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        die("Error preparing query: " . $conn->error);
    }
    
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        die("Error executing query: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Get database information
$dbInfo = [
    'database' => 'ghost',
    'table' => 'students',
    'column' => 'student_number'
];

// Get table structure
$tableInfo = safeQuery($conn, "SHOW COLUMNS FROM {$dbInfo['table']} WHERE Field = ?", [$dbInfo['column']]);
$indexes = safeQuery($conn, "SHOW INDEX FROM {$dbInfo['table']} WHERE Column_name = ?", [$dbInfo['column']]);

// Find duplicate student numbers
$duplicates = safeQuery($conn, "
    SELECT 
        {$dbInfo['column']}, 
        COUNT(*) as count, 
        GROUP_CONCAT(student_id) as ids,
        GROUP_CONCAT(CONCAT_WS(' ', first_name, last_name)) as names
    FROM {$dbInfo['table']}
    GROUP BY {$dbInfo['column']} 
    HAVING COUNT(*) > 1
    ORDER BY count DESC
");

// Find students with problematic numbers (with spaces, special chars, etc.)
$problematic = safeQuery($conn, "
    SELECT student_id, student_number, first_name, last_name
    FROM {$dbInfo['table']}
    WHERE student_number REGEXP '[^a-zA-Z0-9-]' 
    OR student_number LIKE '% %'
    OR student_number LIKE '%\t%'
    OR student_number LIKE '%\n%'
    OR student_number LIKE '%\r%'
    OR student_number != TRIM(student_number)
");

// Output results
header('Content-Type: text/plain');
echo "=== Student Number Duplicate Checker ===\n\n";

echo "=== Table Structure ===\n";
print_r($tableInfo);
echo "\n=== Indexes ===\n";
print_r($indexes);

echo "\n=== Duplicate Student Numbers ===\n";
if (empty($duplicates)) {
    echo "No duplicate student numbers found.\n";
} else {
    foreach ($duplicates as $dup) {
        echo "- '{$dup['student_number']}' appears {$dup['count']} times (IDs: {$dup['ids']}, Names: {$dup['names']})\n";
    }
}

echo "\n=== Potentially Problematic Student Numbers ===\n";
if (empty($problematic)) {
    echo "No problematic student numbers found.\n";
} else {
    foreach ($problematic as $prob) {
        $clean = preg_replace('/[^a-zA-Z0-9-]/', '.', $prob['student_number']);
        echo "- ID: {$prob['student_id']}, Name: {$prob['first_name']} {$prob['last_name']}\n";
        echo "  Original: '" . $prob['student_number'] . "'\n";
        echo "  Cleaned:  '" . $clean . "'\n";
        echo "  Length:   " . strlen($prob['student_number']) . " characters\n";
    }
}

// Check for students with the specific problematic number
$testNumber = '2024-Bs011-13455';
$testStudents = safeQuery($conn, "
    SELECT student_id, student_number, first_name, last_name, LENGTH(student_number) as len
    FROM {$dbInfo['table']}
    WHERE student_number = ?
    OR student_number LIKE ?
    OR TRIM(student_number) = ?
", [$testNumber, "%$testNumber%", $testNumber]);

echo "\n=== Students with number similar to '$testNumber' ===\n";
if (empty($testStudents)) {
    echo "No students found with this number.\n";
} else {
    foreach ($testStudents as $s) {
        echo "- ID: {$s['student_id']}, Name: {$s['first_name']} {$s['last_name']}, Number: '{$s['student_number']}' (Length: {$s['len']})\n";
        // Show hex representation to detect hidden characters
        $hex = '';
        for ($i = 0; $i < strlen($s['student_number']); $i++) {
            $hex .= sprintf("%02X ", ord($s['student_number'][$i]));
        }
        echo "  Hex: $hex\n";
    }
}

// Close connection
$conn->close();
?>
