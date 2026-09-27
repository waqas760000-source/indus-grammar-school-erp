<?php
require_once __DIR__ . '/../config/app.php';

$db = Database::getConnection();

$classObj = $db->query("SELECT id FROM classes LIMIT 1")->fetchColumn();
$teacherObj = $db->query("SELECT id FROM staff WHERE status = 'Active' LIMIT 1")->fetchColumn();

if (!$classObj) {
    echo "Class missing.\n";
    exit;
}

$insertData = [
    'class_id'      => $classObj,
    'subject_name'  => 'Computer Science Test',
    'subject_code'  => 'CS-101',
    'total_marks'   => 100,
    'passing_marks' => 50,
    'academic_type' => 'School',
    'teacher_id'    => $teacherObj ?: 0,
    'status'        => 'Active'
];

$id = Subject::create($insertData);
echo "Created Subject ID: {$id}\n";

$fetched = Subject::findById($id);
echo "Fetched Subject: " . ($fetched['subject_name'] ?? 'N/A') . " | Passing: " . ($fetched['passing_marks'] ?? 'N/A') . "\n";

// Update
$updateOk = Subject::update($id, array_merge($insertData, ['passing_marks' => 45]));
echo "Update Status: " . ($updateOk ? 'Success' : 'Failed') . "\n";

$fetchedUpdated = Subject::findById($id);
echo "Updated Passing Marks: " . ($fetchedUpdated['passing_marks'] ?? 'N/A') . "\n";

// Delete
$deleteOk = Subject::delete($id);
echo "Delete Status: " . ($deleteOk ? 'Success' : 'Failed') . "\n";
