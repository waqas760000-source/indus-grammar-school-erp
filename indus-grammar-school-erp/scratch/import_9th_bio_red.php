<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getConnection();
    echo "=== Starting 9th Biology Red Student Data Import ===\n\n";

    // 1. Locate or create Class ID for 9th Biology Red
    $stmtCls = $db->prepare("SELECT id FROM classes WHERE (class_name LIKE '%Pre 9th%Bio%' OR class_name LIKE '%9th%Bio%') AND (section = 'Red' OR section = 'red') LIMIT 1");
    $stmtCls->execute();
    $classId = $stmtCls->fetchColumn();

    if (!$classId) {
        $stmtInsCls = $db->prepare("INSERT INTO classes (class_name, section) VALUES ('Pre 9th Bio', 'Red')");
        $stmtInsCls->execute();
        $classId = (int)$db->lastInsertId();
    }
    echo "Using Class ID: {$classId} (Class: Pre 9th Bio / 9th Biology, Section: Red)\n\n";

    $studentsData = [
        [
            'id' => 338, 'admission_no' => 'AAS-338', 'first_name' => 'ALIHA', 'last_name' => 'SAJID',
            'gender' => 'Female', 'date_of_birth' => '2011-12-23', 'cnic_bform' => null,
            'enrollment_date' => '2024-03-27', 'class_id' => $classId, 'status' => 'Active',
            'guardian_name' => 'SAJID ALI', 'guardian_cnic' => '35202-6076324-9', 'guardian_phone' => '03440459724',
            'address' => 'THOKAR NIAZ BAIG LHR', 'student_mobile' => '923440459724', 'father_name' => 'SAJID ALI',
            'father_cnic' => '35202-6076324-9', 'father_mobile' => '03440459724', 'family_group' => '127',
            'academic_type' => 'School', 'school_class' => 'Pre 9th Bio', 'school_section' => 'Red'
        ],
        [
            'id' => 341, 'admission_no' => 'AAS-341', 'first_name' => 'AROOJ', 'last_name' => 'FATIMA',
            'gender' => 'Female', 'date_of_birth' => '2012-01-02', 'cnic_bform' => null,
            'enrollment_date' => '2023-03-11', 'class_id' => $classId, 'status' => 'Active',
            'guardian_name' => 'MUZZAFAR ABBAS', 'guardian_cnic' => '33203-1464199-3', 'guardian_phone' => '03007546524',
            'address' => 'THOKAR NIAZ BAIGLHR.', 'student_mobile' => '923007546524', 'father_name' => 'MUZZAFAR ABBAS',
            'father_cnic' => '33203-1464199-3', 'father_mobile' => '03007546524', 'family_group' => '27',
            'academic_type' => 'School', 'school_class' => 'Pre 9th Bio', 'school_section' => 'Red'
        ],
        [
            'id' => 342, 'admission_no' => 'AAS-342', 'first_name' => 'BATOOL', 'last_name' => 'FATIMA',
            'gender' => 'Female', 'date_of_birth' => '2012-01-18', 'cnic_bform' => null,
            'enrollment_date' => '2023-03-03', 'class_id' => $classId, 'status' => 'Active',
            'guardian_name' => 'AKBAR ALI', 'guardian_cnic' => '35202-2411613-7', 'guardian_phone' => '03014224801',
            'address' => 'THOKAR NIAZ BAIG LHR', 'student_mobile' => '923014224801', 'father_name' => 'AKBAR ALI',
            'father_cnic' => '35202-2411613-7', 'father_mobile' => '03014224801', 'family_group' => '90',
            'academic_type' => 'School', 'school_class' => 'Pre 9th Bio', 'school_section' => 'Red'
        ],
        [
            'id' => 346, 'admission_no' => 'AAS-346', 'first_name' => 'HALEEMA', 'last_name' => 'SADIA',
            'gender' => 'Female', 'date_of_birth' => '2013-06-30', 'cnic_bform' => '35202-8287018-8',
            'enrollment_date' => '2024-02-15', 'class_id' => $classId, 'status' => 'Active',
            'guardian_name' => 'MUHAMMAD TARIQ ISMAEL', 'guardian_cnic' => '35202-8730767-3', 'guardian_phone' => '03064224191',
            'address' => 'THOKAR NIAZ BAIG LHR', 'student_mobile' => '923064224191', 'father_name' => 'MUHAMMAD TARIQ ISMAEL',
            'father_cnic' => '35202-8730767-3', 'father_mobile' => '03064224191', 'family_group' => '155',
            'academic_type' => 'School', 'school_class' => 'Pre 9th Bio', 'school_section' => 'Red'
        ],
        [
            'id' => 350, 'admission_no' => 'AAS-350', 'first_name' => 'KINZA', 'last_name' => 'NAVEED',
            'gender' => 'Female', 'date_of_birth' => '2011-12-12', 'cnic_bform' => null,
            'enrollment_date' => '2023-03-11', 'class_id' => $classId, 'status' => 'Active',
            'guardian_name' => 'NAVEED AHMAD', 'guardian_cnic' => '35202-9885978-7', 'guardian_phone' => '03466940781',
            'address' => 'THOKAR NIAZ BAIG LHR.', 'student_mobile' => null, 'father_name' => 'NAVEED AHMAD',
            'father_cnic' => '35202-9885978-7', 'father_mobile' => '03466940781', 'family_group' => '167',
            'academic_type' => 'School', 'school_class' => 'Pre 9th Bio', 'school_section' => 'Red'
        ],
        [
            'id' => 351, 'admission_no' => 'AAS-351', 'first_name' => 'MAHNOOR', 'last_name' => '',
            'gender' => 'Female', 'date_of_birth' => '2011-07-27', 'cnic_bform' => null,
            'enrollment_date' => '2024-03-27', 'class_id' => $classId, 'status' => 'Active',
            'guardian_name' => 'KHUDA BUKHSH', 'guardian_cnic' => '35200-1525667-1', 'guardian_phone' => '03084767918',
            'address' => 'THOKAR NIAZ BAIG LHR', 'student_mobile' => '923084767918', 'father_name' => 'KHUDA BUKHSH',
            'father_cnic' => '35200-1525667-1', 'father_mobile' => '03084767918', 'family_group' => '59',
            'academic_type' => 'School', 'school_class' => 'Pre 9th Bio', 'school_section' => 'Red'
        ],
        [
            'id' => 368, 'admission_no' => 'AAS-368', 'first_name' => 'RIMSHA', 'last_name' => 'SHAKEEL',
            'gender' => 'Female', 'date_of_birth' => '2011-05-16', 'cnic_bform' => '35202-3653992-0',
            'enrollment_date' => '2024-03-25', 'class_id' => $classId, 'status' => 'Active',
            'guardian_name' => 'SHAKEELHASSAN KHAN', 'guardian_cnic' => '35202-2542848-1', 'guardian_phone' => '03215557664',
            'address' => 'THOKAR NIAZ BAIG LHR', 'student_mobile' => '923214442036', 'father_name' => 'SHAKEELHASSAN KHAN',
            'father_cnic' => '35202-2542848-1', 'father_mobile' => '03215557664', 'family_group' => '98',
            'academic_type' => 'School', 'school_class' => 'Pre 9th Bio', 'school_section' => 'Red'
        ],
        [
            'id' => 369, 'admission_no' => 'AAS-369', 'first_name' => 'RUBAB', 'last_name' => 'ZAHRA',
            'gender' => 'Female', 'date_of_birth' => '2011-01-10', 'cnic_bform' => null,
            'enrollment_date' => '2024-03-26', 'class_id' => $classId, 'status' => 'Active',
            'guardian_name' => 'NADEEM RAZA SHAD', 'guardian_cnic' => '35202-2525135-1', 'guardian_phone' => '03097275279',
            'address' => 'THOKAR NIAZ BAIG LHR', 'student_mobile' => '923097275279', 'father_name' => 'NADEEM RAZA SHAD',
            'father_cnic' => '35202-2525135-1', 'father_mobile' => '03097275279', 'family_group' => '97',
            'academic_type' => 'School', 'school_class' => 'Pre 9th Bio', 'school_section' => 'Red'
        ],
        [
            'id' => 552, 'admission_no' => 'AAS552', 'first_name' => 'AFSHEEN', 'last_name' => 'ZAHRA',
            'gender' => 'Female', 'date_of_birth' => '2011-05-07', 'cnic_bform' => '03221545054',
            'enrollment_date' => '2024-05-07', 'class_id' => $classId, 'status' => 'Active',
            'guardian_name' => 'BISHARAT ALI', 'guardian_cnic' => '03221545054', 'guardian_phone' => '03221545054',
            'address' => 'THOKAR NIAZ BAIG LHR', 'student_mobile' => '03221545054', 'father_name' => 'BISHARAT ALI',
            'father_cnic' => '03221545054', 'father_mobile' => '03221545054', 'family_group' => '367',
            'academic_type' => 'School', 'school_class' => 'Pre 9th Bio', 'school_section' => 'Red'
        ],
        [
            'id' => 596, 'admission_no' => 'AAS596', 'first_name' => 'RAFIA', 'last_name' => '',
            'gender' => 'Female', 'date_of_birth' => '2024-08-16', 'cnic_bform' => '03027242032',
            'enrollment_date' => '2024-08-16', 'class_id' => $classId, 'status' => 'Active',
            'guardian_name' => 'ZIA-UL-HAQ', 'guardian_cnic' => '35301-1956360-1', 'guardian_phone' => '03027242032',
            'address' => 'THOKAR NIAZ BAIG LHR', 'student_mobile' => '03027242032', 'father_name' => 'ZIA-UL-HAQ',
            'father_cnic' => '35301-1956360-1', 'father_mobile' => '03027242032', 'family_group' => '475',
            'academic_type' => 'School', 'school_class' => 'Pre 9th Bio', 'school_section' => 'Red'
        ],
        [
            'id' => 631, 'admission_no' => 'AAS631', 'first_name' => 'KIRAN', 'last_name' => 'FATIMA',
            'gender' => 'Female', 'date_of_birth' => '2024-09-02', 'cnic_bform' => '36104-2465592-4',
            'enrollment_date' => '2024-09-02', 'class_id' => $classId, 'status' => 'Active',
            'guardian_name' => 'MUHAMMAD AKRAM', 'guardian_cnic' => '36104-4429818-3', 'guardian_phone' => '03045240796',
            'address' => 'THOKAR NIAZ BAIG LHR', 'student_mobile' => '03045240796', 'father_name' => 'MUHAMMAD AKRAM',
            'father_cnic' => '36104-4429818-3', 'father_mobile' => '03045240796', 'family_group' => '495',
            'academic_type' => 'School', 'school_class' => 'Pre 9th Bio', 'school_section' => 'Red'
        ],
        [
            'id' => 632, 'admission_no' => 'AAS632', 'first_name' => 'IRAM', 'last_name' => 'FATIMA',
            'gender' => 'Female', 'date_of_birth' => '2024-09-02', 'cnic_bform' => '35104-2456947-4',
            'enrollment_date' => '2024-09-02', 'class_id' => $classId, 'status' => 'Active',
            'guardian_name' => 'MUHAMMAD AKRAM', 'guardian_cnic' => '36104-4429818-3', 'guardian_phone' => '03045240796',
            'address' => 'THOKAR NIAZ BAIG LHR', 'student_mobile' => '03045240796', 'father_name' => 'MUHAMMAD AKRAM',
            'father_cnic' => '36104-4429818-3', 'father_mobile' => '03045240796', 'family_group' => '495',
            'academic_type' => 'School', 'school_class' => 'Pre 9th Bio', 'school_section' => 'Red'
        ],
        [
            'id' => 661, 'admission_no' => 'AAS661', 'first_name' => 'MEERAB', 'last_name' => 'SHOAIB',
            'gender' => 'Female', 'date_of_birth' => '2014-09-01', 'cnic_bform' => '35103-8141120-0',
            'enrollment_date' => '2024-09-09', 'class_id' => $classId, 'status' => 'Active',
            'guardian_name' => 'MUHAMMAD SHOAIB', 'guardian_cnic' => '35103-1313430-9', 'guardian_phone' => '03334495772',
            'address' => 'THOKAR NIAZ BAIG LHR', 'student_mobile' => '03334495772', 'father_name' => 'MUHAMMAD SHOAIB',
            'father_cnic' => '35103-1313430-9', 'father_mobile' => '03334495772', 'family_group' => '522',
            'academic_type' => 'School', 'school_class' => 'Pre 9th Bio', 'school_section' => 'Red'
        ],
        [
            'id' => 720, 'admission_no' => 'AAS720', 'first_name' => 'SUMMAYYA', 'last_name' => 'TUFAIL',
            'gender' => 'Female', 'date_of_birth' => '2012-11-06', 'cnic_bform' => null,
            'enrollment_date' => '2024-11-02', 'class_id' => $classId, 'status' => 'Active',
            'guardian_name' => 'MUHMMAD TUFAIL', 'guardian_cnic' => '03007579728', 'guardian_phone' => '03007579728',
            'address' => 'THOKAR NIAZ BAIG LHR', 'student_mobile' => '03007579728', 'father_name' => 'MUHMMAD TUFAIL',
            'father_cnic' => '03007579728', 'father_mobile' => '03007579728', 'family_group' => '558',
            'academic_type' => 'School', 'school_class' => 'Pre 9th Bio', 'school_section' => 'Red'
        ],
        [
            'id' => 877, 'admission_no' => 'AAS877', 'first_name' => 'ZAINAB', 'last_name' => 'JAHANGIR',
            'gender' => 'Female', 'date_of_birth' => '2016-01-01', 'cnic_bform' => null,
            'enrollment_date' => '2025-03-05', 'class_id' => $classId, 'status' => 'Active',
            'guardian_name' => 'JAHANGIR ABBAS', 'guardian_cnic' => '03034272786', 'guardian_phone' => '03034272786',
            'address' => 'THOKAR NIAZ BAIG LHR.', 'student_mobile' => '03034272786', 'father_name' => 'JAHANGIR ABBAS',
            'father_cnic' => '03034272786', 'father_mobile' => '03034272786', 'family_group' => '687',
            'academic_type' => 'School', 'school_class' => 'Pre 9th Bio', 'school_section' => 'Red'
        ],
        [
            'id' => 906, 'admission_no' => 'AAS906', 'first_name' => 'BISMA', 'last_name' => 'AFZAL',
            'gender' => 'Female', 'date_of_birth' => '2011-08-01', 'cnic_bform' => '35202-1681257-4',
            'enrollment_date' => '2025-03-08', 'class_id' => $classId, 'status' => 'Active',
            'guardian_name' => 'MUHAMMAD AFZAL', 'guardian_cnic' => '35202-2681257-4', 'guardian_phone' => '03034699137',
            'address' => 'THOKAR NIAZ BAIG LHR', 'student_mobile' => '03034699137', 'father_name' => 'MUHAMMAD AFZAL',
            'father_cnic' => '35202-2681257-4', 'father_mobile' => '03034699137', 'family_group' => '709',
            'academic_type' => 'School', 'school_class' => 'Pre 9th Bio', 'school_section' => 'Red'
        ],
        [
            'id' => 1213, 'admission_no' => 'IGS1213', 'first_name' => 'AYESHA', 'last_name' => 'NADEEM',
            'gender' => 'Female', 'date_of_birth' => '2008-10-02', 'cnic_bform' => null,
            'enrollment_date' => '2025-09-01', 'class_id' => $classId, 'status' => 'Active',
            'guardian_name' => 'NADEEM HUSSAIN', 'guardian_cnic' => '03220424498', 'guardian_phone' => '03220424498',
            'address' => 'THOKAR NIAZ BAIG LHR', 'student_mobile' => null, 'father_name' => 'NADEEM HUSSAIN',
            'father_cnic' => '03220424498', 'father_mobile' => '03220424498', 'family_group' => '1210',
            'academic_type' => 'School', 'school_class' => 'Pre 9th Bio', 'school_section' => 'Red'
        ],
        [
            'id' => 1314, 'admission_no' => 'IGS1314', 'first_name' => 'ZAINAB', 'last_name' => 'IMDAD',
            'gender' => 'Female', 'date_of_birth' => '2012-11-12', 'cnic_bform' => null,
            'enrollment_date' => '2025-11-01', 'class_id' => $classId, 'status' => 'Active',
            'guardian_name' => 'IMDAD ALI', 'guardian_cnic' => '03194994951', 'guardian_phone' => '03194994951',
            'address' => 'THOKAR NIAZ BAIG LHR', 'student_mobile' => null, 'father_name' => 'IMDAD ALI',
            'father_cnic' => '03194994951', 'father_mobile' => '03194994951', 'family_group' => '1262',
            'academic_type' => 'School', 'school_class' => 'Pre 9th Bio', 'school_section' => 'Red'
        ],
        [
            'id' => 1335, 'admission_no' => 'IGS1335', 'first_name' => 'MUNIZA', 'last_name' => 'IBRAR',
            'gender' => 'Female', 'date_of_birth' => '2015-12-15', 'cnic_bform' => null,
            'enrollment_date' => '2025-12-01', 'class_id' => $classId, 'status' => 'Active',
            'guardian_name' => 'MUHAMMAD IBRAR', 'guardian_cnic' => '03126956758', 'guardian_phone' => '03126956758',
            'address' => 'THOKAR NIAZ BAIG LHR', 'student_mobile' => null, 'father_name' => 'MUHAMMAD IBRAR',
            'father_cnic' => '03126956758', 'father_mobile' => '03126956758', 'family_group' => '1283',
            'academic_type' => 'School', 'school_class' => 'Pre 9th Bio', 'school_section' => 'Red'
        ],
        [
            'id' => 1462, 'admission_no' => 'IGS1462', 'first_name' => 'RIDA', 'last_name' => 'FATIMA',
            'gender' => 'Female', 'date_of_birth' => '2009-01-02', 'cnic_bform' => '35202-7001520-8',
            'enrollment_date' => '2026-04-03', 'class_id' => $classId, 'status' => 'Active',
            'guardian_name' => 'MUHAMMAD YOUSAF', 'guardian_cnic' => '35202-8338714-5', 'guardian_phone' => '03004257915',
            'address' => 'THOKAR NIAZ BAIG LHR', 'student_mobile' => null, 'father_name' => 'MUHAMMAD YOUSAF',
            'father_cnic' => '35202-8338714-5', 'father_mobile' => '03004257915', 'family_group' => '1365',
            'academic_type' => 'School', 'school_class' => 'Pre 9th Bio', 'school_section' => 'Red'
        ],
        [
            'id' => 1504, 'admission_no' => 'IGS1504', 'first_name' => 'AYESHA', 'last_name' => 'BILAL',
            'gender' => 'Female', 'date_of_birth' => '2012-12-27', 'cnic_bform' => '36304-1025289-8',
            'enrollment_date' => '2026-04-11', 'class_id' => $classId, 'status' => 'Active',
            'guardian_name' => 'MUHAMMAD BILAL', 'guardian_cnic' => '36304-0335196-1', 'guardian_phone' => '03184788442',
            'address' => 'THOKAR NIAZ BAIG LHR', 'student_mobile' => null, 'father_name' => 'MUHAMMAD BILAL',
            'father_cnic' => '36304-0335196-1', 'father_mobile' => '03184788442', 'family_group' => '1392',
            'academic_type' => 'School', 'school_class' => 'Pre 9th Bio', 'school_section' => 'Red'
        ]
    ];

    $countProcessed = 0;
    $countInserted = 0;
    $countUpdated = 0;

    foreach ($studentsData as $st) {
        $countProcessed++;
        echo "Processing [{$countProcessed}/21]: Student ID {$st['id']} - {$st['first_name']} {$st['last_name']} ({$st['admission_no']})...\n";

        // Check if student exists by admission_no or (first_name & father_name) or id
        $stmtCheck = $db->prepare("SELECT id FROM students WHERE admission_no = ? OR (first_name = ? AND guardian_name = ?) OR id = ?");
        $stmtCheck->execute([$st['admission_no'], $st['first_name'], $st['guardian_name'], $st['id']]);
        $existingId = $stmtCheck->fetchColumn();

        if ($existingId) {
            echo "  Updating existing student record (ID: {$existingId})...\n";
            $stmtUpd = $db->prepare("
                UPDATE students SET 
                    admission_no = COALESCE(NULLIF(admission_no, ''), :adm),
                    first_name = :fn,
                    last_name = :ln,
                    gender = :gen,
                    date_of_birth = :dob,
                    cnic_bform = COALESCE(NULLIF(cnic_bform, ''), :cnic),
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
            $countUpdated++;
        } else {
            echo "  Inserting new student record...\n";
            // Check if ID is available
            $stmtIdCheck = $db->prepare("SELECT COUNT(*) FROM students WHERE id = ?");
            $stmtIdCheck->execute([$st['id']]);
            $idTaken = $stmtIdCheck->fetchColumn() > 0;

            if (!$idTaken) {
                $stmtIns = $db->prepare("
                    INSERT INTO students (
                        id, admission_no, roll_no, first_name, last_name, gender, date_of_birth, cnic_bform,
                        enrollment_date, class_id, status, guardian_name, guardian_cnic, guardian_phone, address,
                        academic_type, school_class, school_section
                    ) VALUES (
                        :id, :adm, :roll, :fn, :ln, :gen, :dob, :cnic,
                        :enr, :cid, :stat, :gname, :gcnic, :gphone, :addr,
                        :academic_type, :school_class, :school_section
                    )
                ");
                $stmtIns->execute([
                    'id' => $st['id'],
                    'adm' => $st['admission_no'],
                    'roll' => '0',
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
                $studentDbId = $st['id'];
            } else {
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
                    'roll' => '0',
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
            $countInserted++;
        }

        // Insert or update student_registration_details table
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
                admission_date = VALUES(admission_date),
                student_mobile = COALESCE(NULLIF(VALUES(student_mobile), ''), student_mobile),
                father_name = VALUES(father_name),
                father_cnic = VALUES(father_cnic),
                father_mobile = VALUES(father_mobile),
                guardian_relationship = VALUES(guardian_relationship),
                guardian_cnic = VALUES(guardian_cnic),
                guardian_address = VALUES(guardian_address),
                current_address = VALUES(current_address),
                permanent_address = VALUES(permanent_address),
                academic_type = VALUES(academic_type),
                school_class = VALUES(school_class),
                school_section = VALUES(school_section)
        ");
        $stmtDet->execute([
            'sid' => $studentDbId,
            'roll' => '0',
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
    }

    echo "\n=== Import Complete! Total Processed: {$countProcessed}, Inserted: {$countInserted}, Updated: {$countUpdated} ===\n";

} catch (Exception $e) {
    echo "Import Error: " . $e->getMessage() . "\n";
}
