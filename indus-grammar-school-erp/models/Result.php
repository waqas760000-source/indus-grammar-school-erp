<?php
/**
 * Indus Grammar School ERP - Result Model
 * Version 1.0.0
 */

class Result {

    public static function getStudentMarks(int $examId, int $studentId): array {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT r.*, s.subject_name, s.total_marks
                FROM results r
                JOIN subjects s ON r.subject_id = s.id
                WHERE r.exam_id = :eid AND r.student_id = :sid
            ");
            $stmt->execute(['eid' => $examId, 'sid' => $studentId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Result::getStudentMarks error: " . $e->getMessage());
            return [];
        }
    }

    public static function getClassMarksBySubject(int $examId, int $classId, int $subjectId): array {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT st.id as student_id, st.first_name, st.last_name, st.admission_no,
                       r.id as result_id, r.marks_obtained, r.remarks, r.status
                FROM students st
                LEFT JOIN results r ON r.student_id = st.id AND r.exam_id = :eid AND r.subject_id = :subid
                WHERE st.class_id = :cid AND st.status = 'Active'
                ORDER BY st.first_name ASC
            ");
            $stmt->execute(['eid' => $examId, 'subid' => $subjectId, 'cid' => $classId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Result::getClassMarksBySubject error: " . $e->getMessage());
            return [];
        }
    }

    public static function saveMarks(array $data): bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO results (exam_id, student_id, subject_id, marks_obtained, remarks, status)
                VALUES (:eid, :sid, :subid, :marks, :remarks, :status)
                ON DUPLICATE KEY UPDATE marks_obtained = :marks2, remarks = :remarks2, status = :status2
            ");
            return $stmt->execute([
                'eid'      => $data['exam_id'],
                'sid'      => $data['student_id'],
                'subid'    => $data['subject_id'],
                'marks'    => $data['marks_obtained'],
                'remarks'  => $data['remarks'] ?? '',
                'status'   => $data['status'] ?? 'Present',
                'marks2'   => $data['marks_obtained'],
                'remarks2' => $data['remarks'] ?? '',
                'status2'  => $data['status'] ?? 'Present'
            ]);
        } catch (PDOException $e) {
            error_log("Result::saveMarks error: " . $e->getMessage());
            return false;
        }
    }
}
