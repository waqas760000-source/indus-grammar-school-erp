<?php
/**
 * Indus Grammar School ERP - Attendance Model
 * Version 1.0.0
 */

class Attendance {

    /**
     * Fetch all student attendance records for a specific class and date
     *
     * @param int $classId
     * @param string $date (Y-m-d)
     * @return array
     */
    public static function getByClassAndDate(int $classId, string $date): array {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT a.*, s.first_name, s.last_name, s.admission_no
                FROM attendance a
                JOIN students s ON a.student_id = s.id
                WHERE a.class_id = :class_id AND a.date = :date
                ORDER BY s.first_name ASC
            ");
            $stmt->execute(['class_id' => $classId, 'date' => $date]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Attendance::getByClassAndDate error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Fetch attendance history for a specific student
     *
     * @param int $studentId
     * @param string|null $from
     * @param string|null $to
     * @param int $limit
     * @return array
     */
    public static function getStudentHistory(int $studentId, ?string $from = null, ?string $to = null, int $limit = 30): array {
        try {
            $db = Database::getConnection();
            $sql = "SELECT * FROM attendance WHERE student_id = :student_id";
            $params = ['student_id' => $studentId];
            if ($from) { $sql .= " AND date >= :from"; $params['from'] = $from; }
            if ($to)   { $sql .= " AND date <= :to";   $params['to']   = $to; }
            $sql .= " ORDER BY date DESC LIMIT :limit";
            $stmt = $db->prepare($sql);
            foreach ($params as $k => $v) $stmt->bindValue($k, $v);
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Attendance::getStudentHistory error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get daily attendance summary for a class
     *
     * @param string $date
     * @param int|null $classId
     * @return array ['present'=>n, 'absent'=>n, 'late'=>n, 'leave'=>n, 'total'=>n]
     */
    public static function getDailySummary(string $date, ?int $classId = null): array {
        try {
            $db = Database::getConnection();
            $sql = "SELECT status, COUNT(*) as cnt FROM attendance WHERE date = :date";
            $params = ['date' => $date];
            if ($classId) { $sql .= " AND class_id = :class_id"; $params['class_id'] = $classId; }
            $sql .= " GROUP BY status";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            $summary = ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Leave' => 0, 'total' => 0];
            foreach ($rows as $r) {
                $summary[$r['status']] = (int)$r['cnt'];
                $summary['total'] += (int)$r['cnt'];
            }
            return $summary;
        } catch (PDOException $e) {
            error_log("Attendance::getDailySummary error: " . $e->getMessage());
            return ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Leave' => 0, 'total' => 0];
        }
    }

    /**
     * Get monthly attendance data for a student
     *
     * @param int $studentId
     * @param int $month
     * @param int $year
     * @return array keyed by date string
     */
    public static function getMonthlyForStudent(int $studentId, int $month, int $year): array {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT date, status FROM attendance
                WHERE student_id = :sid
                  AND MONTH(date) = :month
                  AND YEAR(date) = :year
                ORDER BY date ASC
            ");
            $stmt->execute(['sid' => $studentId, 'month' => $month, 'year' => $year]);
            $rows = $stmt->fetchAll();
            $map = [];
            foreach ($rows as $r) $map[$r['date']] = $r['status'];
            return $map;
        } catch (PDOException $e) {
            error_log("Attendance::getMonthlyForStudent error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Mark or update a single student's attendance (INSERT … ON DUPLICATE KEY UPDATE)
     *
     * @param int $studentId
     * @param int $classId
     * @param string $date
     * @param string $status
     * @param string|null $remarks
     * @param int|null $markedBy
     * @return bool
     */
    public static function mark(int $studentId, int $classId, string $date, string $status, ?string $remarks, ?int $markedBy): bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO attendance (student_id, class_id, date, status, remarks, marked_by)
                VALUES (:student_id, :class_id, :date, :status, :remarks, :marked_by)
                ON DUPLICATE KEY UPDATE status = VALUES(status), remarks = VALUES(remarks), marked_by = VALUES(marked_by)
            ");
            return $stmt->execute([
                'student_id' => $studentId,
                'class_id'   => $classId,
                'date'       => $date,
                'status'     => $status,
                'remarks'    => $remarks,
                'marked_by'  => $markedBy
            ]);
        } catch (PDOException $e) {
            error_log("Attendance::mark error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get student attendance percentage for a date range
     *
     * @param int $studentId
     * @param string $from
     * @param string $to
     * @return float
     */
    public static function getAttendancePercentage(int $studentId, string $from, string $to): float {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT
                    COUNT(*) as total,
                    SUM(status IN ('Present','Late')) as present_count
                FROM attendance
                WHERE student_id = :sid AND date BETWEEN :from AND :to
            ");
            $stmt->execute(['sid' => $studentId, 'from' => $from, 'to' => $to]);
            $row = $stmt->fetch();
            if (!$row || $row['total'] == 0) return 0.0;
            return round(($row['present_count'] / $row['total']) * 100, 1);
        } catch (PDOException $e) {
            error_log("Attendance::getAttendancePercentage error: " . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Get all classes that had attendance marked for a given date
     *
     * @param string $date
     * @return array
     */
    public static function getMarkedClassesForDate(string $date): array {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT DISTINCT c.id, c.class_name, c.section,
                       COUNT(a.id) as student_count
                FROM attendance a
                JOIN classes c ON a.class_id = c.id
                WHERE a.date = :date
                GROUP BY c.id
            ");
            $stmt->execute(['date' => $date]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Attendance::getMarkedClassesForDate error: " . $e->getMessage());
            return [];
        }
    }
}
