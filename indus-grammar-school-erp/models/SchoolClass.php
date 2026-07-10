<?php
/**
 * Indus Grammar School ERP - SchoolClass Model
 * Version 1.0.0
 */

class SchoolClass {
    
    /**
     * Retrieve all classes
     *
     * @return array
     */
    public static function all(): array {
        try {
            $db = Database::getConnection();
            $stmt = $db->query("SELECT * FROM classes ORDER BY class_name ASC, section ASC");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("SchoolClass::all error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Find a class by ID
     *
     * @param int $id
     * @return array|bool
     */
    public static function findById(int $id): array|bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT * FROM classes WHERE id = :id");
            $stmt->execute(['id' => $id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("SchoolClass::findById error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a new school class
     *
     * @param string $className e.g. "Class 1"
     * @param string $section e.g. "A"
     * @return int|bool Last inserted class ID or false
     */
    public static function create(string $className, string $section): int|bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("INSERT INTO classes (class_name, section) VALUES (:class_name, :section)");
            $stmt->execute([
                'class_name' => trim($className),
                'section' => trim($section)
            ]);
            return (int)$db->lastInsertId();
        } catch (PDOException $e) {
            error_log("SchoolClass::create error: " . $e->getMessage());
            return false;
        }
    }
}
