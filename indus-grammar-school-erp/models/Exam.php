<?php
/**
 * Indus Grammar School ERP - Exam Model
 * Version 1.0.0
 */

class Exam {

    public static function all(string $academicYear = ''): array {
        try {
            $db = Database::getConnection();
            $year = $academicYear ?: CURRENT_ACADEMIC_YEAR;
            $stmt = $db->prepare("SELECT * FROM exams WHERE academic_year = :year ORDER BY start_date DESC");
            $stmt->execute(['year' => $year]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Exam::all error: " . $e->getMessage());
            return [];
        }
    }

    public static function create(array $data): int|bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO exams (exam_name, academic_year, start_date, end_date)
                VALUES (:name, :year, :start, :end)
            ");
            $stmt->execute([
                'name'  => sanitize($data['exam_name']),
                'year'  => sanitize($data['academic_year'] ?? CURRENT_ACADEMIC_YEAR),
                'start' => sanitize($data['start_date']),
                'end'   => sanitize($data['end_date']),
            ]);
            return (int)$db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Exam::create error: " . $e->getMessage());
            return false;
        }
    }

    public static function update(int $id, array $data): bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                UPDATE exams SET exam_name = :name, start_date = :start, end_date = :end
                WHERE id = :id
            ");
            return $stmt->execute([
                'name'  => sanitize($data['exam_name']),
                'start' => sanitize($data['start_date']),
                'end'   => sanitize($data['end_date']),
                'id'    => $id
            ]);
        } catch (PDOException $e) {
            error_log("Exam::update error: " . $e->getMessage());
            return false;
        }
    }

    public static function delete(int $id): bool {
        try {
            $db = Database::getConnection();
            return $db->prepare("DELETE FROM exams WHERE id = :id")->execute(['id' => $id]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public static function findById(int $id): array|bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT * FROM exams WHERE id = :id");
            $stmt->execute(['id' => $id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return false;
        }
    }
}
