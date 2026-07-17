<?php
/**
 * Indus Grammar School ERP - ExamSchedule Model
 * Version 4.0.0
 */

class ExamSchedule {

    public static function all(int $examTypeId = 0, int $classId = 0): array {
        try {
            $db = Database::getConnection();
            $sql = "
                SELECT es.*, et.exam_name, c.class_name, c.section, s.subject_name, s.subject_code
                FROM exam_schedule es
                JOIN exam_types et ON es.exam_type_id = et.id
                JOIN classes c ON es.class_id = c.id
                JOIN subjects s ON es.subject_id = s.id
                WHERE 1=1
            ";
            $params = [];
            if ($examTypeId > 0) {
                $sql .= " AND es.exam_type_id = :etid";
                $params['etid'] = $examTypeId;
            }
            if ($classId > 0) {
                $sql .= " AND es.class_id = :cid";
                $params['cid'] = $classId;
            }
            $sql .= " ORDER BY es.exam_date ASC, es.start_time ASC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("ExamSchedule::all error: " . $e->getMessage());
            return [];
        }
    }

    public static function findById(int $id): array|bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT * FROM exam_schedule WHERE id = :id");
            $stmt->execute(['id' => $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("ExamSchedule::findById error: " . $e->getMessage());
            return false;
        }
    }

    public static function create(array $data): int|bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO exam_schedule (exam_type_id, class_id, subject_id, exam_date, start_time, end_time, room, supervisor)
                VALUES (:etid, :cid, :sid, :date, :start, :end, :room, :supervisor)
            ");
            $stmt->execute([
                'etid'       => (int)$data['exam_type_id'],
                'cid'        => (int)$data['class_id'],
                'sid'        => (int)$data['subject_id'],
                'date'       => sanitize($data['exam_date']),
                'start'      => sanitize($data['start_time'] ?: null),
                'end'        => sanitize($data['end_time'] ?: null),
                'room'       => sanitize($data['room'] ?? ''),
                'supervisor' => sanitize($data['supervisor'] ?? '')
            ]);
            return (int)$db->lastInsertId();
        } catch (PDOException $e) {
            error_log("ExamSchedule::create error: " . $e->getMessage());
            return false;
        }
    }

    public static function update(int $id, array $data): bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                UPDATE exam_schedule 
                SET exam_type_id = :etid, class_id = :cid, subject_id = :sid, 
                    exam_date = :date, start_time = :start, end_time = :end, 
                    room = :room, supervisor = :supervisor
                WHERE id = :id
            ");
            return $stmt->execute([
                'etid'       => (int)$data['exam_type_id'],
                'cid'        => (int)$data['class_id'],
                'sid'        => (int)$data['subject_id'],
                'date'       => sanitize($data['exam_date']),
                'start'      => sanitize($data['start_time'] ?: null),
                'end'        => sanitize($data['end_time'] ?: null),
                'room'       => sanitize($data['room'] ?? ''),
                'supervisor' => sanitize($data['supervisor'] ?? ''),
                'id'         => $id
            ]);
        } catch (PDOException $e) {
            error_log("ExamSchedule::update error: " . $e->getMessage());
            return false;
        }
    }

    public static function delete(int $id): bool {
        try {
            $db = Database::getConnection();
            return $db->prepare("DELETE FROM exam_schedule WHERE id = :id")->execute(['id' => $id]);
        } catch (PDOException $e) {
            error_log("ExamSchedule::delete error: " . $e->getMessage());
            return false;
        }
    }
}
