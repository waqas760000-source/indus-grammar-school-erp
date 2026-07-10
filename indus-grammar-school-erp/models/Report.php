<?php
/**
 * Indus Grammar School ERP - Report Model
 * Version 1.0.0
 */

class Report {

    public static function getStudentSummary(): array {
        try {
            $db = Database::getConnection();
            $stmt = $db->query("
                SELECT 
                    (SELECT COUNT(*) FROM students WHERE status = 'Active') as active_count,
                    (SELECT COUNT(*) FROM students WHERE status = 'Suspended') as suspended_count,
                    (SELECT COUNT(*) FROM students WHERE status = 'Graduated') as graduated_count,
                    (SELECT COUNT(*) FROM students WHERE status = 'Withdrawn') as withdrawn_count,
                    (SELECT COUNT(*) FROM students) as total_count
            ");
            return $stmt->fetch() ?: [];
        } catch (PDOException $e) {
            error_log("Report::getStudentSummary error: " . $e->getMessage());
            return [];
        }
    }

    public static function getClasswiseEnrollment(): array {
        try {
            $db = Database::getConnection();
            $stmt = $db->query("
                SELECT c.class_name, c.section, COUNT(s.id) as student_count
                FROM classes c
                LEFT JOIN students s ON s.class_id = c.id AND s.status = 'Active'
                GROUP BY c.id
                ORDER BY c.class_name, c.section
            ");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Report::getClasswiseEnrollment error: " . $e->getMessage());
            return [];
        }
    }

    public static function getAttendanceStats(string $from, string $to): array {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT 
                    status,
                    COUNT(*) as count
                FROM attendance
                WHERE date BETWEEN :from AND :to
                GROUP BY status
            ");
            $stmt->execute(['from' => $from, 'to' => $to]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Report::getAttendanceStats error: " . $e->getMessage());
            return [];
        }
    }

    public static function getFinanceSummary(string $from, string $to): array {
        try {
            $db = Database::getConnection();
            // Total collections
            $stmt1 = $db->prepare("SELECT SUM(amount_paid) as total FROM fee_collections WHERE payment_date BETWEEN :from AND :to");
            $stmt1->execute(['from' => $from, 'to' => $to]);
            $col = $stmt1->fetch();

            // Total expenses
            $stmt2 = $db->prepare("SELECT SUM(amount) as total FROM expenses WHERE expense_date BETWEEN :from AND :to");
            $stmt2->execute(['from' => $from, 'to' => $to]);
            $exp = $stmt2->fetch();

            // Total unpaid challans
            $stmt3 = $db->prepare("SELECT SUM(net_amount) as total FROM fee_challans WHERE status IN ('Unpaid', 'Overdue')");
            $stmt3->execute();
            $unpaid = $stmt3->fetch();

            return [
                'collections' => (float)($col['total'] ?? 0),
                'expenses'    => (float)($exp['total'] ?? 0),
                'outstanding' => (float)($unpaid['total'] ?? 0)
            ];
        } catch (PDOException $e) {
            error_log("Report::getFinanceSummary error: " . $e->getMessage());
            return ['collections' => 0.0, 'expenses' => 0.0, 'outstanding' => 0.0];
        }
    }

    public static function getStaffSummary(): array {
        try {
            $db = Database::getConnection();
            $stmt = $db->query("
                SELECT 
                    department,
                    status,
                    COUNT(*) as count,
                    SUM(salary) as total_salary
                FROM staff
                GROUP BY department, status
            ");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Report::getStaffSummary error: " . $e->getMessage());
            return [];
        }
    }
}
