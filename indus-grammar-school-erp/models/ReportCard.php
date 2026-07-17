<?php
/**
 * Indus Grammar School ERP - ReportCard Model
 * Version 4.0.0
 */

class ReportCard {

    public static function find(int $examTypeId, int $studentId): array|bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT * FROM report_cards WHERE exam_type_id = :etid AND student_id = :sid");
            $stmt->execute(['etid' => $examTypeId, 'sid' => $studentId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("ReportCard::find error: " . $e->getMessage());
            return false;
        }
    }

    public static function saveRemarks(array $data): bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO report_cards (exam_type_id, student_id, teacher_remarks, principal_remarks, attendance_percentage, promotion_status)
                VALUES (:etid, :sid, :tremarks, :premarks, :att, :promo)
                ON DUPLICATE KEY UPDATE 
                    teacher_remarks = :tremarks2, 
                    principal_remarks = :premarks2, 
                    attendance_percentage = :att2, 
                    promotion_status = :promo2
            ");
            return $stmt->execute([
                'etid'      => (int)$data['exam_type_id'],
                'sid'       => (int)$data['student_id'],
                'tremarks'  => sanitize($data['teacher_remarks'] ?? ''),
                'premarks'  => sanitize($data['principal_remarks'] ?? ''),
                'att'       => $data['attendance_percentage'] !== '' ? (float)$data['attendance_percentage'] : null,
                'promo'     => sanitize($data['promotion_status'] ?? ''),
                
                'tremarks2' => sanitize($data['teacher_remarks'] ?? ''),
                'premarks2' => sanitize($data['principal_remarks'] ?? ''),
                'att2'      => $data['attendance_percentage'] !== '' ? (float)$data['attendance_percentage'] : null,
                'promo2'    => sanitize($data['promotion_status'] ?? '')
            ]);
        } catch (PDOException $e) {
            error_log("ReportCard::saveRemarks error: " . $e->getMessage());
            return false;
        }
    }
}
