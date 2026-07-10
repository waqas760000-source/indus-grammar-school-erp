<?php
/**
 * Indus Grammar School ERP - ExamService
 * Version 1.0.0
 */

class ExamService {

    /**
     * Save marks for a class and subject in bulk.
     *
     * @param int $examId
     * @param int $subjectId
     * @param array $marksData
     * @return array
     */
    public function saveClassMarks(int $examId, int $subjectId, array $marksData): array {
        if ($examId <= 0 || $subjectId <= 0 || empty($marksData)) {
            return ['status' => false, 'message' => 'Invalid exam, subject, or no data provided.'];
        }

        $subject = Subject::findById($subjectId);
        if (!$subject) {
            return ['status' => false, 'message' => 'Subject not found.'];
        }
        
        $totalMarks = (float)$subject['total_marks'];
        $successCount = 0;
        $errorCount = 0;

        try {
            $db = Database::getConnection();
            $db->beginTransaction();

            foreach ($marksData as $studentId => $data) {
                $status = sanitize($data['status'] ?? 'Present');
                $marksObtained = 0;
                
                if ($status === 'Present') {
                    $marksObtained = (float)($data['marks'] ?? 0);
                    if ($marksObtained < 0) $marksObtained = 0;
                    if ($marksObtained > $totalMarks) $marksObtained = $totalMarks;
                }

                $ok = Result::saveMarks([
                    'exam_id'        => $examId,
                    'student_id'     => $studentId,
                    'subject_id'     => $subjectId,
                    'marks_obtained' => $marksObtained,
                    'remarks'        => sanitize($data['remarks'] ?? ''),
                    'status'         => $status
                ]);

                if ($ok) $successCount++;
                else $errorCount++;
            }

            $db->commit();
            auditLog('Marks Saved', "Marks saved for Exam ID $examId, Subject ID $subjectId. Success: $successCount, Errors: $errorCount");
            
            return [
                'status' => true, 
                'message' => "Marks saved successfully. ($successCount updated)"
            ];

        } catch (Exception $e) {
            if (isset($db)) $db->rollBack();
            error_log("ExamService::saveClassMarks error: " . $e->getMessage());
            return ['status' => false, 'message' => 'Failed to save marks due to system error.'];
        }
    }

    /**
     * Determine Grade and GPA based on percentage
     *
     * @param float $percentage
     * @return array ['grade' => 'A', 'gpa' => '4.0']
     */
    public static function getGrading(float $percentage): array {
        if ($percentage >= 80) return ['grade' => 'A+', 'gpa' => 4.0];
        if ($percentage >= 70) return ['grade' => 'A',  'gpa' => 3.5];
        if ($percentage >= 60) return ['grade' => 'B',  'gpa' => 3.0];
        if ($percentage >= 50) return ['grade' => 'C',  'gpa' => 2.5];
        if ($percentage >= 40) return ['grade' => 'D',  'gpa' => 2.0];
        return ['grade' => 'F', 'gpa' => 0.0];
    }
}
