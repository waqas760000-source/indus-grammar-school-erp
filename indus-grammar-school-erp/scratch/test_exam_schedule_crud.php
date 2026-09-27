<?php
require_once __DIR__ . '/../config/app.php';

$db = Database::getConnection();

// Create test schedule
$examType = $db->query("SELECT id FROM exam_types LIMIT 1")->fetchColumn();
$classObj = $db->query("SELECT id FROM classes LIMIT 1")->fetchColumn();
$subjectObj = $db->query("SELECT id FROM subjects LIMIT 1")->fetchColumn();

if (!$examType || !$classObj || !$subjectObj) {
    echo "Prerequisite data missing.\n";
    exit;
}

$insertData = [
    'exam_type_id' => $examType,
    'class_id'     => $classObj,
    'subject_id'   => $subjectObj,
    'exam_date'    => date('Y-m-d', strtotime('+3 days')),
    'start_time'   => '10:00',
    'end_time'     => '13:00',
    'room'         => 'Hall C Test',
    'supervisor'   => 'Dr. Test Supervisor'
];

$id = ExamSchedule::create($insertData);
echo "Created ExamSchedule ID: {$id}\n";

$fetched = ExamSchedule::findById($id);
echo "Fetched Room: " . ($fetched['room'] ?? 'N/A') . " | Supervisor: " . ($fetched['supervisor'] ?? 'N/A') . "\n";

// Update
$updateOk = ExamSchedule::update($id, array_merge($insertData, ['room' => 'Hall C Updated']));
echo "Update Status: " . ($updateOk ? 'Success' : 'Failed') . "\n";

// Verify update
$fetchedUpdated = ExamSchedule::findById($id);
echo "Updated Room: " . ($fetchedUpdated['room'] ?? 'N/A') . "\n";

// Delete test record
$deleteOk = ExamSchedule::delete($id);
echo "Delete Status: " . ($deleteOk ? 'Success' : 'Failed') . "\n";
