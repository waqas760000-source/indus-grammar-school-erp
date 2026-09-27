<?php
require_once __DIR__ . '/../config/app.php';

$insertData = [
    'grade'          => 'A*',
    'min_percentage' => 95.00,
    'max_percentage' => 100.00,
    'grade_point'    => 4.00,
    'remarks'        => 'High Distinction'
];

$id = GradeSetup::create($insertData);
echo "Created Grade ID: {$id}\n";

$fetched = GradeSetup::findById($id);
echo "Fetched Grade: " . ($fetched['grade'] ?? 'N/A') . " | GPA: " . ($fetched['grade_point'] ?? 'N/A') . "\n";

// Update
$updateOk = GradeSetup::update($id, array_merge($insertData, ['remarks' => 'Super Distinction']));
echo "Update Status: " . ($updateOk ? 'Success' : 'Failed') . "\n";

$fetchedUpdated = GradeSetup::findById($id);
echo "Updated Remarks: " . ($fetchedUpdated['remarks'] ?? 'N/A') . "\n";

// Delete
$deleteOk = GradeSetup::delete($id);
echo "Delete Status: " . ($deleteOk ? 'Success' : 'Failed') . "\n";
