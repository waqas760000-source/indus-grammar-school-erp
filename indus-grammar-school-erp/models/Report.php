<?php
/**
 * Indus Grammar School ERP - Report Model
 * Version 2.0.0
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

    // ── Overview metrics ──
    public static function getOverviewMetrics(): array {
        try {
            $db = Database::getConnection();
            
            // Total Classes and Sections
            $classesCount = (int)$db->query("SELECT COUNT(DISTINCT class_name) FROM classes")->fetchColumn();
            $sectionsCount = (int)$db->query("SELECT COUNT(*) FROM classes")->fetchColumn();
            
            // Total Staff
            $staffCount = (int)$db->query("SELECT COUNT(*) FROM staff WHERE status = 'Active'")->fetchColumn();

            // Total Students
            $studentsCount = (int)$db->query("SELECT COUNT(*) FROM students WHERE status = 'Active'")->fetchColumn();

            // Total Admissions
            $admissionsCount = (int)$db->query("SELECT COUNT(*) FROM admissions")->fetchColumn();

            // Today's Attendance %
            $attendanceRate = 100.0;
            $att = $db->query("
                SELECT 
                    SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
                    COUNT(*) as total
                FROM attendance
                WHERE date = CURRENT_DATE
            ")->fetch();
            if (!empty($att['total'])) {
                $attendanceRate = ($att['present'] / $att['total']) * 100;
            }

            // Financial Summaries
            $incomeMonth = (float)$db->query("SELECT SUM(amount_paid) FROM fee_collections WHERE MONTH(payment_date) = MONTH(CURRENT_DATE) AND YEAR(payment_date) = YEAR(CURRENT_DATE)")->fetchColumn();
            $expensesMonth = (float)$db->query("SELECT SUM(amount) FROM expenses WHERE MONTH(expense_date) = MONTH(CURRENT_DATE) AND YEAR(expense_date) = YEAR(CURRENT_DATE)")->fetchColumn();
            
            // Outstanding unpaid challans
            $pendingFees = (float)$db->query("SELECT SUM(net_amount - amount_paid) FROM fee_challans fc LEFT JOIN (SELECT challan_id, SUM(amount_paid) as amount_paid FROM fee_collections GROUP BY challan_id) col ON fc.id = col.challan_id WHERE fc.status IN ('Unpaid', 'Overdue')")->fetchColumn();

            // Admissions this month
            $admissionsThisMonth = (int)$db->query("SELECT COUNT(*) FROM admissions WHERE MONTH(application_date) = MONTH(CURRENT_DATE) AND YEAR(application_date) = YEAR(CURRENT_DATE)")->fetchColumn();

            // Upcoming exams count
            $upcomingExamsCount = (int)$db->query("SELECT COUNT(*) FROM exam_schedules WHERE exam_date >= CURRENT_DATE")->fetchColumn();

            // Cash in Hand (from open register if any, or general calculation)
            $cashInHand = (float)$db->query("SELECT (opening_balance + total_collections - total_expenses) FROM cash_register WHERE status = 'Open' ORDER BY date DESC LIMIT 1")->fetchColumn();
            if (!$cashInHand) {
                $cashInHand = $incomeMonth - $expensesMonth;
            }

            return [
                'total_students'        => $studentsCount,
                'total_staff'           => $staffCount,
                'total_admissions'      => $admissionsCount,
                'admissions_this_month' => $admissionsThisMonth,
                'upcoming_exams_count'  => $upcomingExamsCount,
                'total_classes'         => $classesCount,
                'total_sections'        => $sectionsCount,
                'attendance_rate'       => round($attendanceRate, 1),
                'pending_fees'          => $pendingFees,
                'monthly_income'        => $incomeMonth,
                'monthly_expenses'      => $expensesMonth,
                'cash_in_hand'          => $cashInHand
            ];
        } catch (Exception $e) {
            error_log("Report::getOverviewMetrics error: " . $e->getMessage());
            return [];
        }
    }

    // ── Analytics Chart queries ──
    public static function getGenderDistribution(): array {
        try {
            $db = Database::getConnection();
            return $db->query("SELECT gender, COUNT(*) as count FROM students WHERE status = 'Active' GROUP BY gender")->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }

    public static function getAdmissionsTrend(string $range): array {
        try {
            $db = Database::getConnection();
            $sql = "SELECT DATE_FORMAT(application_date, '%b %Y') as label, COUNT(*) as value 
                    FROM admissions 
                    WHERE application_date >= DATE_SUB(CURRENT_DATE, INTERVAL 1 YEAR)
                    GROUP BY MONTH(application_date) 
                    ORDER BY application_date ASC";
            if ($range === 'Today') {
                $sql = "SELECT DATE_FORMAT(application_date, '%H:00') as label, COUNT(*) as value 
                        FROM admissions 
                        WHERE application_date = CURRENT_DATE
                        GROUP BY HOUR(created_at)";
            } elseif ($range === 'This Week') {
                $sql = "SELECT DAYNAME(application_date) as label, COUNT(*) as value 
                        FROM admissions 
                        WHERE application_date >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)
                        GROUP BY application_date 
                        ORDER BY application_date ASC";
            }
            return $db->query($sql)->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }

    public static function getFeeCollectionsTrend(string $range): array {
        try {
            $db = Database::getConnection();
            $sql = "SELECT DATE_FORMAT(payment_date, '%b %Y') as label, SUM(amount_paid) as value 
                    FROM fee_collections 
                    WHERE payment_date >= DATE_SUB(CURRENT_DATE, INTERVAL 1 YEAR)
                    GROUP BY MONTH(payment_date) 
                    ORDER BY payment_date ASC";
            if ($range === 'Today') {
                $sql = "SELECT DATE_FORMAT(payment_date, '%H:00') as label, SUM(amount_paid) as value 
                        FROM fee_collections 
                        WHERE payment_date = CURRENT_DATE
                        GROUP BY HOUR(created_at)";
            } elseif ($range === 'This Week') {
                $sql = "SELECT DAYNAME(payment_date) as label, SUM(amount_paid) as value 
                        FROM fee_collections 
                        WHERE payment_date >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)
                        GROUP BY payment_date 
                        ORDER BY payment_date ASC";
            }
            return $db->query($sql)->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }

    public static function getIncomeExpensesTrend(string $range): array {
        try {
            $db = Database::getConnection();
            $labels = [];
            $income = [];
            $expenses = [];

            // Fetch last 6 months metrics
            $stmt = $db->query("
                SELECT DATE_FORMAT(d.date, '%b %Y') as label, 
                       (SELECT COALESCE(SUM(amount_paid), 0) FROM fee_collections WHERE DATE_FORMAT(payment_date, '%b %Y') = DATE_FORMAT(d.date, '%b %Y')) as inc,
                       (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE DATE_FORMAT(expense_date, '%b %Y') = DATE_FORMAT(d.date, '%b %Y')) as exp
                FROM (
                    SELECT CURRENT_DATE - INTERVAL 5 MONTH as date UNION
                    SELECT CURRENT_DATE - INTERVAL 4 MONTH UNION
                    SELECT CURRENT_DATE - INTERVAL 3 MONTH UNION
                    SELECT CURRENT_DATE - INTERVAL 2 MONTH UNION
                    SELECT CURRENT_DATE - INTERVAL 1 MONTH UNION
                    SELECT CURRENT_DATE
                ) d
                GROUP BY label
                ORDER BY d.date ASC
            ");
            $rows = $stmt->fetchAll();
            foreach ($rows as $row) {
                $labels[] = $row['label'];
                $income[] = (float)$row['inc'];
                $expenses[] = (float)$row['exp'];
            }
            return ['labels' => $labels, 'income' => $income, 'expenses' => $expenses];
        } catch (Exception $e) {
            return ['labels' => [], 'income' => [], 'expenses' => []];
        }
    }

    public static function getAttendanceTrend(string $range): array {
        try {
            $db = Database::getConnection();
            $sql = "SELECT DATE_FORMAT(date, '%b %Y') as label, 
                           ROUND((SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as value
                    FROM attendance
                    WHERE date >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
                    GROUP BY MONTH(date)
                    ORDER BY date ASC";
            if ($range === 'This Week') {
                $sql = "SELECT DAYNAME(date) as label, 
                               ROUND((SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as value
                        FROM attendance
                        WHERE date >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)
                        GROUP BY date
                        ORDER BY date ASC";
            }
            return $db->query($sql)->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
}
