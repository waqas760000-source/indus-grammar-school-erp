<?php
/**
 * Indus Grammar School ERP - ReportService
 * Version 1.0.0
 */

class ReportService {

    public function getDashboardKPIs(): array {
        $studentStats = Report::getStudentSummary();
        $financeStats = Report::getFinanceSummary(date('Y-m-01'), date('Y-m-d'));
        
        // Compute attendance rate for today
        $attendanceRate = 100.0;
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT 
                    SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
                    COUNT(*) as total
                FROM attendance
                WHERE date = :today
            ");
            $stmt->execute(['today' => date('Y-m-d')]);
            $att = $stmt->fetch();
            if (!empty($att['total'])) {
                $attendanceRate = ($att['present'] / $att['total']) * 100;
            }
        } catch (Exception $e) {}

        return [
            'total_students'  => $studentStats['active_count'] ?? 0,
            'monthly_collection' => $financeStats['collections'] ?? 0.00,
            'monthly_expense' => $financeStats['expenses'] ?? 0.00,
            'attendance_rate' => round($attendanceRate, 1)
        ];
    }
}
