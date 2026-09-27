<?php
/**
 * Indus Grammar School ERP - Promotion Model
 * Version 4.0.0
 */

class Promotion {

    public static function all(string $session = ''): array {
        try {
            $db = Database::getConnection();
            $currSession = $session ?: CURRENT_ACADEMIC_YEAR;
            $stmt = $db->prepare("
                SELECT p.*, st.first_name, st.last_name, st.admission_no,
                       fc.class_name as from_class, fc.section as from_section,
                       tc.class_name as to_class, tc.section as to_section
                FROM promotions p
                JOIN students st ON p.student_id = st.id
                JOIN classes fc ON p.from_class_id = fc.id
                JOIN classes tc ON p.to_class_id = tc.id
                WHERE p.academic_session = :session
                ORDER BY p.id DESC
            ");
            $stmt->execute(['session' => $currSession]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Promotion::all error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Promote a batch of students to a new class (Optimized high-performance batch operation).
     */
    public static function promoteStudents(array $studentIds, int $fromClassId, int $toClassId, string $session): array {
        if (empty($studentIds) || $fromClassId <= 0 || $toClassId <= 0) {
            return ['success' => false, 'message' => 'Invalid parameters provided.'];
        }

        // Clean integer array
        $studentIds = array_map('intval', array_filter($studentIds));
        if (empty($studentIds)) {
            return ['success' => false, 'message' => 'No valid student IDs selected.'];
        }

        try {
            $db = Database::getConnection();
            $db->beginTransaction();

            $date = date('Y-m-d');
            $cleanSession = sanitize($session);
            $successCount = 0;

            // 1. Chunked Bulk Insert for Promotion Audit Log
            $chunks = array_chunk($studentIds, 250);
            foreach ($chunks as $chunk) {
                $placeholders = [];
                $params = [];
                foreach ($chunk as $sid) {
                    $placeholders[] = "(?, ?, ?, ?, ?, 'Promoted')";
                    array_push($params, $sid, $fromClassId, $toClassId, $cleanSession, $date);
                }
                $sqlLog = "INSERT INTO promotions (student_id, from_class_id, to_class_id, academic_session, promotion_date, status) VALUES " . implode(', ', $placeholders);
                $stmtLog = $db->prepare($sqlLog);
                $stmtLog->execute($params);

                // 2. Bulk UPDATE class reference in students table for this chunk
                $inClause = implode(',', array_fill(0, count($chunk), '?'));
                $sqlUpdate = "UPDATE students SET class_id = ? WHERE class_id = ? AND id IN ($inClause)";
                $stmtUpdate = $db->prepare($sqlUpdate);
                $updateParams = array_merge([$toClassId, $fromClassId], $chunk);
                $stmtUpdate->execute($updateParams);
                $successCount += $stmtUpdate->rowCount();
            }

            $db->commit();
            auditLog('Promotion Processed', "Promoted $successCount students from Class ID $fromClassId to $toClassId for session $session.");
            return ['success' => true, 'message' => "$successCount students successfully promoted to the next class."];

        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) $db->rollBack();
            error_log("Promotion::promoteStudents error: " . $e->getMessage());
            return ['success' => false, 'message' => 'System error processing promotion: ' . $e->getMessage()];
        }
    }

    /**
     * Reject or roll back promotion for a student (demote back).
     */
    public static function rollBackPromotion(int $promotionId): bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT * FROM promotions WHERE id = :id");
            $stmt->execute(['id' => $promotionId]);
            $promo = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$promo) return false;

            $db->beginTransaction();

            // Update student's class back to the original
            $stmtUpdate = $db->prepare("UPDATE students SET class_id = :from_class WHERE id = :sid AND class_id = :to_class");
            $stmtUpdate->execute([
                'from_class' => $promo['from_class_id'],
                'sid'        => $promo['student_id'],
                'to_class'   => $promo['to_class_id']
            ]);

            // Delete promotion log
            $db->prepare("DELETE FROM promotions WHERE id = :id")->execute(['id' => $promotionId]);

            $db->commit();
            auditLog('Promotion Rolled Back', "Reverted promotion for Student ID " . $promo['student_id']);
            return true;
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) $db->rollBack();
            error_log("Promotion::rollBackPromotion error: " . $e->getMessage());
            return false;
        }
    }
}
