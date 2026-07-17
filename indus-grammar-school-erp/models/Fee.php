<?php
/**
 * Indus Grammar School ERP - Normalized Fee Data Model
 * Version 4.0.0
 */

class Fee {

    // ── Class Fee Structures (Academic configurations) ──

    public static function allStructures($filters = []) {
        try {
            $db = Database::getConnection();
            $where = "WHERE 1=1";
            $params = [];
            
            if (!empty($filters['academic_type'])) {
                $where .= " AND fs.academic_type = :type";
                $params['type'] = $filters['academic_type'];
            }
            if (!empty($filters['class_id'])) {
                $where .= " AND fs.class_id = :class_id";
                $params['class_id'] = (int)$filters['class_id'];
            }
            if (!empty($filters['academic_year'])) {
                $where .= " AND fs.academic_year = :year";
                $params['year'] = $filters['academic_year'];
            }

            $stmt = $db->prepare("
                SELECT fs.*, c.class_name, c.section 
                FROM fee_structure fs
                LEFT JOIN classes c ON fs.class_id = c.id
                $where
                ORDER BY fs.academic_year DESC, c.class_name ASC, c.section ASC
            ");
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Fee::allStructures error: " . $e->getMessage());
            return [];
        }
    }

    public static function getStructuresByClass($classId, $academicType, $year = CURRENT_ACADEMIC_YEAR) {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT * FROM fee_structure 
                WHERE class_id = :cid AND academic_type = :type AND academic_year = :year 
                LIMIT 1
            ");
            $stmt->execute(['cid' => $classId, 'type' => $academicType, 'year' => $year]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Fee::getStructuresByClass error: " . $e->getMessage());
            return false;
        }
    }

    public static function createStructure($data) {
        try {
            $db = Database::getConnection();
            
            // Insert or Update on duplicate class/year keys
            $stmt = $db->prepare("
                INSERT INTO fee_structure 
                (academic_type, class_id, admission_fee, tuition_fee, computer_fee, exam_fee, transport_fee, annual_charges, security_deposit, other_charges, status, academic_year)
                VALUES 
                (:type, :cid, :adm, :tui, :comp, :exam, :trans, :ann, :sec, :oth, :status, :year)
                ON DUPLICATE KEY UPDATE
                    admission_fee = VALUES(admission_fee),
                    tuition_fee = VALUES(tuition_fee),
                    computer_fee = VALUES(computer_fee),
                    exam_fee = VALUES(exam_fee),
                    transport_fee = VALUES(transport_fee),
                    annual_charges = VALUES(annual_charges),
                    security_deposit = VALUES(security_deposit),
                    other_charges = VALUES(other_charges),
                    status = VALUES(status)
            ");
            
            return $stmt->execute([
                'type'   => $data['academic_type'],
                'cid'    => $data['class_id'],
                'adm'    => $data['admission_fee'] ?? 0.00,
                'tui'    => $data['tuition_fee'],
                'comp'   => $data['computer_fee'] ?? 0.00,
                'exam'   => $data['exam_fee'] ?? 0.00,
                'trans'  => $data['transport_fee'] ?? 0.00,
                'ann'    => $data['annual_charges'] ?? 0.00,
                'sec'    => $data['security_deposit'] ?? 0.00,
                'oth'    => $data['other_charges'] ?? 0.00,
                'status' => $data['status'] ?? 'Active',
                'year'   => $data['academic_year'] ?? CURRENT_ACADEMIC_YEAR
            ]);
        } catch (PDOException $e) {
            error_log("Fee::createStructure error: " . $e->getMessage());
            return false;
        }
    }

    public static function deleteStructure($id) {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("DELETE FROM fee_structure WHERE id = :id");
            return $stmt->execute(['id' => $id]);
        } catch (PDOException $e) {
            error_log("Fee::deleteStructure error: " . $e->getMessage());
            return false;
        }
    }

    // ── Student Fee Assignments (Concessions/Discounts links) ──

    public static function getStudentAssignment($studentId) {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT sfa.*, fs.admission_fee, fs.tuition_fee, fs.computer_fee, fs.exam_fee, 
                       fs.transport_fee, fs.annual_charges, fs.security_deposit, fs.other_charges,
                       fs.academic_type, fs.class_id, fs.academic_year
                FROM student_fee_assignments sfa
                JOIN fee_structure fs ON sfa.fee_structure_id = fs.id
                WHERE sfa.student_id = :sid
            ");
            $stmt->execute(['sid' => $studentId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Fee::getStudentAssignment error: " . $e->getMessage());
            return false;
        }
    }

    public static function ensureStudentAssignment($studentId) {
        try {
            $db = Database::getConnection();
            
            $stmt = $db->prepare("SELECT id FROM student_fee_assignments WHERE student_id = :sid");
            $stmt->execute(['sid' => $studentId]);
            if ($stmt->fetchColumn()) {
                return true; // Already assigned
            }
            
            $stStmt = $db->prepare("SELECT class_id, academic_type FROM students WHERE id = :sid");
            $stStmt->execute(['sid' => $studentId]);
            $st = $stStmt->fetch();
            if (!$st) return false;
            
            $fsStmt = $db->prepare("
                SELECT id FROM fee_structure 
                WHERE class_id = :cid AND academic_type = :type AND academic_year = :year LIMIT 1
            ");
            $fsStmt->execute(['cid' => $st['class_id'], 'type' => $st['academic_type'], 'year' => CURRENT_ACADEMIC_YEAR]);
            $fid = $fsStmt->fetchColumn();
            
            if (!$fid) {
                // Automatically initialize a default zero-rate structure for this class/type/year
                $insFs = $db->prepare("
                    INSERT INTO fee_structure (academic_type, class_id, academic_year, admission_fee, tuition_fee, computer_fee, exam_fee, transport_fee, annual_charges, security_deposit, other_charges, status)
                    VALUES (:type, :cid, :year, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Active')
                ");
                $insFs->execute(['type' => $st['academic_type'], 'cid' => $st['class_id'], 'year' => CURRENT_ACADEMIC_YEAR]);
                $fid = (int)$db->lastInsertId();
            }
            
            $ins = $db->prepare("
                INSERT INTO student_fee_assignments (student_id, fee_structure_id, discount_percentage, discount_flat, discount_reason, status)
                VALUES (:sid, :fid, 0.00, 0.00, '', 'Active')
            ");
            return $ins->execute(['sid' => $studentId, 'fid' => $fid]);
        } catch (Exception $e) {
            error_log("Fee::ensureStudentAssignment error: " . $e->getMessage());
            return false;
        }
    }

    public static function assignFeeToStudent($data) {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO student_fee_assignments 
                (student_id, fee_structure_id, discount_percentage, discount_flat, discount_reason, status)
                VALUES (:sid, :fid, :pct, :flat, :reason, :status)
                ON DUPLICATE KEY UPDATE 
                  fee_structure_id = VALUES(fee_structure_id),
                  discount_percentage = VALUES(discount_percentage),
                  discount_flat = VALUES(discount_flat),
                  discount_reason = VALUES(discount_reason),
                  status = VALUES(status)
            ");
            return $stmt->execute([
                'sid'    => $data['student_id'],
                'fid'    => $data['fee_structure_id'],
                'pct'    => $data['discount_percentage'] ?? 0.00,
                'flat'   => $data['discount_flat'] ?? 0.00,
                'reason' => $data['discount_reason'] ?? '',
                'status' => $data['status'] ?? 'Active'
            ]);
        } catch (PDOException $e) {
            error_log("Fee::assignFeeToStudent error: " . $e->getMessage());
            return false;
        }
    }

    // ── Monthly Fee Ledger Operations ──

    public static function allChallans($filters = [], $limit = 20, $offset = 0) {
        try {
            $db = Database::getConnection();
            $where = "WHERE 1=1";
            $params = [];

            if (!empty($filters['status'])) {
                $where .= " AND fl.status = :status";
                $params['status'] = $filters['status'];
            }
            if (!empty($filters['academic_type'])) {
                $where .= " AND s.academic_type = :type";
                $params['type'] = $filters['academic_type'];
            }
            if (!empty($filters['class_id'])) {
                $where .= " AND s.class_id = :class_id";
                $params['class_id'] = (int)$filters['class_id'];
            }
            if (!empty($filters['month'])) {
                $where .= " AND fl.month = :month";
                $params['month'] = $filters['month'];
            }
            if (!empty($filters['search'])) {
                $where .= " AND (s.first_name LIKE :q OR s.last_name LIKE :q OR s.admission_no LIKE :q)";
                $params['q'] = '%' . $filters['search'] . '%';
            }

            $stmt = $db->prepare("
                SELECT fl.*, s.first_name, s.last_name, s.admission_no, s.academic_type, c.class_name, c.section, d.father_name
                FROM fee_ledger fl
                JOIN students s ON fl.student_id = s.id
                LEFT JOIN classes c ON s.class_id = c.id
                LEFT JOIN student_registration_details d ON s.id = d.student_id
                $where
                ORDER BY fl.due_date DESC, fl.id DESC
                LIMIT :limit OFFSET :offset
            ");

            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Fee::allChallans error: " . $e->getMessage());
            return [];
        }
    }

    public static function countChallans($filters = []) {
        try {
            $db = Database::getConnection();
            $where = "WHERE 1=1";
            $params = [];

            if (!empty($filters['status'])) {
                $where .= " AND fl.status = :status";
                $params['status'] = $filters['status'];
            }
            if (!empty($filters['academic_type'])) {
                $where .= " AND s.academic_type = :type";
                $params['type'] = $filters['academic_type'];
            }
            if (!empty($filters['class_id'])) {
                $where .= " AND s.class_id = :class_id";
                $params['class_id'] = (int)$filters['class_id'];
            }
            if (!empty($filters['month'])) {
                $where .= " AND fl.month = :month";
                $params['month'] = $filters['month'];
            }
            if (!empty($filters['search'])) {
                $where .= " AND (s.first_name LIKE :q OR s.last_name LIKE :q OR s.admission_no LIKE :q)";
                $params['q'] = '%' . $filters['search'] . '%';
            }

            $stmt = $db->prepare("
                SELECT COUNT(*)
                FROM fee_ledger fl
                JOIN students s ON fl.student_id = s.id
                $where
            ");
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Fee::countChallans error: " . $e->getMessage());
            return 0;
        }
    }

    public static function getLedgerForStudent($studentId) {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT * FROM fee_ledger WHERE student_id = :sid ORDER BY due_date ASC");
            $stmt->execute(['sid' => $studentId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Fee::getLedgerForStudent error: " . $e->getMessage());
            return [];
        }
    }

    public static function getLedgerById($id) {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT fl.*, s.first_name, s.last_name, s.admission_no, s.academic_type, c.class_name, c.section, d.father_name, d.roll_no
                FROM fee_ledger fl
                JOIN students s ON fl.student_id = s.id
                LEFT JOIN classes c ON s.class_id = c.id
                LEFT JOIN student_registration_details d ON s.id = d.student_id
                WHERE fl.id = :id
            ");
            $stmt->execute(['id' => $id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Fee::getLedgerById error: " . $e->getMessage());
            return false;
        }
    }

    // ── Atomic Payment Record Transaction ──

    public static function recordPayment($data) {
        $db = Database::getConnection();
        try {
            $db->beginTransaction();
            
            // 1. Fetch ledger row
            $stmt = $db->prepare("SELECT * FROM fee_ledger WHERE id = :id FOR UPDATE");
            $stmt->execute(['id' => $data['ledger_id']]);
            $ledger = $stmt->fetch();
            if (!$ledger) {
                throw new Exception("Ledger month record not found.");
            }
            
            $newPaid = (float)$ledger['paid_amount'] + (float)$data['amount_paid'];
            
            // Determine status
            $status = 'Pending';
            if ($newPaid >= (float)$ledger['total_payable']) {
                $status = 'Paid';
            } elseif ($newPaid > 0) {
                $status = 'Partial';
            }
            
            // 2. Update fee_ledger status and paid totals
            $upStmt = $db->prepare("
                UPDATE fee_ledger 
                SET paid_amount = :paid, status = :status 
                WHERE id = :id
            ");
            $upStmt->execute([
                'paid'   => $newPaid,
                'status' => $status,
                'id'     => $ledger['id']
            ]);
            
            // 3. Register cash register context if enabled
            $cashRegId = $data['cash_register_id'] ?? null;
            if (empty($cashRegId) && class_exists('Cash')) {
                $activeReg = Cash::getActiveRegister();
                if ($activeReg) {
                    $cashRegId = $activeReg['id'];
                } else {
                    $newId = Cash::openRegister(0.00, 'System Auto-Init');
                    if ($newId > 0) {
                        $cashRegId = $newId;
                    } else {
                        $activeReg = Cash::getActiveRegister();
                        if ($activeReg) {
                            $cashRegId = $activeReg['id'];
                        }
                    }
                }
            }

            // 4. Save to payments log
            $payStmt = $db->prepare("
                INSERT INTO fee_payments 
                (ledger_id, student_id, amount_paid, payment_date, payment_method, reference_number, remarks, cash_register_id, collected_by)
                VALUES (:lid, :sid, :amt, :pdate, :method, :ref, :remarks, :reg, :by)
            ");
            $payStmt->execute([
                'lid'     => $ledger['id'],
                'sid'     => $ledger['student_id'],
                'amt'     => $data['amount_paid'],
                'pdate'   => $data['payment_date'],
                'method'  => $data['payment_method'],
                'ref'     => $data['reference_number'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'reg'     => $cashRegId,
                'by'      => $_SESSION['user_id'] ?? null
            ]);
            
            $paymentId = $db->lastInsertId();
            
            // 5. Generate professional receipt: REC-YYYYMMDD-ID
            $receiptNo = 'REC-' . date('Ymd') . '-' . str_pad($paymentId, 5, '0', STR_PAD_LEFT);
            $recStmt = $db->prepare("
                INSERT INTO fee_receipts (receipt_no, payment_id) 
                VALUES (:rno, :pid)
            ");
            $recStmt->execute([
                'rno' => $receiptNo,
                'pid' => $paymentId
            ]);
            
            
            if ($cashRegId && class_exists('Cash') && $data['payment_method'] === 'Cash') {
                Cash::recalculateRegister($cashRegId);
            }

            // Auto-post to income table
            $incStmt = $db->prepare("
                INSERT INTO income (income_date, source, reference_no, student_id, description, amount, payment_method, received_by, remarks)
                VALUES (:idate, 'Student Fee Collection', :ref, :sid, :desc, :amt, :method, :by, :remarks)
            ");
            $incStmt->execute([
                'idate'   => $data['payment_date'],
                'ref'     => $receiptNo,
                'sid'     => $ledger['student_id'],
                'desc'    => "Fee collection for month: " . $ledger['month'] . " (" . $ledger['academic_year'] . ")",
                'amt'     => $data['amount_paid'],
                'method'  => $data['payment_method'],
                'by'      => $_SESSION['user_id'] ?? null,
                'remarks' => $data['remarks'] ?? null
            ]);
            
            $db->commit();
            return [
                'status'     => true,
                'receipt_no' => $receiptNo,
                'payment_id' => $paymentId
            ];
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Fee::recordPayment exception: " . $e->getMessage());
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    // ── Cashier Dashboard Stats (Auto counters) ──

    public static function getCashierDashboard(): array {
        try {
            $db = Database::getConnection();
            
            // 1. Today Collection (Cashier payment logs today)
            $todayColl = (float)$db->query("
                SELECT COALESCE(SUM(amount_paid), 0) FROM fee_payments WHERE payment_date = CURDATE()
            ")->fetchColumn();
            
            // 2. Today Pending (unpaid/partial ledger rows due on or before today)
            $todayPending = (float)$db->query("
                SELECT COALESCE(SUM(total_payable - paid_amount), 0) FROM fee_ledger 
                WHERE status IN ('Pending', 'Partial') AND due_date <= CURDATE()
            ")->fetchColumn();
            
            // 3. Monthly Collection
            $monthColl = (float)$db->query("
                SELECT COALESCE(SUM(amount_paid), 0) FROM fee_payments 
                WHERE MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())
            ")->fetchColumn();
            
            // 4. Outstanding Dues (total unpaid/partial balance overall)
            $outstanding = (float)$db->query("
                SELECT COALESCE(SUM(total_payable - paid_amount), 0) FROM fee_ledger 
                WHERE status IN ('Pending', 'Partial')
            ")->fetchColumn();
            
            $currentMonthStr = date('F Y');
            $paidStudents = $db->prepare("
                SELECT COUNT(DISTINCT student_id) FROM fee_ledger 
                WHERE status = 'Paid' AND month = :month
            ");
            $paidStudents->execute(['month' => $currentMonthStr]);
            $paidCount = (int)$paidStudents->fetchColumn();
            
            // 6. Pending Students count (currently outstanding)
            $pendingStudents = (int)$db->query("
                SELECT COUNT(DISTINCT student_id) FROM fee_ledger 
                WHERE status IN ('Pending', 'Partial')
            ")->fetchColumn();
            
            return [
                'today_collection'   => $todayColl,
                'today_pending'      => $todayPending,
                'monthly_collection' => $monthColl,
                'outstanding_dues'   => $outstanding,
                'paid_students'      => $paidCount,
                'pending_students'   => $pendingStudents
            ];
        } catch (Exception $e) {
            error_log("getCashierDashboard V2 error: " . $e->getMessage());
            return [
                'today_collection'   => 0,
                'today_pending'      => 0,
                'monthly_collection' => 0,
                'outstanding_dues'   => 0,
                'paid_students'      => 0,
                'pending_students'   => 0
            ];
        }
    }

    // ── Payment Receipt collections log ──

    public static function allCollections($filters = [], $limit = 20, $offset = 0) {
        try {
            $db = Database::getConnection();
            $where = "WHERE 1=1";
            $params = [];
            
            if (!empty($filters['from'])) {
                $where .= " AND fp.payment_date >= :from";
                $params['from'] = $filters['from'];
            }
            if (!empty($filters['to'])) {
                $where .= " AND fp.payment_date <= :to";
                $params['to'] = $filters['to'];
            }
            if (!empty($filters['payment_method'])) {
                $where .= " AND fp.payment_method = :method";
                $params['method'] = $filters['payment_method'];
            }
            if (!empty($filters['search'])) {
                $where .= " AND (fr.receipt_no LIKE :q OR s.first_name LIKE :q OR s.last_name LIKE :q OR s.admission_no LIKE :q)";
                $params['q'] = '%' . $filters['search'] . '%';
            }
            
            $stmt = $db->prepare("
                SELECT fp.*, fr.receipt_no, s.first_name, s.last_name, s.admission_no, fl.month
                FROM fee_payments fp
                JOIN fee_receipts fr ON fr.payment_id = fp.id
                JOIN students s ON fp.student_id = s.id
                JOIN fee_ledger fl ON fp.ledger_id = fl.id
                $where
                ORDER BY fp.created_at DESC
                LIMIT :limit OFFSET :offset
            ");
            
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Fee::allCollections error: " . $e->getMessage());
            return [];
        }
    }

    public static function countCollections($filters = []) {
        try {
            $db = Database::getConnection();
            $where = "WHERE 1=1";
            $params = [];
            
            if (!empty($filters['from'])) {
                $where .= " AND fp.payment_date >= :from";
                $params['from'] = $filters['from'];
            }
            if (!empty($filters['to'])) {
                $where .= " AND fp.payment_date <= :to";
                $params['to'] = $filters['to'];
            }
            if (!empty($filters['payment_method'])) {
                $where .= " AND fp.payment_method = :method";
                $params['method'] = $filters['payment_method'];
            }
            if (!empty($filters['search'])) {
                $where .= " AND (fr.receipt_no LIKE :q OR s.first_name LIKE :q OR s.last_name LIKE :q OR s.admission_no LIKE :q)";
                $params['q'] = '%' . $filters['search'] . '%';
            }
            
            $stmt = $db->prepare("
                SELECT COUNT(*)
                FROM fee_payments fp
                JOIN fee_receipts fr ON fr.payment_id = fp.id
                JOIN students s ON fp.student_id = s.id
                JOIN fee_ledger fl ON fp.ledger_id = fl.id
                $where
            ");
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Fee::countCollections error: " . $e->getMessage());
            return 0;
        }
    }

    public static function getSummaryStats($from, $to) {
        try {
            $db = Database::getConnection();
            
            $stmt = $db->prepare("SELECT COALESCE(SUM(amount_paid), 0) FROM fee_payments WHERE payment_date = CURDATE()");
            $stmt->execute();
            $todayColl = (float)$stmt->fetchColumn();
            
            $stmt = $db->prepare("SELECT COALESCE(SUM(amount_paid), 0) FROM fee_payments WHERE payment_date BETWEEN :from AND :to");
            $stmt->execute(['from' => $from, 'to' => $to]);
            $rangeColl = (float)$stmt->fetchColumn();
            
            $stmt = $db->prepare("SELECT COALESCE(SUM(total_payable - paid_amount), 0) FROM fee_ledger WHERE status IN ('Pending', 'Partial')");
            $stmt->execute();
            $pendingDues = (float)$stmt->fetchColumn();
            
            $paidCount = (int)$db->query("SELECT COUNT(DISTINCT student_id) FROM fee_ledger WHERE status = 'Paid'")->fetchColumn();
            $unpaidCount = (int)$db->query("SELECT COUNT(DISTINCT student_id) FROM fee_ledger WHERE status IN ('Pending', 'Partial')")->fetchColumn();
            
            $stmt = $db->prepare("SELECT COALESCE(SUM(discount_amount), 0) FROM fee_ledger WHERE created_at BETWEEN :from AND :to");
            $stmt->execute(['from' => $from . ' 00:00:00', 'to' => $to . ' 23:59:59']);
            $discountGiven = (float)$stmt->fetchColumn();
            
            $stmt = $db->prepare("SELECT COALESCE(SUM(fine_amount), 0) FROM fee_ledger WHERE status = 'Paid' AND updated_at BETWEEN :from AND :to");
            $stmt->execute(['from' => $from . ' 00:00:00', 'to' => $to . ' 23:59:59']);
            $fineCollected = (float)$stmt->fetchColumn();
            
            return [
                'today_collection'    => $todayColl,
                'monthly_collection'  => $rangeColl,
                'pending_dues'        => $pendingDues,
                'students_paid'       => $paidCount,
                'students_unpaid'     => $unpaidCount,
                'discount_given'      => $discountGiven,
                'fine_collected'      => $fineCollected
            ];
        } catch (PDOException $e) {
            error_log("Fee::getSummaryStats error: " . $e->getMessage());
            return [
                'today_collection'    => 0,
                'monthly_collection'  => 0,
                'pending_dues'        => 0,
                'students_paid'       => 0,
                'students_unpaid'     => 0,
                'discount_given'      => 0,
                'fine_collected'      => 0
            ];
        }
    }

    // ── Global Fine Parameters settings ──

    public static function getSettings() {
        try {
            $db = Database::getConnection();
            $stmt = $db->query("SELECT * FROM fee_settings ORDER BY id DESC LIMIT 1");
            $settings = $stmt->fetch();
            if (!$settings) {
                return [
                    'late_fine_amount' => 100.00,
                    'grace_days'       => 5,
                    'fine_type'        => 'Fixed'
                ];
            }
            return $settings;
        } catch (PDOException $e) {
            error_log("Fee::getSettings error: " . $e->getMessage());
            return [
                'late_fine_amount' => 100.00,
                'grace_days'       => 5,
                'fine_type'        => 'Fixed'
            ];
        }
    }

    public static function updateSettings($data) {
        try {
            $db = Database::getConnection();
            
            // Delete old settings and insert new one
            $db->exec("DELETE FROM fee_settings");
            $stmt = $db->prepare("
                INSERT INTO fee_settings (late_fine_amount, grace_days, fine_type)
                VALUES (:amt, :grace, :type)
            ");
            return $stmt->execute([
                'amt'   => $data['late_fine_amount'],
                'grace' => $data['grace_days'],
                'type'  => $data['fine_type']
            ]);
        } catch (PDOException $e) {
            error_log("Fee::updateSettings error: " . $e->getMessage());
            return false;
        }
    }
}
