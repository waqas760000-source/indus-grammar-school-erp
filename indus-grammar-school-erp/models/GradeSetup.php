<?php
/**
 * Indus Grammar School ERP - GradeSetup Model
 * Version 4.0.0
 */

class GradeSetup {

    public static function all(): array {
        try {
            $db = Database::getConnection();
            return $db->query("SELECT * FROM grade_setup ORDER BY min_percentage DESC")->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("GradeSetup::all error: " . $e->getMessage());
            return [];
        }
    }

    public static function findById(int $id): array|bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT * FROM grade_setup WHERE id = :id");
            $stmt->execute(['id' => $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("GradeSetup::findById error: " . $e->getMessage());
            return false;
        }
    }

    public static function create(array $data): int|bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO grade_setup (grade, min_percentage, max_percentage, grade_point, remarks)
                VALUES (:grade, :min, :max, :gp, :remarks)
            ");
            $stmt->execute([
                'grade'   => sanitize($data['grade']),
                'min'     => (float)$data['min_percentage'],
                'max'     => (float)$data['max_percentage'],
                'gp'      => (float)($data['grade_point'] ?? 0.00),
                'remarks' => sanitize($data['remarks'] ?? '')
            ]);
            return (int)$db->lastInsertId();
        } catch (PDOException $e) {
            error_log("GradeSetup::create error: " . $e->getMessage());
            return false;
        }
    }

    public static function update(int $id, array $data): bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                UPDATE grade_setup 
                SET grade = :grade, min_percentage = :min, max_percentage = :max, 
                    grade_point = :gp, remarks = :remarks
                WHERE id = :id
            ");
            return $stmt->execute([
                'grade'   => sanitize($data['grade']),
                'min'     => (float)$data['min_percentage'],
                'max'     => (float)$data['max_percentage'],
                'gp'      => (float)($data['grade_point']),
                'remarks' => sanitize($data['remarks'] ?? ''),
                'id'      => $id
            ]);
        } catch (PDOException $e) {
            error_log("GradeSetup::update error: " . $e->getMessage());
            return false;
        }
    }

    public static function delete(int $id): bool {
        try {
            $db = Database::getConnection();
            return $db->prepare("DELETE FROM grade_setup WHERE id = :id")->execute(['id' => $id]);
        } catch (PDOException $e) {
            error_log("GradeSetup::delete error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Match grade in memory from array of scales to avoid DB roundtrips in bulk loops.
     */
    public static function matchGrade(float $percentage, array $scales): array {
        foreach ($scales as $scale) {
            if ($percentage >= (float)$scale['min_percentage'] && $percentage <= (float)$scale['max_percentage']) {
                return $scale;
            }
        }
        return ['grade' => 'F', 'grade_point' => 0.00, 'remarks' => 'Fail'];
    }

    /**
     * Look up Grade scale by numeric percentage.
     */
    public static function getGradeByPercentage(float $percentage): array {
        static $cachedScales = null;
        if ($cachedScales === null) {
            $cachedScales = self::all();
        }
        if (!empty($cachedScales)) {
            return self::matchGrade($percentage, $cachedScales);
        }

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT * FROM grade_setup 
                WHERE :pct1 >= min_percentage AND :pct2 <= max_percentage 
                ORDER BY min_percentage DESC
                LIMIT 1
            ");
            $stmt->execute(['pct1' => $percentage, 'pct2' => $percentage]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($res) return $res;
        } catch (Exception $e) {
            error_log("GradeSetup::getGradeByPercentage error: " . $e->getMessage());
        }
        return ['grade' => 'F', 'grade_point' => 0.00, 'remarks' => 'Fail'];
    }
}
