<?php
/**
 * Indus Grammar School ERP - Fee Model
 * Version 1.0.0
 */

class Fee {

    // ── Fee Structures ──────────────────────────────────────

    public static function allStructures(string $academicYear = ''): array {
        try {
            $db = Database::getConnection();
            $year = $academicYear ?: CURRENT_ACADEMIC_YEAR;
            $stmt = $db->prepare("
                SELECT fs.*, c.class_name, c.section
                FROM fee_structures fs
                JOIN classes c ON fs.class_id = c.id
                WHERE fs.academic_year = :year
                ORDER BY c.class_name ASC, c.section ASC, fs.fee_type ASC
            ");
            $stmt->execute(['year' => $year]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Fee::allStructures error: " . $e->getMessage());
            return [];
        }
    }

    public static function getStructuresByClass(int $classId, string $academicYear = ''): array {
        try {
            $db   = Database::getConnection();
            $year = $academicYear ?: CURRENT_ACADEMIC_YEAR;
            $stmt = $db->prepare("SELECT * FROM fee_structures WHERE class_id = :cid AND academic_year = :year");
            $stmt->execute(['cid' => $classId, 'year' => $year]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Fee::getStructuresByClass error: " . $e->getMessage());
            return [];
        }
    }

    public static function createStructure(array $data): int|bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO fee_structures (class_id, fee_type, amount, academic_year)
                VALUES (:cid, :type, :amount, :year)
                ON DUPLICATE KEY UPDATE amount = VALUES(amount)
            ");
            $stmt->execute([
                'cid'    => $data['class_id'],
                'type'   => $data['fee_type'],
                'amount' => $data['amount'],
                'year'   => $data['academic_year'] ?? CURRENT_ACADEMIC_YEAR,
            ]);
            return (int)$db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Fee::createStructure error: " . $e->getMessage());
            return false;
        }
    }

    public static function deleteStructure(int $id): bool {
        try {
            $db = Database::getConnection();
            return $db->prepare("DELETE FROM fee_structures WHERE id = :id")->execute(['id' => $id]);
        } catch (PDOException $e) {
            error_log("Fee::deleteStructure error: " . $e->getMessage());
            return false;
        }
    }

    // ── Challans ─────────────────────────────────────────────

    public static function allChallans(array $filters = [], int $limit = 20, int $offset = 0): array {
        try {
            $db = Database::getConnection();
            $sql = "
                SELECT fc.*, s.first_name, s.last_name, s.admission_no,
                       c.class_name, c.section
                FROM fee_challans fc
                JOIN students s ON fc.student_id = s.id
                JOIN classes c ON s.class_id = c.id
                WHERE 1=1
            ";
            $params = [];
            if (!empty($filters['status'])) { $sql .= " AND fc.status = :status"; $params['status'] = $filters['status']; }
            if (!empty($filters['student_id'])) { $sql .= " AND fc.student_id = :sid"; $params['sid'] = $filters['student_id']; }
            if (!empty($filters['search'])) {
                $sql .= " AND (s.first_name LIKE :q OR s.last_name LIKE :q OR s.admission_no LIKE :q OR fc.challan_no LIKE :q)";
                $params['q'] = '%'.$filters['search'].'%';
            }
            $sql .= " ORDER BY fc.created_at DESC LIMIT :lim OFFSET :off";
            $stmt = $db->prepare($sql);
            foreach ($params as $k => $v) $stmt->bindValue($k, $v);
            $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
            $stmt->bindValue('off', $offset, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Fee::allChallans error: " . $e->getMessage());
            return [];
        }
    }

    public static function findChallanById(int $id): array|bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT fc.*, s.first_name, s.last_name, s.admission_no, c.class_name, c.section
                FROM fee_challans fc
                JOIN students s ON fc.student_id = s.id
                JOIN classes c ON s.class_id = c.id
                WHERE fc.id = :id
            ");
            $stmt->execute(['id' => $id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Fee::findChallanById error: " . $e->getMessage());
            return false;
        }
    }

    public static function getChallanItems(int $challanId): array {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT * FROM fee_challan_items WHERE challan_id = :cid");
            $stmt->execute(['cid' => $challanId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    public static function generateChallanNumber(): string {
        try {
            $db   = Database::getConnection();
            $stmt = $db->query("SELECT MAX(id) as max_id FROM fee_challans");
            $row  = $stmt->fetch();
            $next = ($row['max_id'] ?? 0) + 1;
            return sprintf("IGS-FEE-%s-%04d", date('Y'), $next);
        } catch (Exception $e) {
            return "IGS-FEE-" . date('Y') . "-" . rand(1000, 9999);
        }
    }

    public static function createChallan(array $data, array $items): int|bool {
        try {
            $db = Database::getConnection();
            $db->beginTransaction();
            $stmt = $db->prepare("
                INSERT INTO fee_challans
                    (student_id, challan_no, month, academic_year, total_amount, discount_amount, fine_amount, net_amount, due_date, status, generated_by)
                VALUES
                    (:sid, :cno, :month, :year, :total, :disc, :fine, :net, :due, 'Unpaid', :gen)
            ");
            $stmt->execute([
                'sid'   => $data['student_id'],
                'cno'   => $data['challan_no'],
                'month' => $data['month'],
                'year'  => $data['academic_year'],
                'total' => $data['total_amount'],
                'disc'  => $data['discount_amount'] ?? 0,
                'fine'  => $data['fine_amount'] ?? 0,
                'net'   => $data['net_amount'],
                'due'   => $data['due_date'],
                'gen'   => $_SESSION['user_id'] ?? null,
            ]);
            $challanId = (int)$db->lastInsertId();
            $iStmt = $db->prepare("INSERT INTO fee_challan_items (challan_id, fee_type, amount) VALUES (:cid, :type, :amt)");
            foreach ($items as $item) {
                $iStmt->execute(['cid' => $challanId, 'type' => $item['fee_type'], 'amt' => $item['amount']]);
            }
            $db->commit();
            return $challanId;
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Fee::createChallan error: " . $e->getMessage());
            return false;
        }
    }

    // ── Collections (Payments) ────────────────────────────────

    public static function allCollections(array $filters = [], int $limit = 20, int $offset = 0): array {
        try {
            $db = Database::getConnection();
            $sql = "
                SELECT fc.*, s.first_name, s.last_name, s.admission_no
                FROM fee_collections fc
                JOIN students s ON fc.student_id = s.id
                WHERE 1=1
            ";
            $params = [];
            if (!empty($filters['student_id'])) { $sql .= " AND fc.student_id = :sid"; $params['sid'] = $filters['student_id']; }
            if (!empty($filters['from'])) { $sql .= " AND fc.payment_date >= :from"; $params['from'] = $filters['from']; }
            if (!empty($filters['to']))   { $sql .= " AND fc.payment_date <= :to";   $params['to']   = $filters['to']; }
            $sql .= " ORDER BY fc.created_at DESC LIMIT :lim OFFSET :off";
            $stmt = $db->prepare($sql);
            foreach ($params as $k => $v) $stmt->bindValue($k, $v);
            $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
            $stmt->bindValue('off', $offset, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Fee::allCollections error: " . $e->getMessage());
            return [];
        }
    }

    public static function generateReceiptNumber(): string {
        try {
            $db = Database::getConnection();
            $row = $db->query("SELECT MAX(id) as max_id FROM fee_collections")->fetch();
            $next = ($row['max_id'] ?? 0) + 1;
            return sprintf("RCP-%s-%04d", date('Y'), $next);
        } catch (Exception $e) {
            return "RCP-" . date('Y') . "-" . rand(1000, 9999);
        }
    }

    public static function recordPayment(array $data): int|bool {
        try {
            $db = Database::getConnection();
            $db->beginTransaction();
            // Insert payment
            $stmt = $db->prepare("
                INSERT INTO fee_collections (challan_id, student_id, amount_paid, payment_date, payment_method, receipt_no, collected_by, remarks)
                VALUES (:cid, :sid, :amt, :dt, :method, :rno, :col, :remarks)
            ");
            $stmt->execute([
                'cid'     => $data['challan_id'] ?? null,
                'sid'     => $data['student_id'],
                'amt'     => $data['amount_paid'],
                'dt'      => $data['payment_date'],
                'method'  => $data['payment_method'],
                'rno'     => $data['receipt_no'],
                'col'     => $_SESSION['user_id'] ?? null,
                'remarks' => $data['remarks'] ?? null,
            ]);
            $receiptId = (int)$db->lastInsertId();

            // Update challan status
            if (!empty($data['challan_id'])) {
                $db->prepare("UPDATE fee_challans SET status = 'Paid', updated_at = NOW() WHERE id = :id")
                   ->execute(['id' => $data['challan_id']]);
            }

            // Update cash register
            $db->prepare("
                UPDATE cash_register SET total_collections = total_collections + :amt
                WHERE date = CURDATE() AND status = 'Open'
            ")->execute(['amt' => $data['amount_paid']]);

            $db->commit();
            return $receiptId;
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Fee::recordPayment error: " . $e->getMessage());
            return false;
        }
    }

    // ── Discounts ──────────────────────────────────────────────

    public static function getDiscountsByStudent(int $studentId): array {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT * FROM fee_discounts WHERE student_id = :sid AND is_active = 1");
            $stmt->execute(['sid' => $studentId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    // ── Fines ─────────────────────────────────────────────────

    public static function getFinesByStudent(int $studentId): array {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT * FROM fee_fines WHERE student_id = :sid AND status = 'Pending'");
            $stmt->execute(['sid' => $studentId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    // ── Stats / Reports ───────────────────────────────────────

    public static function getMonthlyStats(): array {
        try {
            $db = Database::getConnection();
            $stmt = $db->query("
                SELECT
                    SUM(net_amount) as total_billed,
                    SUM(CASE WHEN status='Paid' THEN net_amount ELSE 0 END) as collected,
                    SUM(CASE WHEN status IN('Unpaid','Overdue') THEN net_amount ELSE 0 END) as outstanding,
                    COUNT(*) as total_challans,
                    SUM(status='Paid') as paid_count,
                    SUM(status IN('Unpaid','Overdue')) as unpaid_count
                FROM fee_challans
                WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())
            ");
            return $stmt->fetch() ?: [];
        } catch (PDOException $e) {
            error_log("Fee::getMonthlyStats error: " . $e->getMessage());
            return [];
        }
    }

    public static function getTotalCollected(): float {
        try {
            $db   = Database::getConnection();
            $stmt = $db->query("SELECT SUM(amount_paid) FROM fee_collections");
            return (float)($stmt->fetchColumn() ?? 0);
        } catch (PDOException $e) {
            return 0.0;
        }
    }
}
