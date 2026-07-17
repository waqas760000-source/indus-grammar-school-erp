<?php
/**
 * Indus Grammar School ERP - Communication Module Controller
 * Version 4.0.0
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../models/CommSetting.php';
require_once __DIR__ . '/../models/CommTemplate.php';
require_once __DIR__ . '/../models/Announcement.php';
require_once __DIR__ . '/../models/Circular.php';
require_once __DIR__ . '/../models/CommHistory.php';

AuthMiddleware::requirePermission('communication_send');

header('Content-Type: application/json');

$action = sanitize($_POST['action'] ?? $_GET['action'] ?? '');

try {
    $db = Database::getConnection();

    switch ($action) {

        case 'save_settings':
            if (!validateCsrf($_POST['csrf_token'] ?? '')) {
                echo json_encode(['status' => 'error', 'message' => 'CSRF verification failed.']);
                exit;
            }
            $success = CommSetting::save($_POST);
            if ($success) {
                auditLog('Comm Settings Updated', 'Saved new SMS SMTP configuration details.');
                echo json_encode(['status' => 'success', 'message' => 'Settings successfully saved!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to save communication configurations.']);
            }
            break;

        case 'test_sms':
            $phone = sanitize($_POST['test_phone'] ?? '');
            if (empty($phone)) {
                echo json_encode(['status' => 'error', 'message' => 'Test mobile phone number is required.']);
                exit;
            }
            // Simulated gateway trigger
            $settings = CommSetting::get();
            $logMsg = "Test SMS simulation dispatched successfully to $phone from senderID: " . ($settings['sms_sender_id'] ?: 'IGS-ERP');
            auditLog('SMS Test Triggered', "To: $phone");
            echo json_encode(['status' => 'success', 'message' => $logMsg]);
            break;

        case 'test_email':
            $email = sanitize($_POST['test_email'] ?? '');
            if (empty($email)) {
                echo json_encode(['status' => 'error', 'message' => 'Test email destination address is required.']);
                exit;
            }
            $settings = CommSetting::get();
            $logMsg = "Test Email simulation sent to $email via host " . ($settings['smtp_host'] ?: 'localhost');
            auditLog('Email Test Triggered', "To: $email");
            echo json_encode(['status' => 'success', 'message' => $logMsg]);
            break;

        case 'save_template':
            if (!validateCsrf($_POST['csrf_token'] ?? '')) {
                echo json_encode(['status' => 'error', 'message' => 'CSRF verification failed.']);
                exit;
            }
            $success = CommTemplate::save($_POST);
            if ($success) {
                echo json_encode(['status' => 'success', 'message' => 'Template successfully saved.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to save template.']);
            }
            break;

        case 'delete_template':
            $id = (int)($_POST['id'] ?? 0);
            if (CommTemplate::delete($id)) {
                echo json_encode(['status' => 'success', 'message' => 'Template successfully deleted.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to delete template.']);
            }
            break;

        case 'save_announcement':
            if (!validateCsrf($_POST['csrf_token'] ?? '')) {
                echo json_encode(['status' => 'error', 'message' => 'CSRF verification failed.']);
                exit;
            }
            $success = Announcement::save($_POST);
            if ($success) {
                auditLog('Announcement Saved', 'Title: ' . sanitize($_POST['title']));
                echo json_encode(['status' => 'success', 'message' => 'Announcement successfully published.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to save announcement details.']);
            }
            break;

        case 'delete_announcement':
            $id = (int)($_POST['id'] ?? 0);
            if (Announcement::delete($id)) {
                auditLog('Announcement Deleted', "ID: $id");
                echo json_encode(['status' => 'success', 'message' => 'Announcement successfully deleted.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to delete announcement.']);
            }
            break;

        case 'save_circular':
            if (!validateCsrf($_POST['csrf_token'] ?? '')) {
                echo json_encode(['status' => 'error', 'message' => 'CSRF verification failed.']);
                exit;
            }
            
            $fileUrl = $_POST['existing_attachment'] ?? '';
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $dir = __DIR__ . '/../uploads/communication/';
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }
                $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
                $newName = 'circular_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $dir . $newName)) {
                    $fileUrl = 'uploads/communication/' . $newName;
                }
            }

            $data = $_POST;
            $data['attachment_path'] = $fileUrl;
            $success = Circular::save($data);

            if ($success) {
                auditLog('Circular Saved', 'Title: ' . sanitize($_POST['title']));
                echo json_encode(['status' => 'success', 'message' => 'Circular notice successfully saved.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to save circular details.']);
            }
            break;

        case 'delete_circular':
            $id = (int)($_POST['id'] ?? 0);
            if (Circular::delete($id)) {
                auditLog('Circular Deleted', "ID: $id");
                echo json_encode(['status' => 'success', 'message' => 'Circular notice successfully deleted.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to delete circular.']);
            }
            break;

        case 'send_sms':
            if (!validateCsrf($_POST['csrf_token'] ?? '')) {
                echo json_encode(['status' => 'error', 'message' => 'CSRF verification failed.']);
                exit;
            }
            
            $recType = sanitize($_POST['recipient_type'] ?? '');
            $message = sanitize($_POST['message'] ?? '');
            $schedTime = sanitize($_POST['scheduled_time'] ?? '');
            $status = ($schedTime !== '') ? 'Scheduled' : 'Sent';

            $phones = [];
            
            // Resolve recipient phone numbers
            if ($recType === 'Custom Mobile Number') {
                $customNo = sanitize($_POST['custom_mobile'] ?? '');
                if (!empty($customNo)) {
                    $phones[$customNo] = 'Custom Recipient';
                }
            } else {
                $stIds = $_POST['student_ids'] ?? [];
                if (!empty($stIds)) {
                    $idsStr = implode(',', array_map('intval', $stIds));
                    if ($recType === 'Teachers' || $recType === 'Staff') {
                        $list = $db->query("SELECT phone, first_name, last_name FROM staff WHERE id IN ($idsStr)")->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($list as $p) {
                            if ($p['phone']) {
                                $phones[$p['phone']] = $p['first_name'] . ' ' . $p['last_name'];
                            }
                        }
                    } else {
                        // Student/Parent categories
                        $list = $db->query("SELECT guardian_phone, guardian_name, first_name, last_name FROM students WHERE id IN ($idsStr)")->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($list as $p) {
                            $phoneNum = $p['guardian_phone'];
                            $name = ($recType === 'Parents') ? $p['guardian_name'] : ($p['first_name'] . ' ' . $p['last_name']);
                            if ($phoneNum) {
                                $phones[$phoneNum] = $name;
                            }
                        }
                    }
                }
            }

            if (empty($phones)) {
                echo json_encode(['status' => 'error', 'message' => 'No active target phone numbers resolved.']);
                exit;
            }

            // Save to SMS History
            $logSuccess = CommHistory::logSms([
                'recipient_type'  => $recType,
                'recipient_count' => count($phones),
                'recipients_list' => $phones,
                'message'         => $message,
                'scheduled_time'  => $schedTime,
                'status'          => $status
            ]);

            if ($logSuccess) {
                auditLog('SMS Dispatched', "Recipients Count: " . count($phones) . " | Msg: $message");
                echo json_encode(['status' => 'success', 'message' => 'SMS processed successfully! ' . count($phones) . ' recipients queued.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to save SMS history details.']);
            }
            break;

        case 'send_email':
            if (!validateCsrf($_POST['csrf_token'] ?? '')) {
                echo json_encode(['status' => 'error', 'message' => 'CSRF verification failed.']);
                exit;
            }

            $recType = sanitize($_POST['recipient_type'] ?? '');
            $subject = sanitize($_POST['subject'] ?? '');
            $body = $_POST['body'] ?? '';
            $priority = sanitize($_POST['priority'] ?? 'Normal');
            $schedTime = sanitize($_POST['scheduled_time'] ?? '');
            $status = ($schedTime !== '') ? 'Scheduled' : 'Sent';

            $fileUrl = '';
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $dir = __DIR__ . '/../uploads/communication/';
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }
                $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
                $newName = 'attachment_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $dir . $newName)) {
                    $fileUrl = 'uploads/communication/' . $newName;
                }
            }

            $emails = [];

            // Resolve email addresses
            switch ($recType) {
                case 'Student':
                    $stId = (int)($_POST['student_id'] ?? 0);
                    // Since students don't have separate email in students table directly, we send to guardian email
                    $p = $db->query("SELECT guardian_email, first_name, last_name FROM students WHERE id = $stId")->fetch(PDO::FETCH_ASSOC);
                    if ($p && $p['guardian_email']) {
                        $emails[$p['guardian_email']] = $p['first_name'] . ' ' . $p['last_name'];
                    }
                    break;

                case 'Parent':
                    $stId = (int)($_POST['student_id'] ?? 0);
                    $p = $db->query("SELECT guardian_email, guardian_name FROM students WHERE id = $stId")->fetch(PDO::FETCH_ASSOC);
                    if ($p && $p['guardian_email']) {
                        $emails[$p['guardian_email']] = $p['guardian_name'];
                    }
                    break;

                case 'Teacher':
                    $tId = (int)($_POST['staff_id'] ?? 0);
                    $p = $db->query("SELECT email, first_name, last_name FROM staff WHERE id = $tId")->fetch(PDO::FETCH_ASSOC);
                    if ($p && $p['email']) {
                        $emails[$p['email']] = $p['first_name'] . ' ' . $p['last_name'];
                    }
                    break;

                case 'Staff':
                    $tId = (int)($_POST['staff_id'] ?? 0);
                    $p = $db->query("SELECT email, first_name, last_name FROM staff WHERE id = $tId")->fetch(PDO::FETCH_ASSOC);
                    if ($p && $p['email']) {
                        $emails[$p['email']] = $p['first_name'] . ' ' . $p['last_name'];
                    }
                    break;

                case 'Entire Class':
                    $classId = (int)($_POST['class_id'] ?? 0);
                    $list = $db->query("SELECT guardian_email, first_name, last_name FROM students WHERE class_id = $classId AND status = 'Active'")->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($list as $p) {
                        if ($p['guardian_email']) $emails[$p['guardian_email']] = $p['first_name'] . ' ' . $p['last_name'];
                    }
                    break;

                case 'Entire School':
                    $list = $db->query("SELECT guardian_email, first_name, last_name FROM students WHERE academic_type = 'School' AND status = 'Active'")->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($list as $p) {
                        if ($p['guardian_email']) $emails[$p['guardian_email']] = $p['first_name'] . ' ' . $p['last_name'];
                    }
                    break;

                case 'Custom Email':
                    $customEmail = sanitize($_POST['custom_email'] ?? '');
                    if (!empty($customEmail)) {
                        $emails[$customEmail] = 'Custom Recipient';
                    }
                    break;
            }

            if (empty($emails)) {
                echo json_encode(['status' => 'error', 'message' => 'No active target email addresses resolved.']);
                exit;
            }

            // Save to Email History
            $logSuccess = CommHistory::logEmail([
                'recipient_type'  => $recType,
                'recipient_count' => count($emails),
                'recipients_list' => $emails,
                'subject'         => $subject,
                'body'            => $body,
                'attachment_path' => $fileUrl,
                'priority'        => $priority,
                'scheduled_time'  => $schedTime,
                'status'          => $status
            ]);

            if ($logSuccess) {
                auditLog('Email Dispatched', "Recipients Count: " . count($emails) . " | Sub: $subject");
                echo json_encode(['status' => 'success', 'message' => 'Email processed successfully! ' . count($emails) . ' recipients queued.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to log email history details.']);
            }
            break;

        case 'delete_sms_log':
            $id = (int)($_POST['id'] ?? 0);
            if (CommHistory::deleteSmsLog($id)) {
                echo json_encode(['status' => 'success', 'message' => 'SMS history log deleted.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to delete SMS log.']);
            }
            break;

        case 'delete_email_log':
            $id = (int)($_POST['id'] ?? 0);
            if (CommHistory::deleteEmailLog($id)) {
                echo json_encode(['status' => 'success', 'message' => 'Email history log deleted.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to delete Email log.']);
            }
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Unsupported action endpoint requested.']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'System exception occurred: ' . $e->getMessage()]);
}
