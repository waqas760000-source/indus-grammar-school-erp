<?php
/**
 * Indus Grammar School ERP - Automated Communication Dispatcher & Cron Script
 * Version 4.0.0
 */

// If triggered from browser, authenticate as admin, otherwise CLI runs natively
if (php_sapi_name() !== 'cli') {
    require_once __DIR__ . '/../../config/app.php';
    AuthMiddleware::requirePermission('communication_send');
} else {
    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../../config/database.php';
}

require_once __DIR__ . '/../../models/CommHistory.php';

$db = Database::getConnection();
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$reportsRun = [];

try {
    // 1. Attendance Alerts (Students absent today)
    $absentStudents = $db->query("
        SELECT st.id, st.first_name, st.last_name, st.guardian_phone, st.guardian_email, a.date
        FROM attendance a
        JOIN students st ON a.student_id = st.id
        WHERE a.date = '$today' AND a.status = 'Absent'
    ")->fetchAll(PDO::FETCH_ASSOC);

    $attCount = 0;
    foreach ($absentStudents as $st) {
        $msg = "Attendance Alert: Dear Parent, your child " . $st['first_name'] . " " . $st['last_name'] . " was marked Absent from school today (" . date('d-M-Y', strtotime($st['date'])) . "). Please contact the coordinator.";
        
        // Log SMS
        if ($st['guardian_phone']) {
            CommHistory::logSms([
                'recipient_type'  => 'Single Student',
                'recipient_count' => 1,
                'recipients_list' => [$st['guardian_phone'] => $st['first_name'] . ' ' . $st['last_name']],
                'message'         => $msg,
                'scheduled_time'  => null,
                'status'          => 'Sent'
            ]);
            $attCount++;
        }
    }
    $reportsRun[] = "Sent $attCount student absence SMS alerts.";

    // 2. Pending Fees Reminders
    $defaulters = $db->query("
        SELECT st.id, st.first_name, st.last_name, st.guardian_phone, st.guardian_email,
               SUM(fl.total_payable - fl.paid_amount) as outstanding
        FROM fee_ledger fl
        JOIN students st ON fl.student_id = st.id
        WHERE fl.status IN ('Pending', 'Partial') AND fl.due_date <= '$today'
        GROUP BY st.id
        HAVING outstanding > 0
    ")->fetchAll(PDO::FETCH_ASSOC);

    $feeCount = 0;
    foreach ($defaulters as $def) {
        $msg = "Fee Reminder: Dear Parent, your child " . $def['first_name'] . " " . $def['last_name'] . " has outstanding school fees of Rs. " . number_format($def['outstanding'], 0) . ". Please clear these pending dues immediately. Thank you.";
        
        if ($def['guardian_phone']) {
            CommHistory::logSms([
                'recipient_type'  => 'Single Student',
                'recipient_count' => 1,
                'recipients_list' => [$def['guardian_phone'] => $def['first_name'] . ' ' . $def['last_name']],
                'message'         => $msg,
                'scheduled_time'  => null,
                'status'          => 'Sent'
            ]);
            $feeCount++;
        }
    }
    $reportsRun[] = "Dispatched $feeCount pending fee reminders.";

    // 3. Exam Schedule alerts (Exams scheduled for tomorrow)
    $tomorrowExams = $db->query("
        SELECT es.*, et.exam_name, s.subject_name, c.class_name, c.section
        FROM exam_schedule es
        JOIN exam_types et ON es.exam_type_id = et.id
        JOIN subjects s ON es.subject_id = s.id
        JOIN classes c ON s.class_id = c.id
        WHERE es.exam_date = '$tomorrow'
    ")->fetchAll(PDO::FETCH_ASSOC);

    $examCount = 0;
    foreach ($tomorrowExams as $ex) {
        // Find all active students in this class
        $studentsInClass = $db->query("SELECT guardian_phone, first_name, last_name FROM students WHERE class_id = {$ex['class_id']} AND status = 'Active'")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($studentsInClass as $st) {
            $msg = "Exam Notification: Dear Parents, please note that the " . $ex['exam_name'] . " for subject: " . $ex['subject_name'] . " is scheduled for tomorrow (" . date('d-M-Y', strtotime($tomorrow)) . ") at " . date('h:i A', strtotime($ex['start_time'])) . ". Good luck!";
            
            if ($st['guardian_phone']) {
                CommHistory::logSms([
                    'recipient_type'  => 'Single Student',
                    'recipient_count' => 1,
                    'recipients_list' => [$st['guardian_phone'] => $st['first_name'] . ' ' . $st['last_name']],
                    'message'         => $msg,
                    'scheduled_time'  => null,
                    'status'          => 'Sent'
                ]);
                $examCount++;
            }
        }
    }
    $reportsRun[] = "Sent $examCount exam schedule student alerts.";

    // 4. Birthday Wishes (Optional)
    $birthdayBoys = $db->query("
        SELECT first_name, last_name, guardian_phone, guardian_email 
        FROM students 
        WHERE DATE_FORMAT(date_of_birth, '%m-%d') = DATE_FORMAT('$today', '%m-%d') AND status = 'Active'
    ")->fetchAll(PDO::FETCH_ASSOC);

    $bdayCount = 0;
    foreach ($birthdayBoys as $b) {
        $msg = "Birthday Wishes: Indus Grammar School wishes your child " . $b['first_name'] . " " . $b['last_name'] . " a very Happy Birthday! May they have a bright, successful future ahead.";
        if ($b['guardian_phone']) {
            CommHistory::logSms([
                'recipient_type'  => 'Single Student',
                'recipient_count' => 1,
                'recipients_list' => [$b['guardian_phone'] => $b['first_name'] . ' ' . $b['last_name']],
                'message'         => $msg,
                'scheduled_time'  => null,
                'status'          => 'Sent'
            ]);
            $bdayCount++;
        }
    }
    $reportsRun[] = "Sent $bdayCount birthday greeting SMS wishes.";

    // Process scheduled messages whose time is reached
    $schedSms = $db->query("SELECT * FROM sms_history WHERE status = 'Scheduled' AND scheduled_time <= NOW()")->fetchAll(PDO::FETCH_ASSOC);
    $smsProcessed = 0;
    foreach ($schedSms as $s) {
        $db->exec("UPDATE sms_history SET status = 'Sent' WHERE id = {$s['id']}");
        $smsProcessed++;
    }

    $schedEmails = $db->query("SELECT * FROM email_history WHERE status = 'Scheduled' AND scheduled_time <= NOW()")->fetchAll(PDO::FETCH_ASSOC);
    $emailProcessed = 0;
    foreach ($schedEmails as $e) {
        $db->exec("UPDATE email_history SET status = 'Sent' WHERE id = {$e['id']}");
        $emailProcessed++;
    }

    $reportsRun[] = "Processed $smsProcessed scheduled SMS and $emailProcessed scheduled Emails.";

    if (php_sapi_name() !== 'cli') {
        echo json_encode(['status' => 'success', 'message' => implode("\n", $reportsRun)]);
    } else {
        echo "Cron executed successfully:\n" . implode("\n", $reportsRun) . "\n";
    }

} catch (Exception $ex) {
    if (php_sapi_name() !== 'cli') {
        echo json_encode(['status' => 'error', 'message' => 'Automation error: ' . $ex->getMessage()]);
    } else {
        echo "Error: " . $ex->getMessage() . "\n";
    }
}
