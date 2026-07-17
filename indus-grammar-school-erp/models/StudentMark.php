<?php
/**
 * Indus Grammar School ERP - StudentMark Model
 * Version 4.0.0
 */

class StudentMark {

    public static function getStudentMarks(int $examTypeId, int $studentId): array {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT sm.*, s.subject_name, s.subject_code, s.total_marks, s.passing_marks
                FROM student_marks sm
                JOIN subjects s ON sm.subject_id = s.id
                WHERE sm.exam_type_id = :etid AND sm.student_id = :sid
            ");
            $stmt->execute(['etid' => $examTypeId, 'sid' => $studentId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("StudentMark::getStudentMarks error: " . $e->getMessage());
            return [];
        }
    }

    public static function getClassMarksBySubject(int $examTypeId, int $classId, int $subjectId): array {
        try {
            $db = Database::getConnection();
            // Fetch all active students in the class and join their marks
            $stmt = $db->prepare("
                SELECT st.id as student_id, st.first_name, st.last_name, st.admission_no,
                       sm.id as mark_id, sm.marks_obtained, sm.remarks, sm.status
                FROM students st
                LEFT JOIN student_marks sm ON sm.student_id = st.id AND sm.exam_type_id = :etid AND sm.subject_id = :subid
                WHERE st.class_id = :cid AND st.status = 'Active'
                ORDER BY st.first_name ASC
            ");
            $stmt->execute(['etid' => $examTypeId, 'subid' => $subjectId, 'cid' => $classId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("StudentMark::getClassMarksBySubject error: " . $e->getMessage());
            return [];
        }
    }

    public static function saveMarks(array $data): bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO student_marks (exam_type_id, student_id, subject_id, marks_obtained, remarks, status)
                VALUES (:etid, :sid, :subid, :marks, :remarks, :status)
                ON DUPLICATE KEY UPDATE marks_obtained = :marks2, remarks = :remarks2, status = :status2
            ");
            return $stmt->execute([
                'etid'     => $data['exam_type_id'],
                'sid'      => $data['student_id'],
                'subid'    => $data['subject_id'],
                'marks'    => $data['marks_obtained'] !== '' ? $data['marks_obtained'] : null,
                'remarks'  => sanitize($data['remarks'] ?? ''),
                'status'   => sanitize($data['status'] ?? 'Present'),
                'marks2'   => $data['marks_obtained'] !== '' ? $data['marks_obtained'] : null,
                'remarks2' => sanitize($data['remarks'] ?? ''),
                'status2'  => sanitize($data['status'] ?? 'Present')
            ]);
        } catch (PDOException $e) {
            error_log("StudentMark::saveMarks error: " . $e->getMessage());
            return false;
        }
    }
}
