<?php
require_once __DIR__ . '/../config/app.php';

$db = Database::getConnection();

function testSearch($query) {
    global $db;
    $cleanQuery = str_replace('-', '', trim($query));
    $sql = "
        SELECT s.*, c.class_name, c.section,
               d.roll_no, d.blood_group, d.emergency_contact, d.academic_session, d.doc_student_photo,
               d.father_name, d.father_mobile, d.current_address,
               d.admission_date, d.cnic_no, d.campus
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN student_registration_details d ON s.id = d.student_id
        WHERE s.status = 'Active'
          AND (s.admission_no = :q_adm OR d.roll_no = :q_roll OR d.cnic_no = :q_cnic OR REPLACE(d.cnic_no, '-', '') = :q_clean)
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'q_adm' => $query,
        'q_roll' => $query,
        'q_cnic' => $query,
        'q_clean' => $cleanQuery
    ]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

echo "Testing Search for 'IGS-2026-0001':\n";
$res1 = testSearch('IGS-2026-0001');
echo "Count: " . count($res1) . "\n";
if (!empty($res1)) {
    echo "Name: " . $res1[0]['first_name'] . " " . $res1[0]['last_name'] . "\n";
    echo "Roll No: " . ($res1[0]['roll_no'] ?? 'N/A') . "\n";
    echo "CNIC: " . ($res1[0]['cnic_no'] ?? 'N/A') . "\n";
    echo "Campus: " . ($res1[0]['campus'] ?? 'N/A') . "\n";
}

echo "\nTesting Search by Roll No '101':\n";
$res2 = testSearch('101');
echo "Count: " . count($res2) . "\n";

echo "\nTesting Search by CNIC '12345-6789012-3':\n";
$res3 = testSearch('12345-6789012-3');
echo "Count: " . count($res3) . "\n";
