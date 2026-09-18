<?php
require_once __DIR__ . '/../config/app.php';

$db = Database::getConnection();

echo "=========================================================\n";
echo "AUDITING ALL STUDENT RECORDS FOR ID CARD GENERATOR\n";
echo "=========================================================\n\n";

$sql = "
    SELECT s.*, c.class_name, c.section,
           d.roll_no, d.blood_group, d.emergency_contact, d.academic_session, d.doc_student_photo,
           d.father_name, d.father_mobile, d.current_address,
           d.admission_date, d.cnic_no, d.campus
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN student_registration_details d ON s.id = d.student_id
    WHERE s.status = 'Active'
    ORDER BY s.id ASC
";

$stmt = $db->query($sql);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total Active Students Found: " . count($students) . "\n\n";

function getStudentPhotoUrlTest($photoPath) {
    if (!empty($photoPath)) {
        $cleanPath = ltrim(str_replace('\\', '/', $photoPath), '/');
        $fullPath = __DIR__ . '/../' . $cleanPath;
        if (file_exists($fullPath)) {
            return [
                'type' => 'FILE_EXISTS',
                'url' => APP_URL . '/' . $cleanPath,
                'path' => $fullPath
            ];
        } else {
            return [
                'type' => 'FILE_MISSING_FALLBACK_SVG',
                'url' => APP_URL . '/assets/images/default_student.svg',
                'path' => $fullPath
            ];
        }
    }
    return [
        'type' => 'EMPTY_FALLBACK_SVG',
        'url' => APP_URL . '/assets/images/default_student.svg',
        'path' => 'N/A'
    ];
}

foreach ($students as $st) {
    $stName = trim(($st['first_name'] ?? '') . ' ' . ($st['last_name'] ?? ''));
    $admNo = $st['admission_no'] ?? '—';
    $rollNo = $st['roll_no'] ?: '—';
    $cnic = $st['cnic_no'] ?: '—';
    $className = $st['class_name'] ?? $st['school_class'] ?? '—';
    $sectionName = $st['section'] ?? $st['school_section'] ?? 'A';
    $campusName = $st['campus'] ?: 'Main Campus';
    $sessionName = $st['academic_session'] ?: '—';
    $academicType = $st['academic_type'] ?? 'School';
    $photoInfo = getStudentPhotoUrlTest($st['doc_student_photo'] ?? '');

    echo "---------------------------------------------------------\n";
    echo "STUDENT ID: {$st['id']}\n";
    echo "Name: $stName\n";
    echo "Admission No: $admNo\n";
    echo "Roll No: $rollNo\n";
    echo "CNIC: $cnic\n";
    echo "Class & Sec: $className - $sectionName\n";
    echo "Campus: $campusName | Session: $sessionName | Type: $academicType\n";
    echo "Photo Status: {$photoInfo['type']}\n";
    echo "Photo URL: {$photoInfo['url']}\n";
    if ($photoInfo['type'] === 'FILE_MISSING_FALLBACK_SVG') {
        echo "WARNING: DB photo column was set ('{$st['doc_student_photo']}') but file did NOT exist on disk! Reverting safely to default_student.svg.\n";
    }
    
    // Check if card object would have empty critical fields
    if (empty($stName) || $stName === '') {
        echo "CRITICAL WARNING: Student Name is EMPTY!\n";
    }
    if ($admNo === '—' || empty($admNo)) {
        echo "CRITICAL WARNING: Admission No is EMPTY!\n";
    }
}
echo "---------------------------------------------------------\n";
echo "\nAUDIT COMPLETE.\n";
