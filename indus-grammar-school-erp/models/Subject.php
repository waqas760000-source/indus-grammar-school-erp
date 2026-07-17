<?php
/**
 * Indus Grammar School ERP - Subject Model
 * Version 4.0.0
 */

class Subject {

    public static function all(int $classId = 0): array {
        try {
            $db = Database::getConnection();
            $sql = "
                SELECT s.*, c.class_name, c.section, 
                       st.first_name as teacher_first, st.last_name as teacher_last
                FROM subjects s 
                JOIN classes c ON s.class_id = c.id
                LEFT JOIN staff st ON s.teacher_id = st.id
            ";
            if ($classId > 0) $sql .= " WHERE s.class_id = :cid";
            $sql .= " ORDER BY c.class_name, s.subject_name";
            
            $stmt = $db->prepare($sql);
            if ($classId > 0) $stmt->bindValue(':cid', $classId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Subject::all error: " . $e->getMessage());
            return [];
        }
    }

    public static function create(array $data): int|bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO subjects (class_id, subject_name, subject_code, total_marks, passing_marks, academic_type, teacher_id, status)
                VALUES (:cid, :name, :code, :marks, :pmarks, :atype, :tid, :status)
            ");
            $stmt->execute([
                'cid'    => (int)$data['class_id'],
                'name'   => sanitize($data['subject_name']),
                'code'   => sanitize($data['subject_code'] ?? ''),
                'marks'  => (int)($data['total_marks'] ?? 100),
                'pmarks' => (int)($data['passing_marks'] ?? 40),
                'atype'  => sanitize($data['academic_type'] ?? 'School'),
                'tid'    => $data['teacher_id'] > 0 ? (int)$data['teacher_id'] : null,
                'status' => sanitize($data['status'] ?? 'Active')
            ]);
            return (int)$db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Subject::create error: " . $e->getMessage());
            return false;
        }
    }

    public static function update(int $id, array $data): bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                UPDATE subjects 
                SET subject_name = :name, subject_code = :code, total_marks = :marks, 
                    passing_marks = :pmarks, academic_type = :atype, teacher_id = :tid, status = :status
                WHERE id = :id
            ");
            return $stmt->execute([
                'name'   => sanitize($data['subject_name']),
                'code'   => sanitize($data['subject_code'] ?? ''),
                'marks'  => (int)($data['total_marks'] ?? 100),
                'pmarks' => (int)($data['passing_marks'] ?? 40),
                'atype'  => sanitize($data['academic_type'] ?? 'School'),
                'tid'    => $data['teacher_id'] > 0 ? (int)$data['teacher_id'] : null,
                'status' => sanitize($data['status'] ?? 'Active'),
                'id'     => $id
            ]);
        } catch (PDOException $e) {
            error_log("Subject::update error: " . $e->getMessage());
            return false;
        }
    }

    public static function delete(int $id): bool {
        try {
            $db = Database::getConnection();
            return $db->prepare("DELETE FROM subjects WHERE id = :id")->execute(['id' => $id]);
        } catch (PDOException $e) {
            error_log("Subject::delete error: " . $e->getMessage());
            return false;
        }
    }

    public static function findById(int $id): array|bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT * FROM subjects WHERE id = :id");
            $stmt->execute(['id' => $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Subject::findById error: " . $e->getMessage());
            return false;
        }
    }
}
