<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getConnection();
    echo "=== Starting 9th Computer Green Student Data Import ===\n\n";

    // 1. Ensure Class ID for 9th Computer Science Green exists or locate existing
    $stmtCls = $db->prepare("SELECT id FROM classes WHERE (class_name LIKE '%Pre 9th%Comp%' OR class_name LIKE '%9th%Com%') AND section = 'Green' LIMIT 1");
    $stmtCls->execute();
    $classId = $stmtCls->fetchColumn();

    if (!$classId) {
        $stmtInsCls = $db->prepare("INSERT INTO classes (class_name, section) VALUES ('9th Comp', 'Green')");
        $stmtInsCls->execute();
        $classId = (int)$db->lastInsertId();
    }
    echo "Using Class ID: {$classId}\n";

    $studentsData = [
        [
            'admission_no' => 'IGS1254',
            'first_name' => 'ADIL',
            'last_name' => 'MUNEER',
            'gender' => 'Male',
            'date_of_birth' => '2025-10-16',
            'cnic_bform' => null,
            'enrollment_date' => '2025-10-16',
            'class_id' => $classId,
            'status' => 'Active',
            'guardian_name' => 'MUNEER AHMAD',
            'guardian_cnic' => '03477073016',
            'guardian_phone' => '03477073016',
            'guardian_email' => null,
            'address' => 'THOKAR NIAZ BAIG LHR',
            'academic_type' => 'School',
            'school_class' => 'Pre 9th Comp',
            'school_section' => 'Green',
            'roll_no' => '0',
            'student_mobile' => '03477073016',
            'father_name' => 'MUNEER AHMAD',
            'father_cnic' => '03477073016',
            'father_mobile' => '03477073016',
            'family_group' => '1233'
        ],
        [
            'admission_no' => 'IGS1303',
            'first_name' => 'AHTISHAM',
            'last_name' => 'HASSAN',
            'gender' => 'Male',
            'date_of_birth' => '2009-10-25',
            'cnic_bform' => null,
            'enrollment_date' => '2025-10-25',
            'class_id' => $classId,
            'status' => 'Active',
            'guardian_name' => 'GHULAM SHABBIR',
            'guardian_cnic' => '03004313506',
            'guardian_phone' => '03004313506',
            'guardian_email' => null,
            'address' => 'THOKAR NIAZ BAIG LHR',
            'academic_type' => 'School',
            'school_class' => 'Pre 9th Comp',
            'school_section' => 'Green',
            'roll_no' => '0',
            'student_mobile' => '03004313506',
            'father_name' => 'HAFIZ GHULAM HASSAN',
            'father_cnic' => '03004313506',
            'father_mobile' => '03004313506',
            'family_group' => '787'
        ],
        [
            'admission_no' => 'IGS1304',
            'first_name' => 'ALI',
            'last_name' => 'HAIDER',
            'gender' => 'Male',
            'date_of_birth' => '2010-05-12',
            'cnic_bform' => null,
            'enrollment_date' => '2025-10-26',
            'class_id' => $classId,
            'status' => 'Active',
            'guardian_name' => 'GHULAM HAIDER',
            'guardian_cnic' => '03214567890',
            'guardian_phone' => '03214567890',
            'guardian_email' => null,
            'address' => 'THOKAR NIAZ BAIG LHR',
            'academic_type' => 'School',
            'school_class' => 'Pre 9th Comp',
            'school_section' => 'Green',
            'roll_no' => '0',
            'student_mobile' => '03214567890',
            'father_name' => 'GHULAM HAIDER',
            'father_cnic' => '03214567890',
            'father_mobile' => '03214567890',
            'family_group' => '788'
        ]
    ];

    foreach ($studentsData as $st) {
        echo "Processing Student: {$st['first_name']} {$st['last_name']} ({$st['admission_no']})...\n";

        // Check if student exists by admission_no or first_name
        $stmtCheck = $db->prepare("SELECT id FROM students WHERE admission_no = ? OR (first_name = ? AND last_name = ?)");
        $stmtCheck->execute([$st['admission_no'], $st['first_name'], $st['last_name']]);
        $existingId = $stmtCheck->fetchColumn();

        if ($existingId) {
            echo "  Updating existing student ID: {$existingId}...\n";
            $stmtUpd = $db->prepare("
                UPDATE students SET 
                    admission_no = :adm,
                    first_name = :fn,
                    last_name = :ln,
                    gender = :gen,
                    date_of_birth = :dob,
                    cnic_bform = :cnic,
                    enrollment_date = :enr,
                    class_id = :cid,
                    status = :stat,
                    guardian_name = :gname,
                    guardian_cnic = :gcnic,
                    guardian_phone = :gphone,
                    address = :addr,
                    academic_type = :academic_type,
                    school_class = :school_class,
                    school_section = :school_section
                WHERE id = :id
            ");
            $stmtUpd->execute([
                'id' => $existingId,
                'adm' => $st['admission_no'],
                'fn' => $st['first_name'],
                'ln' => $st['last_name'],
                'gen' => $st['gender'],
                'dob' => $st['date_of_birth'],
                'cnic' => $st['cnic_bform'],
                'enr' => $st['enrollment_date'],
                'cid' => $st['class_id'],
                'stat' => $st['status'],
                'gname' => $st['guardian_name'],
                'gcnic' => $st['guardian_cnic'],
                'gphone' => $st['guardian_phone'],
                'addr' => $st['address'],
                'academic_type' => $st['academic_type'],
                'school_class' => $st['school_class'],
                'school_section' => $st['school_section']
            ]);
            $studentDbId = (int)$existingId;
        } else {
            echo "  Inserting new student record...\n";
            $stmtIns = $db->prepare("
                INSERT INTO students (
                    admission_no, roll_no, first_name, last_name, gender, date_of_birth, cnic_bform,
                    enrollment_date, class_id, status, guardian_name, guardian_cnic, guardian_phone, address,
                    academic_type, school_class, school_section
                ) VALUES (
                    :adm, :roll, :fn, :ln, :gen, :dob, :cnic,
                    :enr, :cid, :stat, :gname, :gcnic, :gphone, :addr,
                    :academic_type, :school_class, :school_section
                )
            ");
            $stmtIns->execute([
                'adm' => $st['admission_no'],
                'roll' => $st['roll_no'],
                'fn' => $st['first_name'],
                'ln' => $st['last_name'],
                'gen' => $st['gender'],
                'dob' => $st['date_of_birth'],
                'cnic' => $st['cnic_bform'],
                'enr' => $st['enrollment_date'],
                'cid' => $st['class_id'],
                'stat' => $st['status'],
                'gname' => $st['guardian_name'],
                'gcnic' => $st['guardian_cnic'],
                'gphone' => $st['guardian_phone'],
                'addr' => $st['address'],
                'academic_type' => $st['academic_type'],
                'school_class' => $st['school_class'],
                'school_section' => $st['school_section']
            ]);
            $studentDbId = (int)$db->lastInsertId();
        }

        // Insert or update registration details
        $stmtDet = $db->prepare("
            INSERT INTO student_registration_details (
                student_id, roll_no, admission_date, academic_session, campus,
                student_mobile, father_name, father_cnic, father_mobile,
                guardian_relationship, guardian_cnic, guardian_address, current_address, permanent_address,
                academic_type, school_class, school_section
            ) VALUES (
                :sid, :roll, :adate, :sess, :camp,
                :smob, :fname, :fcnic, :fmob,
                :grel, :gcnic, :gaddr, :curr_addr, :perm_addr,
                :academic_type, :school_class, :school_section
            ) ON DUPLICATE KEY UPDATE
                roll_no = VALUES(roll_no),
                father_name = VALUES(father_name),
                father_cnic = VALUES(father_cnic),
                school_class = VALUES(school_class),
                school_section = VALUES(school_section)
        ");
        $stmtDet->execute([
            'sid' => $studentDbId,
            'roll' => $st['roll_no'],
            'adate' => $st['enrollment_date'],
            'sess' => '2025-2026',
            'camp' => 'Main Campus',
            'smob' => $st['student_mobile'],
            'fname' => $st['father_name'],
            'fcnic' => $st['father_cnic'],
            'fmob' => $st['father_mobile'],
            'grel' => 'Father',
            'gcnic' => $st['guardian_cnic'],
            'gaddr' => $st['address'],
            'curr_addr' => $st['address'],
            'perm_addr' => $st['address'],
            'academic_type' => $st['academic_type'],
            'school_class' => $st['school_class'],
            'school_section' => $st['school_section']
        ]);
        echo "  Saved registration details for ID: {$studentDbId}.\n";
    }

    echo "\n=== Import Complete! All 3 student records synced cleanly. ===\n";

} catch (Exception $e) {
    echo "Import Error: " . $e->getMessage() . "\n";
}
