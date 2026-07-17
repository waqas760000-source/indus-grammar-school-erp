<?php
/**
 * Indus Grammar School ERP - ExamResult Model
 * Version 4.0.0
 */

class ExamResult {

    public static function getStudentResult(int $examTypeId, int $studentId): array|bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT * FROM exam_results WHERE exam_type_id = :etid AND student_id = :sid");
            $stmt->execute(['etid' => $examTypeId, 'sid' => $studentId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("ExamResult::getStudentResult error: " . $e->getMessage());
            return false;
        }
    }

    public static function getClassResults(int $examTypeId, int $classId): array {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT er.*, st.first_name, st.last_name, st.admission_no
                FROM exam_results er
                JOIN students st ON er.student_id = st.id
                WHERE er.exam_type_id = :etid AND er.class_id = :cid
                ORDER BY er.position ASC, st.first_name ASC
            ");
            $stmt->execute(['etid' => $examTypeId, 'cid' => $classId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("ExamResult::getClassResults error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Compute and save results for all active students in a class.
     */
    public static function generateClassResults(int $examTypeId, int $classId): array {
        try {
            $db = Database::getConnection();
            
            // 1. Fetch Exam details
            $exam = ExamType::findById($examTypeId);
            if (!$exam) return ['success' => false, 'message' => 'Exam type not found.'];

            // 2. Fetch all active students in class
            $stmtStuds = $db->prepare("SELECT id, first_name, last_name, admission_no FROM students WHERE class_id = :cid AND status = 'Active'");
            $stmtStuds->execute(['cid' => $classId]);
            $students = $stmtStuds->fetchAll(PDO::FETCH_ASSOC);

            if (empty($students)) return ['success' => false, 'message' => 'No active students found in this class.'];

            // 3. Fetch class subjects
            $stmtSubs = $db->prepare("SELECT id, total_marks, passing_marks FROM subjects WHERE class_id = :cid AND status = 'Active'");
            $stmtSubs->execute(['cid' => $classId]);
            $subjects = $stmtSubs->fetchAll(PDO::FETCH_ASSOC);

            if (empty($subjects)) return ['success' => false, 'message' => 'No active subjects assigned to this class.'];

            $subjectCount = count($subjects);
            $db->beginTransaction();

            $resultsToRank = [];

            foreach ($students as $student) {
                $studentId = $student['id'];
                
                // Fetch student marks for this exam type and these subjects
                $stmtMarks = $db->prepare("
                    SELECT subject_id, marks_obtained, status 
                    FROM student_marks 
                    WHERE exam_type_id = :etid AND student_id = :sid
                ");
                $stmtMarks->execute(['etid' => $examTypeId, 'sid' => $studentId]);
                $marks = $stmtMarks->fetchAll(PDO::FETCH_ASSOC);

                $obtTotal = 0.00;
                $maxTotal = 0.00;
                $failedAny = false;
                $presentCount = 0;

                // Create a subject map to easily find mark entries
                $markMap = [];
                foreach ($marks as $m) {
                    $markMap[$m['subject_id']] = $m;
                }

                foreach ($subjects as $sub) {
                    $subId = $sub['id'];
                    $maxTotal += $sub['total_marks'];

                    if (isset($markMap[$subId])) {
                        $m = $markMap[$subId];
                        if ($m['status'] === 'Present' && $m['marks_obtained'] !== null) {
                            $obtTotal += (float)$m['marks_obtained'];
                            $presentCount++;
                            if ((float)$m['marks_obtained'] < (float)$sub['passing_marks']) {
                                $failedAny = true;
                            }
                        } else if ($m['status'] === 'Absent') {
                            $failedAny = true; // Absent is failing by default
                        } else if ($m['status'] === 'Leave' || $m['status'] === 'Exempt') {
                            // Leave/Exempt means we skip this subject from max calculations if desired, 
                            // but in most ERPs we still keep it or treat as 0. Let's add marks_obtained as 0.
                        }
                    } else {
                        // Mark is not entered yet for this subject
                        $failedAny = true; 
                    }
                }

                $percentage = $maxTotal > 0 ? ($obtTotal / $maxTotal) * 100 : 0.00;
                
                // Automatically lookup Grade scale
                $gradeInfo = GradeSetup::getGradeByPercentage($percentage);
                $grade = $gradeInfo['grade'];
                
                // If overall percentage is below passing percentage, or student failed any individual compulsory subject, overall status is Fail
                $passStatus = 'Pass';
                if ($percentage < (float)$exam['passing_percentage'] || $failedAny) {
                    $passStatus = 'Fail';
                }

                $resultsToRank[] = [
                    'student_id' => $studentId,
                    'total_marks' => $maxTotal,
                    'obtained_marks' => $obtTotal,
                    'percentage' => $percentage,
                    'grade' => $grade,
                    'status' => $passStatus
                ];
            }

            // 4. Rank/Sort students by percentage (and obtained marks as tie breaker) descending
            usort($resultsToRank, function($a, $b) {
                if ($b['percentage'] == $a['percentage']) {
                    return $b['obtained_marks'] <=> $a['obtained_marks'];
                }
                return $b['percentage'] <=> $a['percentage'];
            });

            // 5. Save results to database and update cache
            $pos = 1;
            foreach ($resultsToRank as $res) {
                $studentId = $res['student_id'];
                
                // Insert/Update exam_results
                $stmtSave = $db->prepare("
                    INSERT INTO exam_results (exam_type_id, student_id, class_id, total_marks, obtained_marks, percentage, grade, position, status)
                    VALUES (:etid, :sid, :cid, :total, :obt, :pct, :grade, :pos, :status)
                    ON DUPLICATE KEY UPDATE total_marks = :total2, obtained_marks = :obt2, percentage = :pct2, grade = :grade2, position = :pos2, status = :status2
                ");
                $stmtSave->execute([
                    'etid'    => $examTypeId,
                    'sid'     => $studentId,
                    'cid'     => $classId,
                    'total'   => $res['total_marks'],
                    'obt'     => $res['obtained_marks'],
                    'pct'     => $res['percentage'],
                    'grade'   => $res['grade'],
                    'pos'     => $pos,
                    'status'  => $res['status'],
                    
                    'total2'  => $res['total_marks'],
                    'obt2'    => $res['obtained_marks'],
                    'pct2'    => $res['percentage'],
                    'grade2'  => $res['grade'],
                    'pos2'    => $pos,
                    'status2' => $res['status']
                ]);

                // Insert/Update positions
                $stmtPos = $db->prepare("
                    INSERT INTO positions (exam_type_id, class_id, student_id, percentage, grade, position_no, status)
                    VALUES (:etid, :cid, :sid, :pct, :grade, :pos, :status)
                    ON DUPLICATE KEY UPDATE percentage = :pct2, grade = :grade2, position_no = :pos2, status = :status2
                ");
                $stmtPos->execute([
                    'etid'    => $examTypeId,
                    'cid'     => $classId,
                    'sid'     => $studentId,
                    'pct'     => $res['percentage'],
                    'grade'   => $res['grade'],
                    'pos'     => $pos,
                    'status'  => $res['status'],
                    
                    'pct2'    => $res['percentage'],
                    'grade2'  => $res['grade'],
                    'pos2'    => $pos,
                    'status2' => $res['status']
                ]);

                $pos++;
            }

            $db->commit();
            auditLog('Results Generated', "Successfully generated results for Exam ID $examTypeId, Class ID $classId.");
            return ['success' => true, 'message' => 'Results compiled and rank positions generated.'];

        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) $db->rollBack();
            error_log("ExamResult::generateClassResults error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Result processing failed: ' . $e->getMessage()];
        }
    }
}
