<?php
/**
 * Indus Grammar School ERP - Staff Attendance AJAX Controller
 * Version 4.0.0
 */

require_once __DIR__ . '/../config/app.php';

// Authentication and Authorization
if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized.'], 401);
}

$db = Database::getConnection();

// Restrict access to Super Admin, School Admin, and Accountant (or users with attendance_mark permission)
if (!hasPermission('attendance_mark')) {
    jsonResponse(['success' => false, 'message' => 'Access denied. You do not have permissions to manage staff attendance.'], 403);
}

// Handle Secure GET Request for CSV/Excel download exports if needed
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
}

// POST and CSRF Verification
if (!validateCsrf($_POST['csrf_token'] ?? '')) {
    jsonResponse(['success' => false, 'message' => 'Security token expired. Please refresh the page.'], 403);
}

$action = sanitize($_POST['action'] ?? '');
if (empty($action)) {
    jsonResponse(['success' => false, 'message' => 'No action specified.'], 400);
}

switch ($action) {

    // ── LOAD EMPLOYEES ROSTER FOR MARKING ──────────────────────
    case 'get_staff_mark_list':
        $date       = sanitize($_POST['date'] ?? date('Y-m-d'));
        $dept       = sanitize($_POST['department'] ?? '');
        $desig      = sanitize($_POST['designation'] ?? '');
        $staffType  = sanitize($_POST['staff_type'] ?? '');
        $status     = sanitize($_POST['status'] ?? 'Active');

        $where = " WHERE 1=1";
        $params = ['date' => $date];

        if ($dept !== '') {
            $where .= " AND s.department = :dept";
            $params['dept'] = $dept;
        }
        if ($desig !== '') {
            $where .= " AND s.designation = :desig";
            $params['desig'] = $desig;
        }
        if ($status !== '') {
            $where .= " AND s.status = :status";
            $params['status'] = $status;
        }
        if ($staffType !== '') {
            if ($staffType === 'Teaching Staff') {
                $where .= " AND s.department = 'Academic'";
            } else {
                $where .= " AND s.department != 'Academic'";
            }
        }

        try {
            $stmt = $db->prepare("
                SELECT s.id, s.employee_no, s.first_name, s.last_name, s.department, s.designation, s.status as employee_status,
                       sa.status as current_status, sa.check_in_time, sa.check_out_time, sa.remarks
                FROM staff s
                LEFT JOIN staff_attendance sa ON sa.staff_id = s.id AND sa.date = :date
                $where
                ORDER BY s.employee_no ASC
            ");
            $stmt->execute($params);
            $staffList = $stmt->fetchAll(PDO::FETCH_ASSOC);
            jsonResponse(['success' => true, 'staff' => $staffList]);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;


    // ── SAVE STAFF ATTENDANCE IN BATCHES ───────────────────────
    case 'save_staff_attendance_bulk':
        $date    = sanitize($_POST['date'] ?? date('Y-m-d'));
        $records = $_POST['records'] ?? [];

        if (empty($records) || !is_array($records)) {
            jsonResponse(['success' => false, 'message' => 'No attendance records submitted.'], 400);
        }

        try {
            $db->beginTransaction();

            $insStmt = $db->prepare("
                INSERT INTO staff_attendance (staff_id, date, status, check_in_time, check_out_time, remarks)
                VALUES (:sid, :date, :status, :cin, :cout, :remarks)
                ON DUPLICATE KEY UPDATE 
                    status = VALUES(status), 
                    check_in_time = VALUES(check_in_time), 
                    check_out_time = VALUES(check_out_time), 
                    remarks = VALUES(remarks)
            ");

            $detailStmt = $db->prepare("
                INSERT INTO staff_attendance_details (attendance_id, log_type, timestamp, ip_address)
                VALUES (:aid, :type, NOW(), :ip)
            ");

            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $successCount = 0;

            foreach ($records as $r) {
                $staffId  = (int)($r['staff_id'] ?? 0);
                $status   = sanitize($r['status'] ?? 'Present');
                $checkIn  = sanitize($r['check_in'] ?? '');
                $checkOut = sanitize($r['check_out'] ?? '');
                $remarks  = sanitize($r['remarks'] ?? '');

                if ($staffId <= 0) continue;

                // Execute daily mark insert/update
                $insStmt->execute([
                    'sid'     => $staffId,
                    'date'    => $date,
                    'status'  => $status,
                    'cin'     => $checkIn ?: null,
                    'cout'    => $checkOut ?: null,
                    'remarks' => $remarks ?: null
                ]);

                // Log audit check-in details if checked in/out
                $attendanceId = (int)$db->lastInsertId();
                if ($attendanceId <= 0) {
                    // Fetch existing attendance ID for duplicate key updates
                    $getId = $db->prepare("SELECT id FROM staff_attendance WHERE staff_id = ? AND date = ?");
                    $getId->execute([$staffId, $date]);
                    $attendanceId = (int)$getId->fetchColumn();
                }

                if ($attendanceId > 0) {
                    if (!empty($checkIn)) {
                        $detailStmt->execute(['aid' => $attendanceId, 'type' => 'Check In', 'ip' => $ipAddress]);
                    }
                    if (!empty($checkOut)) {
                        $detailStmt->execute(['aid' => $attendanceId, 'type' => 'Check Out', 'ip' => $ipAddress]);
                    }
                }
                $successCount++;
            }

            $db->commit();
            auditLog('Staff Attendance Bulk Saved', "Saved daily staff attendance roster for $date ($successCount records)");
            jsonResponse(['success' => true, 'message' => "Successfully processed $successCount staff attendance records."]);
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            jsonResponse(['success' => false, 'message' => 'Failed to save attendance roster: ' . $e->getMessage()]);
        }
        break;


    // ── LEAVE MANAGEMENT APPLICATIONS CRUD ─────────────────────
    case 'apply_staff_leave':
        $staffId   = (int)($_POST['staff_id'] ?? 0);
        $leaveType = sanitize($_POST['leave_type'] ?? 'Casual Leave');
        $leaveFrom = sanitize($_POST['leave_from'] ?? '');
        $leaveTo   = sanitize($_POST['leave_to'] ?? '');
        $reason    = sanitize($_POST['reason'] ?? '');
        $remarks   = sanitize($_POST['remarks'] ?? '');

        if ($staffId <= 0 || empty($leaveFrom) || empty($leaveTo) || empty($reason)) {
            jsonResponse(['success' => false, 'message' => 'All mandatory leave fields are required.'], 400);
        }

        // Calculate total days
        $days = (int)(ceil((strtotime($leaveTo) - strtotime($leaveFrom)) / 86400) + 1);
        if ($days <= 0) {
            jsonResponse(['success' => false, 'message' => 'End date cannot be prior to start date.'], 400);
        }

        // Handle attachment file upload
        $filepath = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = DIR_ROOT . '/storage/leaves/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
            $filename = 'leave_' . time() . '_' . rand(100, 999) . '.' . $ext;
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $filename)) {
                $filepath = 'storage/leaves/' . $filename;
            }
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO staff_leave (staff_id, leave_type, leave_from, leave_to, total_days, reason, attachment, status, remarks)
                VALUES (:sid, :type, :from, :to, :days, :reason, :attach, 'Pending', :remarks)
            ");
            $ok = $stmt->execute([
                'sid'    => $staffId,
                'type'   => $leaveType,
                'from'   => $leaveFrom,
                'to'     => $leaveTo,
                'days'   => $days,
                'reason' => $reason,
                'attach' => $filepath,
                'remarks'=> $remarks
            ]);

            if ($ok) {
                auditLog('Staff Leave Applied', "Logged leave request for Staff ID: $staffId ($days days)");
                jsonResponse(['success' => true, 'message' => 'Leave application submitted successfully.']);
            }
            jsonResponse(['success' => false, 'message' => 'Failed to log leave request.']);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'update_leave_status':
        $id        = (int)($_POST['id'] ?? 0);
        $newStatus = sanitize($_POST['new_status'] ?? '');
        $remarks   = sanitize($_POST['remarks'] ?? '');

        if ($id <= 0 || !in_array($newStatus, ['Approved', 'Rejected'])) {
            jsonResponse(['success' => false, 'message' => 'Invalid parameters.'], 400);
        }

        try {
            $db->beginTransaction();

            $stmt = $db->prepare("
                UPDATE staff_leave 
                SET status = :status, approved_by = :by, remarks = :remarks 
                WHERE id = :id
            ");
            $stmt->execute([
                'status'  => $newStatus,
                'by'      => $_SESSION['user_id'] ?? null,
                'remarks' => $remarks,
                'id'      => $id
            ]);

            // If Approved, automatically update staff daily attendance records
            if ($newStatus === 'Approved') {
                $leave = $db->query("SELECT * FROM staff_leave WHERE id = $id")->fetch();
                if ($leave) {
                    $start = new DateTime($leave['leave_from']);
                    $end   = new DateTime($leave['leave_to']);
                    $end->modify('+1 day'); // include end date in loop

                    $interval = new DateInterval('P1D');
                    $period   = new DatePeriod($start, $interval, $end);

                    $upsert = $db->prepare("
                        INSERT INTO staff_attendance (staff_id, date, status, check_in_time, check_out_time, remarks)
                        VALUES (:sid, :date, 'Leave', NULL, NULL, :remarks)
                        ON DUPLICATE KEY UPDATE 
                            status = 'Leave', 
                            check_in_time = NULL, 
                            check_out_time = NULL, 
                            remarks = VALUES(remarks)
                    ");

                    foreach ($period as $date) {
                        $upsert->execute([
                            'sid'     => $leave['staff_id'],
                            'date'    => $date->format('Y-m-d'),
                            'remarks' => "Approved Leave: " . $leave['leave_type']
                        ]);
                    }
                }
            }

            $db->commit();
            auditLog("Staff Leave $newStatus", "Leave application ID #$id was $newStatus");
            jsonResponse(['success' => true, 'message' => "Leave application status updated to $newStatus."]);
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'delete_leave':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid ID.'], 400);
        }

        try {
            $stmt = $db->prepare("DELETE FROM staff_leave WHERE id = ?");
            $ok = $stmt->execute([$id]);
            if ($ok) {
                auditLog('Staff Leave Deleted', "Removed leave application record #$id");
                jsonResponse(['success' => true, 'message' => 'Leave application successfully removed.']);
            }
            jsonResponse(['success' => false, 'message' => 'Failed to delete leave application.']);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;


    // ── ATTENDANCE SHIFT SETTINGS ──────────────────────────────
    case 'save_attendance_settings':
        $startTime = sanitize($_POST['office_start_time'] ?? '08:00:00');
        $endTime   = sanitize($_POST['office_end_time'] ?? '14:00:00');
        $lateTime  = sanitize($_POST['late_arrival_time'] ?? '08:15:00');
        $halfTime  = sanitize($_POST['half_day_time'] ?? '11:00:00');
        $workDays  = $_POST['working_days'] ?? [];
        $weekends  = $_POST['weekend_days'] ?? [];
        $lockTime  = sanitize($_POST['attendance_lock_time'] ?? '23:59:59');
        $allowEdit = isset($_POST['allow_attendance_editing']) ? 1 : 0;

        $workDaysStr = implode(',', array_map('sanitize', $workDays));
        $weekendsStr = implode(',', array_map('sanitize', $weekends));

        try {
            $stmt = $db->prepare("
                UPDATE staff_attendance_settings 
                SET office_start_time = :start, office_end_time = :end, late_arrival_time = :late, half_day_time = :half,
                    working_days = :work, weekend_days = :week, attendance_lock_time = :lock, allow_attendance_editing = :allow
                WHERE id = 1
            ");
            $ok = $stmt->execute([
                'start' => $startTime,
                'end'   => $endTime,
                'late'  => $lateTime,
                'half'  => $halfTime,
                'work'  => $workDaysStr,
                'week'  => $weekendsStr,
                'lock'  => $lockTime,
                'allow' => $allowEdit
            ]);

            if ($ok) {
                auditLog('Attendance Settings Saved', 'Updated daily work shift and late times parameters.');
                jsonResponse(['success' => true, 'message' => 'Attendance shift configurations saved.']);
            }
            jsonResponse(['success' => false, 'message' => 'Failed to save attendance settings.']);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Action not supported.'], 404);
        break;
}
