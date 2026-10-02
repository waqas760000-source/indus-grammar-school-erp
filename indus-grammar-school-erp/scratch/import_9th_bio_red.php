<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getConnection();
    echo "=== Starting 9th Biology Red Student Data & Fee Import ===\n\n";

    // 1. Locate Class ID for 9th Bio Red
    $stmtCls = $db->prepare("SELECT id FROM classes WHERE (class_name = '9th Bio' OR class_name LIKE '%9th%Bio%' OR class_name LIKE '%9TH-BIOLOGY%') AND (section = 'Red' OR section = 'red') LIMIT 1");
    $stmtCls->execute();
    $classId = (int)$stmtCls->fetchColumn();

    if (!$classId) {
        $stmtInsCls = $db->prepare("INSERT INTO classes (class_name, section) VALUES ('9th Bio', 'Red')");
        $stmtInsCls->execute();
        $classId = (int)$db->lastInsertId();
    }
    echo "Using Class ID: {$classId} (Class: 9th Bio, Section: Red)\n\n";

    // 2. Data array for the 13 students of 9th Biology Red
    $studentsData = [
        [
            'id' => 1197, 'adm' => 'IGS1197', 'fn' => 'AFIA', 'ln' => 'IMRAN', 'gender' => 'Female',
            'dob' => '2010-05-11', 'cnic' => null, 'adate' => '2025-08-26',
            'gname' => 'MUHAMMAD IMRAN', 'gcnic' => '03014757200', 'gphone' => '03014757200',
            'addr' => 'THOKAR NIAZ BAIG LHR', 'smob' => '03014757200',
            'fname' => 'MUHAMMAD IMRAN', 'fcnic' => '03014757200', 'fmob' => '03014757200',
            'group' => '974', 'std_fee' => 2500, 'disc' => 1000, 'net_fee' => 1500
        ],
        [
            'id' => 1094, 'adm' => 'AAS1094', 'fn' => 'AMNA', 'ln' => 'NADEEM', 'gender' => 'Female',
            'dob' => '2015-04-02', 'cnic' => null, 'adate' => '2025-04-16',
            'gname' => 'NADEEM HASSAN', 'gcnic' => '03062921019', 'gphone' => '03062921019',
            'addr' => 'THOKAR NIAZ BAIG LHR', 'smob' => '03062921019',
            'fname' => 'NADEEM HASSAN', 'fcnic' => '03062921019', 'fmob' => '03062921019',
            'group' => '885', 'std_fee' => 2500, 'disc' => 1000, 'net_fee' => 1500
        ],
        [
            'id' => 395, 'adm' => 'AAS-395', 'fn' => 'ASAR', 'ln' => 'FATIMA', 'gender' => 'Female',
            'dob' => '2010-01-01', 'cnic' => null, 'adate' => '2024-04-06',
            'gname' => 'ANWAR YASEEN', 'gcnic' => '35202-9165343-9', 'gphone' => '03014423749',
            'addr' => 'THOKAR NIAZ BAIG LHR', 'smob' => '03014423749',
            'fname' => 'ANWAR YASEEN', 'fcnic' => '35202-9165343-9', 'fmob' => '03014423749',
            'group' => '160', 'std_fee' => 2500, 'disc' => 1000, 'net_fee' => 1500
        ],
        [
            'id' => 1216, 'adm' => 'IGS1216', 'fn' => 'BUSHRA', 'ln' => 'AMANAT', 'gender' => 'Female',
            'dob' => '2025-10-02', 'cnic' => null, 'adate' => '2025-10-02',
            'gname' => 'AMANAT ALI', 'gcnic' => '03014655765', 'gphone' => '03014655765',
            'addr' => 'THOKAR NIAZ BAIG LHR', 'smob' => '03014655765',
            'fname' => 'AMANAT ALI', 'fcnic' => '03014655765', 'fmob' => '03014655765',
            'group' => '1211', 'std_fee' => 2500, 'disc' => 0, 'net_fee' => 2500
        ],
        [
            'id' => 383, 'adm' => 'AAS-383', 'fn' => 'FAJAR', 'ln' => 'NOOR', 'gender' => 'Female',
            'dob' => '2011-08-09', 'cnic' => '36401-5324853-4', 'adate' => '2023-03-04',
            'gname' => 'IRSHAD HUSSAIN', 'gcnic' => '31102-7791585-9', 'gphone' => '03000478672',
            'addr' => 'THOKAR NIAZ BAIG LHR', 'smob' => '923004868098',
            'fname' => 'IRSHAD HUSSAIN', 'fcnic' => '31102-7791585-9', 'fmob' => '03000478672',
            'group' => '10', 'std_fee' => 2500, 'disc' => 1500, 'net_fee' => 1000
        ],
        [
            'id' => 513, 'adm' => 'AAS513', 'fn' => 'FIZZA', 'ln' => 'WASEEM', 'gender' => 'Female',
            'dob' => '2009-04-30', 'cnic' => '03023371483', 'adate' => '2024-04-30',
            'gname' => 'WASEEM HABIB', 'gcnic' => '03023371483', 'gphone' => '03023371483',
            'addr' => 'THOKAR NIAZ BAIG LHR', 'smob' => '03023371483',
            'fname' => 'WASEEM HABIB', 'fcnic' => '03023371483', 'fmob' => '03023371483',
            'group' => '327', 'std_fee' => 2500, 'disc' => 1000, 'net_fee' => 1500
        ],
        [
            'id' => 399, 'adm' => 'AAS-399', 'fn' => 'HAMNA', 'ln' => 'WASEEM', 'gender' => 'Female',
            'dob' => '2010-01-01', 'cnic' => null, 'adate' => '2024-04-06',
            'gname' => 'WASEEM HABIB', 'gcnic' => '03023371483', 'gphone' => '03023371483',
            'addr' => 'THOKAR NIAZ BAIG LHR', 'smob' => '923023371483',
            'fname' => 'WASEEM HABIB', 'fcnic' => '03023371483', 'fmob' => '03023371483',
            'group' => '327', 'std_fee' => 2500, 'disc' => 1000, 'net_fee' => 1500
        ],
        [
            'id' => 1084, 'adm' => 'AAS1084', 'fn' => 'HANIA', 'ln' => 'FATIMA', 'gender' => 'Female',
            'dob' => '2025-04-14', 'cnic' => null, 'adate' => '2025-04-14',
            'gname' => 'MUHAMMAD IMTIAZ', 'gcnic' => '1444', 'gphone' => '1444',
            'addr' => 'THOKAR NIAZ BAIG LAHORE', 'smob' => '1444',
            'fname' => 'MUHAMMAD IMTIAZ', 'fcnic' => '1444', 'fmob' => '1444',
            'group' => '877', 'std_fee' => 2500, 'disc' => 1000, 'net_fee' => 1500
        ],
        [
            'id' => 1102, 'adm' => 'AAS1102', 'fn' => 'LAIBA', 'ln' => 'RASHID', 'gender' => 'Female',
            'dob' => '2025-04-17', 'cnic' => null, 'adate' => '2025-04-17',
            'gname' => 'RASHID', 'gcnic' => '4455', 'gphone' => '4455',
            'addr' => 'THOKAR NIAZ BAIG LAHORE', 'smob' => '4455',
            'fname' => 'RASHID', 'fcnic' => '4455', 'fmob' => '4455',
            'group' => '889', 'std_fee' => 2500, 'disc' => 1500, 'net_fee' => 1000
        ],
        [
            'id' => 895, 'adm' => 'AAS895', 'fn' => 'LAIBA', 'ln' => 'RIAZ', 'gender' => 'Female',
            'dob' => '2025-03-06', 'cnic' => null, 'adate' => '2025-03-06',
            'gname' => 'M.RIAZ', 'gcnic' => '03399999', 'gphone' => '03334916860',
            'addr' => 'THOKAR NIAZ BAIG LAHORE', 'smob' => '03334916860',
            'fname' => 'M.RIAZ', 'fcnic' => '03399999', 'fmob' => '03334916860',
            'group' => '698', 'std_fee' => 2500, 'disc' => 1000, 'net_fee' => 1500
        ],
        [
            'id' => 1217, 'adm' => 'IGS1217', 'fn' => 'MAHNOOR', 'ln' => 'AFZAL', 'gender' => 'Female',
            'dob' => '2025-10-02', 'cnic' => null, 'adate' => '2025-10-02',
            'gname' => 'MUHAMMAD AFZAL', 'gcnic' => '123', 'gphone' => '123',
            'addr' => 'THOKAR NIAZ BAIG LHR', 'smob' => '123',
            'fname' => 'MUHAMMAD AFZAL', 'fcnic' => '123', 'fmob' => '123',
            'group' => '787', 'std_fee' => 2500, 'disc' => 0, 'net_fee' => 2500
        ],
        [
            'id' => 1219, 'adm' => 'IGS1219', 'fn' => 'MARYAM', 'ln' => 'TARIQ', 'gender' => 'Female',
            'dob' => '2025-10-02', 'cnic' => null, 'adate' => '2025-10-02',
            'gname' => 'TARIQ NAEEM', 'gcnic' => '03271750045', 'gphone' => '03271750045',
            'addr' => 'THOKAR NIAZ BAIG LHR', 'smob' => '03271750045',
            'fname' => 'TARIQ NAEEM', 'fcnic' => '03271750045', 'fmob' => '03271750045',
            'group' => '1213', 'std_fee' => 2500, 'disc' => 0, 'net_fee' => 2500
        ],
        [
            'id' => 392, 'adm' => 'AAS-392', 'fn' => 'SYEDA RIDA', 'ln' => 'ZAHRA', 'gender' => 'Female',
            'dob' => '2011-01-01', 'cnic' => null, 'adate' => '2023-05-22',
            'gname' => 'HAJI SHAH', 'gcnic' => '03094433897', 'gphone' => '03094433897',
            'addr' => 'THOKAR NIAZ BAIG LHR', 'smob' => '923334916860',
            'fname' => 'HAJI SHAH', 'fcnic' => '03094433897', 'fmob' => '03094433897',
            'group' => '447', 'std_fee' => 2500, 'disc' => 1000, 'net_fee' => 1500
        ]
    ];

    $countProcessed = 0;
    $countInserted = 0;
    $countUpdated = 0;

    foreach ($studentsData as $st) {
        $countProcessed++;
        $photoRelPath = "uploads/students/std_{$st['id']}_photo.jpeg";

        echo "Processing [{$countProcessed}/13]: ID {$st['id']} - {$st['fn']} {$st['ln']} ({$st['adm']})...\n";

        // Check if student exists
        $stmtCheck = $db->prepare("SELECT id FROM students WHERE id = ? OR admission_no = ?");
        $stmtCheck->execute([$st['id'], $st['adm']]);
        $existingId = $stmtCheck->fetchColumn();

        if ($existingId) {
            echo "  Updating existing student record (ID: {$existingId})...\n";
            $stmtUpd = $db->prepare("
                UPDATE students SET 
                    admission_no = :adm,
                    first_name = :fn,
                    last_name = :ln,
                    gender = 'Female',
                    date_of_birth = :dob,
                    cnic_bform = :cnic,
                    enrollment_date = :adate,
                    class_id = :cid,
                    status = 'Active',
                    guardian_name = :gname,
                    guardian_cnic = :gcnic,
                    guardian_phone = :gphone,
                    address = :addr,
                    academic_type = 'School',
                    school_class = '9th Bio',
                    school_section = 'Red',
                    tuition_fee = :net_fee
                WHERE id = :id
            ");
            $stmtUpd->execute([
                'id' => $existingId,
                'adm' => $st['adm'],
                'fn' => $st['fn'],
                'ln' => $st['ln'],
                'dob' => $st['dob'],
                'cnic' => $st['cnic'],
                'adate' => $st['adate'],
                'cid' => $classId,
                'gname' => $st['gname'],
                'gcnic' => $st['gcnic'],
                'gphone' => $st['gphone'],
                'addr' => $st['addr'],
                'net_fee' => $st['net_fee']
            ]);
            $studentDbId = (int)$existingId;
            $countUpdated++;
        } else {
            echo "  Inserting new student record...\n";
            $stmtIns = $db->prepare("
                INSERT INTO students (
                    id, admission_no, roll_no, first_name, last_name, gender, date_of_birth, cnic_bform,
                    enrollment_date, class_id, status, guardian_name, guardian_cnic, guardian_phone, address,
                    academic_type, school_class, school_section, tuition_fee
                ) VALUES (
                    :id, :adm, '0', :fn, :ln, 'Female', :dob, :cnic,
                    :adate, :cid, 'Active', :gname, :gcnic, :gphone, :addr,
                    'School', '9th Bio', 'Red', :net_fee
                )
            ");
            $stmtIns->execute([
                'id' => $st['id'],
                'adm' => $st['adm'],
                'fn' => $st['fn'],
                'ln' => $st['ln'],
                'dob' => $st['dob'],
                'cnic' => $st['cnic'],
                'adate' => $st['adate'],
                'cid' => $classId,
                'gname' => $st['gname'],
                'gcnic' => $st['gcnic'],
                'gphone' => $st['gphone'],
                'addr' => $st['addr'],
                'net_fee' => $st['net_fee']
            ]);
            $studentDbId = $st['id'];
            $countInserted++;
        }

        // Insert or update registration details
        $stmtDet = $db->prepare("
            INSERT INTO student_registration_details (
                student_id, roll_no, admission_date, academic_session, campus,
                religion, nationality,
                student_mobile, father_name, father_cnic, father_mobile,
                guardian_relationship, guardian_cnic, guardian_address, current_address, permanent_address,
                doc_student_photo, academic_type, school_class, school_section,
                fee_monthly, fee_discount, tuition_fee
            ) VALUES (
                :sid, '0', :adate, '2025-2026', 'Main Campus',
                'Islam', 'Pakistani',
                :smob, :fname, :fcnic, :fmob,
                'Father', :gcnic, :gaddr, :curr_addr, :perm_addr,
                :photo, 'School', '9th Bio', 'Red',
                :std_fee, :disc, :net_fee
            ) ON DUPLICATE KEY UPDATE
                roll_no = VALUES(roll_no),
                admission_date = VALUES(admission_date),
                religion = VALUES(religion),
                nationality = VALUES(nationality),
                student_mobile = VALUES(student_mobile),
                father_name = VALUES(father_name),
                father_cnic = VALUES(father_cnic),
                father_mobile = VALUES(father_mobile),
                guardian_cnic = VALUES(guardian_cnic),
                guardian_address = VALUES(guardian_address),
                doc_student_photo = VALUES(doc_student_photo),
                school_class = VALUES(school_class),
                school_section = VALUES(school_section),
                fee_monthly = VALUES(fee_monthly),
                fee_discount = VALUES(fee_discount),
                tuition_fee = VALUES(tuition_fee)
        ");
        $stmtDet->execute([
            'sid' => $studentDbId,
            'adate' => $st['adate'],
            'smob' => $st['smob'],
            'fname' => $st['fname'],
            'fcnic' => $st['fcnic'],
            'fmob' => $st['fmob'],
            'gcnic' => $st['gcnic'],
            'gaddr' => $st['addr'],
            'curr_addr' => $st['addr'],
            'perm_addr' => $st['addr'],
            'photo' => $photoRelPath,
            'std_fee' => $st['std_fee'],
            'disc' => $st['disc'],
            'net_fee' => $st['net_fee']
        ]);
    }

    echo "\n=== Import Complete! Processed: {$countProcessed}, Inserted: {$countInserted}, Updated: {$countUpdated} ===\n";

} catch (Exception $e) {
    echo "Import Error: " . $e->getMessage() . "\n";
}
