<?php
/**
 * Indus Grammar School ERP - ExamType Model
 * Version 4.0.0
 */

class ExamType {

    public static function all(string $session = ''): array {
        try {
            $db = Database::getConnection();
            $currSession = $session ?: CURRENT_ACADEMIC_YEAR;
            $stmt = $db->prepare("SELECT * FROM exam_types WHERE academic_session = :session ORDER BY start_date DESC");
            $stmt->execute(['session' => $currSession]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("ExamType::all error: " . $e->getMessage());
            return [];
        }
    }

    public static function findById(int $id): array|bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT * FROM exam_types WHERE id = :id");
            $stmt->execute(['id' => $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("ExamType::findById error: " . $e->getMessage());
            return false;
        }
    }

    public static function create(array $data): int|bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO exam_types (exam_name, academic_session, academic_type, start_date, end_date, total_marks, passing_percentage, status)
                VALUES (:name, :session, :type, :start, :end, :marks, :pct, :status)
            ");
            $stmt->execute([
                'name'    => sanitize($data['exam_name']),
                'session' => sanitize($data['academic_session'] ?? CURRENT_ACADEMIC_YEAR),
                'type'    => sanitize($data['academic_type'] ?? 'School'),
                'start'   => sanitize($data['start_date']),
                'end'     => sanitize($data['end_date']),
                'marks'   => (int)($data['total_marks'] ?? 100),
                'pct'     => (float)($data['passing_percentage'] ?? 40.00),
                'status'  => sanitize($data['status'] ?? 'Active')
            ]);
            return (int)$db->lastInsertId();
        } catch (PDOException $e) {
            error_log("ExamType::create error: " . $e->getMessage());
            return false;
        }
    }

    public static function update(int $id, array $data): bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                UPDATE exam_types 
                SET exam_name = :name, academic_session = :session, academic_type = :type, 
                    start_date = :start, end_date = :end, total_marks = :marks, 
                    passing_percentage = :pct, status = :status
                WHERE id = :id
            ");
            return $stmt->execute([
                'name'    => sanitize($data['exam_name']),
                'session' => sanitize($data['academic_session']),
                'type'    => sanitize($data['academic_type']),
                'start'   => sanitize($data['start_date']),
                'end'     => sanitize($data['end_date']),
                'marks'   => (int)($data['total_marks']),
                'pct'     => (float)($data['passing_percentage']),
                'status'  => sanitize($data['status']),
                'id'      => $id
            ]);
        } catch (PDOException $e) {
            error_log("ExamType::update error: " . $e->getMessage());
            return false;
        }
    }

    public static function delete(int $id): bool {
        try {
            $db = Database::getConnection();
            return $db->prepare("DELETE FROM exam_types WHERE id = :id")->execute(['id' => $id]);
        } catch (PDOException $e) {
            error_log("ExamType::delete error: " . $e->getMessage());
            return false;
        }
    }
}
