<?php
/**
 * Indus Grammar School ERP - AttendanceService
 * Version 1.0.0
 */

class AttendanceService {

    /**
     * Bulk-mark daily attendance for a class.
     * $records is an array of ['student_id' => int, 'status' => string, 'remarks' => string|null]
     *
     * @param int $classId
     * @param string $date
     * @param array $records
     * @return array
     */
    public function markDailyAttendance(int $classId, string $date, array $records): array {
        if (empty($records)) {
            return ['status' => false, 'message' => 'No attendance records provided.'];
        }

        if (!$this->isValidDate($date)) {
            return ['status' => false, 'message' => 'Invalid date provided.'];
        }

        $class = SchoolClass::findById($classId);
        if (!$class) {
            return ['status' => false, 'message' => 'Invalid class selected.'];
        }

        $validStatuses = ['Present', 'Absent', 'Late', 'Leave'];
        $markedBy = $_SESSION['user_id'] ?? null;
        $successCount = 0;
        $errors = [];

        foreach ($records as $record) {
            $studentId = (int)($record['student_id'] ?? 0);
            $status    = $record['status'] ?? 'Present';
            $remarks   = !empty($record['remarks']) ? sanitize($record['remarks']) : null;

            if ($studentId <= 0) { $errors[] = "Invalid student ID."; continue; }
            if (!in_array($status, $validStatuses)) { $errors[] = "Invalid status for student ID $studentId."; continue; }

            $ok = Attendance::mark($studentId, $classId, $date, $status, $remarks, $markedBy);
            if ($ok) $successCount++;
        }

        if ($successCount > 0) {
            auditLog('Attendance Marked', "Marked attendance for class ID: $classId on $date ($successCount students)");
            return [
                'status'  => true,
                'message' => "Attendance saved successfully for $successCount student(s).",
                'count'   => $successCount
            ];
        }

        return ['status' => false, 'message' => 'Failed to save attendance. ' . implode(' ', $errors)];
    }

    /**
     * Mark or update staff attendance
     *
     * @param int $userId
     * @param string $date
     * @param string $status
     * @param string|null $checkIn
     * @param string|null $checkOut
     * @param string|null $remarks
     * @return array
     */
    public function markStaffAttendance(int $userId, string $date, string $status, ?string $checkIn, ?string $checkOut, ?string $remarks): array {
        if (!$this->isValidDate($date)) {
            return ['status' => false, 'message' => 'Invalid date provided.'];
        }
        $validStatuses = ['Present', 'Absent', 'Late', 'Leave'];
        if (!in_array($status, $validStatuses)) {
            return ['status' => false, 'message' => 'Invalid attendance status.'];
        }

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO staff_attendance (user_id, date, status, check_in_time, check_out_time, remarks)
                VALUES (:uid, :date, :status, :cin, :cout, :remarks)
                ON DUPLICATE KEY UPDATE
                    status = VALUES(status),
                    check_in_time = VALUES(check_in_time),
                    check_out_time = VALUES(check_out_time),
                    remarks = VALUES(remarks)
            ");
            $ok = $stmt->execute([
                'uid'     => $userId,
                'date'    => $date,
                'status'  => $status,
                'cin'     => $checkIn ?: null,
                'cout'    => $checkOut ?: null,
                'remarks' => $remarks ?: null,
            ]);
            if ($ok) {
                auditLog('Staff Attendance', "Marked staff attendance for user ID $userId: $status on $date");
                return ['status' => true, 'message' => 'Staff attendance saved.'];
            }
        } catch (PDOException $e) {
            error_log("AttendanceService::markStaffAttendance error: " . $e->getMessage());
        }
        return ['status' => false, 'message' => 'Failed to save staff attendance.'];
    }

    /**
     * Submit a leave application
     *
     * @param array $data
     * @return array
     */
    public function applyLeave(array $data): array {
        $errors = [];
        $required = ['applicant_type', 'applicant_id', 'leave_type', 'start_date', 'end_date', 'reason'];
        foreach ($required as $f) {
            if (empty($data[$f])) $errors[] = ucwords(str_replace('_', ' ', $f)) . " is required.";
        }
        if (!in_array($data['applicant_type'] ?? '', ['student', 'staff'])) {
            $errors[] = 'Invalid applicant type.';
        }
        if (!empty($data['start_date']) && !empty($data['end_date'])) {
            if (strtotime($data['end_date']) < strtotime($data['start_date'])) {
                $errors[] = 'End date cannot be before start date.';
            }
        }
        if ($errors) return ['status' => false, 'message' => implode(' ', $errors)];

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO leave_applications (applicant_type, applicant_id, leave_type, start_date, end_date, reason, status)
                VALUES (:atype, :aid, :ltype, :sdate, :edate, :reason, 'Pending')
            ");
            $ok = $stmt->execute([
                'atype'  => $data['applicant_type'],
                'aid'    => (int)$data['applicant_id'],
                'ltype'  => sanitize($data['leave_type']),
                'sdate'  => $data['start_date'],
                'edate'  => $data['end_date'],
                'reason' => sanitize($data['reason']),
            ]);
            if ($ok) {
                auditLog('Leave Applied', "Leave application submitted: {$data['applicant_type']} ID {$data['applicant_id']}");
                return ['status' => true, 'message' => 'Leave application submitted successfully.'];
            }
        } catch (PDOException $e) {
            error_log("AttendanceService::applyLeave error: " . $e->getMessage());
        }
        return ['status' => false, 'message' => 'Failed to submit leave application.'];
    }

    /**
     * Update leave application status (Approve or Reject)
     *
     * @param int $id
     * @param string $newStatus  'Approved' or 'Rejected'
     * @return array
     */
    public function updateLeaveStatus(int $id, string $newStatus): array {
        if (!in_array($newStatus, ['Approved', 'Rejected'])) {
            return ['status' => false, 'message' => 'Invalid status.'];
        }
        try {
            $db  = Database::getConnection();
            $uid = $_SESSION['user_id'] ?? null;
            $stmt = $db->prepare("
                UPDATE leave_applications SET status = :status, approved_by = :uid, updated_at = NOW()
                WHERE id = :id
            ");
            $ok = $stmt->execute(['status' => $newStatus, 'uid' => $uid, 'id' => $id]);
            if ($ok) {
                auditLog("Leave $newStatus", "Leave application ID $id was $newStatus");
                return ['status' => true, 'message' => "Leave application $newStatus successfully."];
            }
        } catch (PDOException $e) {
            error_log("AttendanceService::updateLeaveStatus error: " . $e->getMessage());
        }
        return ['status' => false, 'message' => 'Failed to update leave status.'];
    }

    /**
     * Validate a date string in Y-m-d format
     */
    private function isValidDate(string $date): bool {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}
