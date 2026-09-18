<?php
/**
 * Indus Grammar School ERP - WhatsApp Communication Controller
 * Version 4.0.0
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../models/WhatsAppHistory.php';
require_once __DIR__ . '/../models/WhatsAppTemplate.php';
require_once __DIR__ . '/../models/WhatsAppSetting.php';
require_once __DIR__ . '/../services/WhatsAppService.php';

AuthMiddleware::requirePermission('communication_send');

header('Content-Type: application/json');

$action = sanitize($_POST['action'] ?? $_GET['action'] ?? '');

try {
    $db = Database::getConnection();
    $waService = new WhatsAppService();

    switch ($action) {

        case 'search_student':
            $q = trim(sanitize($_REQUEST['query'] ?? ''));
            if (empty($q)) {
                echo json_encode(['status' => 'error', 'message' => 'Please enter a Student ID or CNIC / B-Form to search.']);
                exit;
            }

            // Perform search across students and registration details
            $sql = "
                SELECT s.id, s.admission_no, s.first_name, s.last_name, s.gender, s.guardian_name, s.guardian_phone,
                       s.academic_type, c.class_name, c.section,
                       srd.father_name, srd.father_mobile, srd.mother_name, srd.mother_mobile, 
                       srd.student_mobile, srd.emergency_contact, srd.cnic_no, srd.birth_cert_no, srd.doc_student_photo
                FROM students s
                LEFT JOIN student_registration_details srd ON s.id = srd.student_id
                LEFT JOIN classes c ON s.class_id = c.id
                WHERE s.admission_no = :q
                   OR s.id = :qid
                   OR srd.cnic_no = :q
                   OR srd.birth_cert_no = :q
                   OR CONCAT(s.first_name, ' ', s.last_name) LIKE :like_q
                ORDER BY s.id DESC LIMIT 1
            ";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                'q'      => $q,
                'qid'    => is_numeric($q) ? (int)$q : 0,
                'like_q' => '%' . $q . '%'
            ]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                echo json_encode(['status' => 'error', 'message' => 'No active student found matching search criteria.']);
                exit;
            }

            $studentId = (int)$student['id'];

            // 1. Calculate live outstanding fee & due date
            $feeStmt = $db->prepare("
                SELECT SUM(net_amount) as total_net,
                       MIN(due_date) as earliest_due
                FROM fee_challans 
                WHERE student_id = :sid AND status IN ('Unpaid', 'Partial', 'Overdue')
            ");
            $feeStmt->execute(['sid' => $studentId]);
            $feeData = $feeStmt->fetch(PDO::FETCH_ASSOC);

            $paidStmt = $db->prepare("
                SELECT SUM(fc.amount_paid) as total_paid
                FROM fee_collections fc
                JOIN fee_challans ch ON fc.challan_id = ch.id
                WHERE ch.student_id = :sid AND ch.status IN ('Unpaid', 'Partial', 'Overdue')
            ");
            $paidStmt->execute(['sid' => $studentId]);
            $paidTotal = (float)($paidStmt->fetchColumn() ?: 0);

            $netTotal = (float)($feeData['total_net'] ?? 0);
            $outstanding = max(0, $netTotal - $paidTotal);
            $dueDate = !empty($feeData['earliest_due']) ? date('d-M-Y', strtotime($feeData['earliest_due'])) : 'N/A';

            // 2. Fetch today's attendance status
            $today = date('Y-m-d');
            $attStmt = $db->prepare("SELECT status FROM attendance WHERE student_id = :sid AND date = :today");
            $attStmt->execute(['sid' => $studentId, 'today' => $today]);
            $todayAtt = $attStmt->fetchColumn() ?: 'Not Marked';

            // Calculate overall attendance percentage
            $attCalcStmt = $db->prepare("
                SELECT COUNT(*) as total_days,
                       SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_days
                FROM attendance WHERE student_id = :sid
            ");
            $attCalcStmt->execute(['sid' => $studentId]);
            $attCalc = $attCalcStmt->fetch(PDO::FETCH_ASSOC);
            $totalDays = (int)($attCalc['total_days'] ?? 0);
            $presentDays = (int)($attCalc['present_days'] ?? 0);
            $attPercentage = ($totalDays > 0) ? round(($presentDays / $totalDays) * 100, 1) . '%' : '100%';

            // Determine best WhatsApp phone number
            $phoneCandidate = $waService->normalizePakistaniPhone(
                $student['father_mobile'] ?: ($student['guardian_phone'] ?: ($student['mother_mobile'] ?: ($student['student_mobile'] ?: $student['emergency_contact'])))
            );

            // Fetch school settings for school name
            $schoolName = 'Indus Grammar School';
            $schStmt = $db->query("SELECT school_name FROM school_settings LIMIT 1");
            if ($schRow = $schStmt->fetch()) {
                if (!empty($schRow['school_name'])) $schoolName = $schRow['school_name'];
            }

            echo json_encode([
                'status' => 'success',
                'data'   => [
                    'student_id'       => $student['admission_no'],
                    'db_id'            => $student['id'],
                    'student_name'     => $student['first_name'] . ' ' . $student['last_name'],
                    'father_name'      => $student['father_name'] ?: $student['guardian_name'],
                    'guardian_name'    => $student['guardian_name'] ?: $student['father_name'],
                    'phone'            => $phoneCandidate,
                    'class_name'       => $student['class_name'] ?: 'Unassigned',
                    'section'          => $student['section'] ?: 'A',
                    'academic_type'    => $student['academic_type'] ?: 'School',
                    'photo'            => $student['doc_student_photo'] ?: '',
                    'outstanding_fee'  => number_format($outstanding, 0),
                    'due_date'         => $dueDate,
                    'attendance'       => $todayAtt . " (" . $attPercentage . ")",
                    'today_attendance' => $todayAtt,
                    'att_percentage'   => $attPercentage,
                    'school_name'      => $schoolName
                ]
            ]);
            break;

        case 'resolve_bulk_recipients':
            $recType      = sanitize($_REQUEST['recipient_type'] ?? 'Whole Class');
            $filterClass  = (int)($_REQUEST['filter_class'] ?? 0);
            $filterAtype  = sanitize($_REQUEST['filter_atype'] ?? '');
            $filterStatus = sanitize($_REQUEST['filter_status'] ?? 'Active');
            $rawMessage   = $_REQUEST['message'] ?? '';
            $templateName = sanitize($_REQUEST['template_name'] ?? '');

            // Fetch school name
            $schoolName = 'Indus Grammar School';
            $schStmt = $db->query("SELECT school_name FROM school_settings LIMIT 1");
            if ($schRow = $schStmt->fetch()) {
                if (!empty($schRow['school_name'])) $schoolName = $schRow['school_name'];
            }

            $recipients = [];

            if ($recType === 'Teachers' || $recType === 'Staff') {
                $sql = "SELECT id, employee_no as reg_no, first_name, last_name, designation, phone FROM staff WHERE status = :status";
                $params = ['status' => $filterStatus];
                if ($recType === 'Teachers') {
                    $sql .= " AND (designation LIKE '%Teacher%' OR department = 'Academic')";
                }
                $sql .= " ORDER BY first_name ASC";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $staffList = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($staffList as $st) {
                    $normPhone = $waService->normalizePakistaniPhone($st['phone']);
                    if (!empty($normPhone) && strlen($normPhone) >= 10) {
                        $vars = [
                            'student_name'    => $st['first_name'] . ' ' . $st['last_name'],
                            'father_name'     => $st['designation'],
                            'class_name'      => 'Staff',
                            'section'         => '',
                            'student_id'      => $st['reg_no'],
                            'outstanding_fee' => '0',
                            'due_date'        => 'N/A',
                            'attendance'      => 'N/A',
                            'school_name'     => $schoolName
                        ];
                        $renderedMsg = $waService->renderTemplate($rawMessage, $vars);
                        $clickUrl    = $waService->generateClickToChatUrl($normPhone, $renderedMsg);

                        $recipients[] = [
                            'id'           => $st['id'],
                            'name'         => $st['first_name'] . ' ' . $st['last_name'],
                            'phone'        => $normPhone,
                            'info'         => $st['reg_no'] . ' (' . $st['designation'] . ')',
                            'rendered_msg' => $renderedMsg,
                            'click_url'    => $clickUrl
                        ];
                    }
                }
            } else {
                // Student / Parent queries
                $phoneSelect = ($recType === 'Parents')
                    ? "COALESCE(NULLIF(TRIM(srd.father_mobile), ''), NULLIF(TRIM(srd.mother_mobile), ''), NULLIF(TRIM(s.guardian_phone), ''), NULLIF(TRIM(srd.student_mobile), ''))"
                    : "COALESCE(NULLIF(TRIM(srd.student_mobile), ''), NULLIF(TRIM(s.guardian_phone), ''), NULLIF(TRIM(srd.father_mobile), ''), NULLIF(TRIM(srd.mother_mobile), ''))";

                $sql = "
                    SELECT s.id, s.admission_no, s.first_name, s.last_name, c.class_name, c.section,
                           srd.father_name, s.guardian_name,
                           $phoneSelect as phone
                    FROM students s
                    LEFT JOIN student_registration_details srd ON s.id = srd.student_id
                    LEFT JOIN classes c ON s.class_id = c.id
                    WHERE s.status = :status
                ";
                $params = ['status' => $filterStatus];

                if ($recType === 'Whole Class' || $filterClass > 0) {
                    if ($filterClass > 0) {
                        $sql .= " AND s.class_id = :cid";
                        $params['cid'] = $filterClass;
                    }
                }

                if ($recType === 'School Students' || $recType === 'School') {
                    $sql .= " AND s.academic_type = 'School'";
                } elseif ($recType === 'Academy Students' || $recType === 'Academy') {
                    $sql .= " AND s.academic_type = 'Academy'";
                } elseif (!empty($filterAtype)) {
                    $sql .= " AND s.academic_type = :atype";
                    $params['atype'] = $filterAtype;
                }

                $sql .= " ORDER BY s.first_name ASC";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $stdList = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($stdList as $st) {
                    $normPhone = $waService->normalizePakistaniPhone($st['phone'] ?? '');
                    if (!empty($normPhone) && strlen($normPhone) >= 10) {
                        $vars = [
                            'student_name'    => $st['first_name'] . ' ' . $st['last_name'],
                            'father_name'     => $st['father_name'] ?: $st['guardian_name'],
                            'guardian_name'   => $st['guardian_name'] ?: $st['father_name'],
                            'class_name'      => $st['class_name'] ?: 'N/A',
                            'section'         => $st['section'] ?: 'A',
                            'student_id'      => $st['admission_no'],
                            'outstanding_fee' => '0',
                            'due_date'        => 'N/A',
                            'attendance'      => 'Present',
                            'school_name'     => $schoolName
                        ];

                        $renderedMsg = $waService->renderTemplate($rawMessage, $vars);
                        $clickUrl    = $waService->generateClickToChatUrl($normPhone, $renderedMsg);

                        $recipients[] = [
                            'id'           => $st['id'],
                            'name'         => ($recType === 'Parents') ? ($st['father_name'] ?: $st['guardian_name']) : ($st['first_name'] . ' ' . $st['last_name']),
                            'phone'        => $normPhone,
                            'info'         => $st['first_name'] . ' ' . $st['last_name'] . ' (' . $st['admission_no'] . ')',
                            'rendered_msg' => $renderedMsg,
                            'click_url'    => $clickUrl
                        ];
                    }
                }
            }

            echo json_encode([
                'status' => 'success',
                'count'  => count($recipients),
                'data'   => $recipients
            ]);
            break;

        case 'dispatch_message':
            if (!validateCsrf($_POST['csrf_token'] ?? '')) {
                echo json_encode(['status' => 'error', 'message' => 'CSRF verification failed.']);
                exit;
            }

            $recipientName = sanitize($_POST['recipient_name'] ?? 'Recipient');
            $recipientType = sanitize($_POST['recipient_type'] ?? 'Single Student');
            $phone         = sanitize($_POST['phone'] ?? '');
            $message       = $_POST['message'] ?? '';
            $templateName  = sanitize($_POST['template_name'] ?? '');

            if (empty($phone)) {
                echo json_encode(['status' => 'error', 'message' => 'No valid WhatsApp number available.']);
                exit;
            }
            if (empty($message)) {
                echo json_encode(['status' => 'error', 'message' => 'Message body text cannot be empty.']);
                exit;
            }

            $result = $waService->dispatch([
                'name'  => $recipientName,
                'phone' => $phone,
                'type'  => $recipientType
            ], $message, $templateName);

            if ($result['status'] === 'success') {
                auditLog('WhatsApp Dispatched', "Recipient: $recipientName ($phone) | Msg: " . substr($message, 0, 50));
            }

            echo json_encode($result);
            break;

        case 'save_template':
            if (!validateCsrf($_POST['csrf_token'] ?? '')) {
                echo json_encode(['status' => 'error', 'message' => 'CSRF verification failed.']);
                exit;
            }

            $success = WhatsAppTemplate::save($_POST);
            if ($success) {
                echo json_encode(['status' => 'success', 'message' => 'WhatsApp template saved successfully.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to save template.']);
            }
            break;

        case 'delete_template':
            $id = (int)($_POST['id'] ?? 0);
            if (WhatsAppTemplate::delete($id)) {
                echo json_encode(['status' => 'success', 'message' => 'Template deleted successfully.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to delete template.']);
            }
            break;

        case 'save_settings':
            if (!validateCsrf($_POST['csrf_token'] ?? '')) {
                echo json_encode(['status' => 'error', 'message' => 'CSRF verification failed.']);
                exit;
            }

            $success = WhatsAppSetting::save($_POST);
            if ($success) {
                auditLog('WhatsApp Settings Saved', 'Mode: ' . sanitize($_POST['mode'] ?? 'click_to_chat'));
                echo json_encode(['status' => 'success', 'message' => 'WhatsApp settings updated successfully.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to save WhatsApp settings.']);
            }
            break;

        case 'delete_history':
            $id = (int)($_POST['id'] ?? 0);
            if (WhatsAppHistory::delete($id)) {
                echo json_encode(['status' => 'success', 'message' => 'Log entry deleted.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to delete log entry.']);
            }
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Unsupported WhatsApp action endpoint requested.']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'System exception occurred: ' . $e->getMessage()]);
}
