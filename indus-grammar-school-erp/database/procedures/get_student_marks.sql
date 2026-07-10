-- Stored Procedure: Get student marks for exam term
DELIMITER //

CREATE PROCEDURE IF NOT EXISTS GetStudentExamMarks(IN studentId INT, IN examId INT)
BEGIN
    SELECT r.marks_obtained, s.subject_name, s.total_marks
    FROM results r
    JOIN subjects s ON r.subject_id = s.id
    WHERE r.student_id = studentId AND r.exam_id = examId;
END //

DELIMITER ;
