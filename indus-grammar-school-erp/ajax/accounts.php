<?php
/**
 * Indus Grammar School ERP - Central Accounts AJAX Controller
 * Version 4.0.0
 */

require_once __DIR__ . '/../config/app.php';

// Authentication and Authorization Checks
if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized.'], 401);
}

// Restrict access to Super Admin, School Admin, and Accountant
$db = Database::getConnection();
$userRole = '';
try {
    $stmt = $db->prepare("SELECT code FROM roles WHERE id = ?");
    $stmt->execute([$_SESSION['role_id'] ?? 0]);
    $userRole = $stmt->fetchColumn() ?: '';
} catch (Exception $e) {}

$allowedRoles = ['super_admin', 'school_admin', 'accountant'];
if (!in_array($userRole, $allowedRoles)) {
    jsonResponse(['success' => false, 'message' => 'Access denied. Only administrators or accountants can perform these operations.'], 403);
}

// Secure GET endpoints (like Voucher Print or CSV exports if needed)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
}

// POST and CSRF Verification
if (!validateCsrf($_POST['csrf_token'] ?? '')) {
    jsonResponse(['success' => false, 'message' => 'Security token expired. Please refresh the page.'], 403);
}

$action = sanitize($_POST['action'] ?? '');
if (empty($action)) {
    jsonResponse(['success' => false, 'message' => 'No action specified.'], 400);
}

switch ($action) {

    // ── INCOME CRUD ───────────────────────────────────────────
    case 'add_income':
        $incomeDate    = sanitize($_POST['income_date'] ?? date('Y-m-d'));
        $source        = sanitize($_POST['source'] ?? '');
        $referenceNo   = sanitize($_POST['reference_no'] ?? '');
        $description   = sanitize($_POST['description'] ?? '');
        $amount        = (float)($_POST['amount'] ?? 0);
        $paymentMethod = sanitize($_POST['payment_method'] ?? 'Cash');
        $remarks       = sanitize($_POST['remarks'] ?? '');

        if (empty($source) || $amount <= 0) {
            jsonResponse(['success' => false, 'message' => 'Income source and a positive amount are required.'], 400);
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO income (income_date, source, reference_no, description, amount, payment_method, received_by, remarks)
                VALUES (:date, :source, :ref, :desc, :amt, :method, :by, :remarks)
            ");
            $ok = $stmt->execute([
                'date'   => $incomeDate,
                'source' => $source,
                'ref'    => $referenceNo ?: 'INC-' . date('Ymd') . '-' . rand(1000, 9999),
                'desc'   => $description,
                'amt'    => $amount,
                'method' => $paymentMethod,
                'by'     => $_SESSION['user_id'] ?? null,
                'remarks'=> $remarks
            ]);

            if ($ok) {
                // If Cash method and a daily register is open, recalculate
                if ($paymentMethod === 'Cash' && class_exists('Cash')) {
                    $activeReg = Cash::getActiveRegister();
                    if ($activeReg) {
                        Cash::recalculateRegister($activeReg['id']);
                    }
                }
                auditLog('Income Added', "Recorded manual income of Rs. " . number_format($amount, 2) . " from $source");
                jsonResponse(['success' => true, 'message' => 'Income record successfully registered.']);
            }
            jsonResponse(['success' => false, 'message' => 'Failed to register income entry.']);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'edit_income':
        $id            = (int)($_POST['id'] ?? 0);
        $incomeDate    = sanitize($_POST['income_date'] ?? '');
        $source        = sanitize($_POST['source'] ?? '');
        $referenceNo   = sanitize($_POST['reference_no'] ?? '');
        $description   = sanitize($_POST['description'] ?? '');
        $amount        = (float)($_POST['amount'] ?? 0);
        $paymentMethod = sanitize($_POST['payment_method'] ?? 'Cash');
        $remarks       = sanitize($_POST['remarks'] ?? '');

        if ($id <= 0 || empty($source) || $amount <= 0) {
            jsonResponse(['success' => false, 'message' => 'All mandatory parameters are required.'], 400);
        }

        try {
            $stmt = $db->prepare("
                UPDATE income 
                SET income_date = :date, source = :source, reference_no = :ref, description = :desc, amount = :amt, payment_method = :method, remarks = :remarks
                WHERE id = :id
            ");
            $ok = $stmt->execute([
                'date'   => $incomeDate,
                'source' => $source,
                'ref'    => $referenceNo,
                'desc'   => $description,
                'amt'    => $amount,
                'method' => $paymentMethod,
                'remarks'=> $remarks,
                'id'     => $id
            ]);

            if ($ok) {
                if (class_exists('Cash')) {
                    $activeReg = Cash::getActiveRegister();
                    if ($activeReg) {
                        Cash::recalculateRegister($activeReg['id']);
                    }
                }
                auditLog('Income Updated', "Modified income record ID #$id (New Amount: " . number_format($amount, 2) . ")");
                jsonResponse(['success' => true, 'message' => 'Income record successfully updated.']);
            }
            jsonResponse(['success' => false, 'message' => 'Failed to update income record.']);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'delete_income':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid ID.'], 400);
        }

        try {
            // Check if student fee collection (not editable/deletable from here)
            $check = $db->query("SELECT source, amount FROM income WHERE id = $id")->fetch();
            if ($check && $check['source'] === 'Student Fee Collection') {
                jsonResponse(['success' => false, 'message' => 'Automated student fee collection income records cannot be deleted manually.'], 400);
            }

            $stmt = $db->prepare("DELETE FROM income WHERE id = ?");
            $ok = $stmt->execute([$id]);

            if ($ok) {
                if (class_exists('Cash')) {
                    $activeReg = Cash::getActiveRegister();
                    if ($activeReg) {
                        Cash::recalculateRegister($activeReg['id']);
                    }
                }
                auditLog('Income Deleted', "Deleted manual income entry #$id");
                jsonResponse(['success' => true, 'message' => 'Income entry deleted successfully.']);
            }
            jsonResponse(['success' => false, 'message' => 'Failed to delete income record.']);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;


    // ── HOME EXPENSES CRUD ─────────────────────────────────────
    case 'add_expense':
        $expenseDate   = sanitize($_POST['expense_date'] ?? date('Y-m-d'));
        $categoryId    = (int)($_POST['category_id'] ?? 0);
        $title         = sanitize($_POST['title'] ?? '');
        $vendor        = sanitize($_POST['vendor_supplier'] ?? '');
        $invoiceNo     = sanitize($_POST['invoice_number'] ?? '');
        $amount        = (float)($_POST['amount'] ?? 0);
        $paymentMethod = sanitize($_POST['payment_method'] ?? 'Cash');
        $remarks       = sanitize($_POST['remarks'] ?? '');

        if ($categoryId <= 0 || empty($title) || $amount <= 0) {
            jsonResponse(['success' => false, 'message' => 'Title, category and positive amount are required.'], 400);
        }

        // File upload attachment
        $filepath = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = DIR_ROOT . '/storage/expenses/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
            $filename = 'expense_' . time() . '_' . rand(100, 999) . '.' . $ext;
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $filename)) {
                $filepath = 'storage/expenses/' . $filename;
            }
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO expenses (expense_date, category_id, title, vendor_supplier, invoice_number, amount, payment_method, paid_by, attachment, remarks)
                VALUES (:date, :cat_id, :title, :vendor, :invoice, :amt, :method, :by, :attach, :remarks)
            ");
            $ok = $stmt->execute([
                'date'    => $expenseDate,
                'cat_id'  => $categoryId,
                'title'   => $title,
                'vendor'  => $vendor,
                'invoice' => $invoiceNo,
                'amt'     => $amount,
                'method'  => $paymentMethod,
                'by'      => $_SESSION['user_id'] ?? null,
                'attach'  => $filepath,
                'remarks' => $remarks
            ]);

            if ($ok) {
                // If payment method is Cash, recalculate
                if ($paymentMethod === 'Cash' && class_exists('Cash')) {
                    $activeReg = Cash::getActiveRegister();
                    if ($activeReg) {
                        Cash::recalculateRegister($activeReg['id']);
                    }
                }
                auditLog('Expense Added', "Recorded operational expense of Rs. " . number_format($amount, 2) . " for: $title");
                jsonResponse(['success' => true, 'message' => 'Expense logged successfully.']);
            }
            jsonResponse(['success' => false, 'message' => 'Failed to record expense log.']);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'edit_expense':
        $id            = (int)($_POST['id'] ?? 0);
        $expenseDate   = sanitize($_POST['expense_date'] ?? date('Y-m-d'));
        $categoryId    = (int)($_POST['category_id'] ?? 0);
        $title         = sanitize($_POST['title'] ?? '');
        $vendor        = sanitize($_POST['vendor_supplier'] ?? '');
        $invoiceNo     = sanitize($_POST['invoice_number'] ?? '');
        $amount        = (float)($_POST['amount'] ?? 0);
        $paymentMethod = sanitize($_POST['payment_method'] ?? 'Cash');
        $remarks       = sanitize($_POST['remarks'] ?? '');

        if ($id <= 0 || $categoryId <= 0 || empty($title) || $amount <= 0) {
            jsonResponse(['success' => false, 'message' => 'All mandatory parameters are required.'], 400);
        }

        // File upload attachment
        $filepath = sanitize($_POST['existing_attachment'] ?? '');
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = DIR_ROOT . '/storage/expenses/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
            $filename = 'expense_' . time() . '_' . rand(100, 999) . '.' . $ext;
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $filename)) {
                $filepath = 'storage/expenses/' . $filename;
            }
        }

        try {
            $stmt = $db->prepare("
                UPDATE expenses 
                SET expense_date = :date, category_id = :cat_id, title = :title, vendor_supplier = :vendor, invoice_number = :invoice, amount = :amt, payment_method = :method, attachment = :attach, remarks = :remarks
                WHERE id = :id
            ");
            $ok = $stmt->execute([
                'date'    => $expenseDate,
                'cat_id'  => $categoryId,
                'title'   => $title,
                'vendor'  => $vendor,
                'invoice' => $invoiceNo,
                'amt'     => $amount,
                'method'  => $paymentMethod,
                'attach'  => $filepath,
                'remarks' => $remarks,
                'id'      => $id
            ]);

            if ($ok) {
                if (class_exists('Cash')) {
                    $activeReg = Cash::getActiveRegister();
                    if ($activeReg) {
                        Cash::recalculateRegister($activeReg['id']);
                    }
                }
                auditLog('Expense Updated', "Modified expense record ID #$id (New Amount: " . number_format($amount, 2) . ")");
                jsonResponse(['success' => true, 'message' => 'Expense record successfully updated.']);
            }
            jsonResponse(['success' => false, 'message' => 'Failed to update expense entry.']);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'delete_expense':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid ID.'], 400);
        }

        try {
            $stmt = $db->prepare("DELETE FROM expenses WHERE id = ?");
            $ok = $stmt->execute([$id]);

            if ($ok) {
                if (class_exists('Cash')) {
                    $activeReg = Cash::getActiveRegister();
                    if ($activeReg) {
                        Cash::recalculateRegister($activeReg['id']);
                    }
                }
                auditLog('Expense Deleted', "Removed operational expense log #$id");
                jsonResponse(['success' => true, 'message' => 'Expense entry deleted successfully.']);
            }
            jsonResponse(['success' => false, 'message' => 'Failed to delete expense record.']);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;


    // ── EXPENSE CATEGORIES CRUD ────────────────────────────────
    case 'add_category':
        $name = sanitize($_POST['name'] ?? '');
        $desc = sanitize($_POST['description'] ?? '');

        if (empty($name)) {
            jsonResponse(['success' => false, 'message' => 'Category name is required.'], 400);
        }

        try {
            $stmt = $db->prepare("INSERT INTO expense_categories (name, description, status) VALUES (?, ?, 'Active')");
            $ok = $stmt->execute([$name, $desc]);
            if ($ok) {
                auditLog('Category Created', "Created expense category: $name");
                jsonResponse(['success' => true, 'message' => 'Expense Category created successfully.']);
            }
            jsonResponse(['success' => false, 'message' => 'Failed to create category.']);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Category name already exists or database error: ' . $e->getMessage()]);
        }
        break;

    case 'edit_category':
        $id    = (int)($_POST['id'] ?? 0);
        $name  = sanitize($_POST['name'] ?? '');
        $desc  = sanitize($_POST['description'] ?? '');
        $status= sanitize($_POST['status'] ?? 'Active');

        if ($id <= 0 || empty($name)) {
            jsonResponse(['success' => false, 'message' => 'Mandatory parameters are required.'], 400);
        }

        try {
            $stmt = $db->prepare("UPDATE expense_categories SET name = ?, description = ?, status = ? WHERE id = ?");
            $ok = $stmt->execute([$name, $desc, $status, $id]);
            if ($ok) {
                auditLog('Category Updated', "Updated expense category ID #$id to $name");
                jsonResponse(['success' => true, 'message' => 'Category successfully updated.']);
            }
            jsonResponse(['success' => false, 'message' => 'Failed to update category.']);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'delete_category':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid ID.'], 400);
        }

        try {
            $stmt = $db->prepare("DELETE FROM expense_categories WHERE id = ?");
            $ok = $stmt->execute([$id]);
            if ($ok) {
                auditLog('Category Deleted', "Deleted expense category ID #$id");
                jsonResponse(['success' => true, 'message' => 'Expense Category successfully deleted.']);
            }
            jsonResponse(['success' => false, 'message' => 'Failed to delete category.']);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Cannot delete. Category is referenced by existing expenses.']);
        }
        break;


    // ── BANK TRANSACTIONS CRUD ─────────────────────────────────
    case 'add_bank_transaction':
        $date        = sanitize($_POST['date'] ?? date('Y-m-d'));
        $type        = sanitize($_POST['transaction_type'] ?? 'Deposit');
        $bankName    = sanitize($_POST['bank_name'] ?? '');
        $accountNo   = sanitize($_POST['account_number'] ?? '');
        $reference   = sanitize($_POST['reference_number'] ?? '');
        $amount      = (float)($_POST['amount'] ?? 0);
        $description = sanitize($_POST['description'] ?? '');

        if (empty($bankName) || empty($accountNo) || $amount <= 0) {
            jsonResponse(['success' => false, 'message' => 'Bank Name, Account number and positive amount are required.'], 400);
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO bank_transactions (date, transaction_type, bank_name, account_number, reference_number, amount, description)
                VALUES (:date, :type, :bank, :acc, :ref, :amt, :desc)
            ");
            $ok = $stmt->execute([
                'date' => $date,
                'type' => $type,
                'bank' => $bankName,
                'acc'  => $accountNo,
                'ref'  => $reference,
                'amt'  => $amount,
                'desc' => $description
            ]);

            if ($ok) {
                auditLog('Bank Transaction Recorded', "Recorded bank $type of Rs. " . number_format($amount, 2) . " into $bankName");
                jsonResponse(['success' => true, 'message' => 'Bank Transaction successfully saved.']);
            }
            jsonResponse(['success' => false, 'message' => 'Failed to save bank transaction.']);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'edit_bank_transaction':
        $id          = (int)($_POST['id'] ?? 0);
        $date        = sanitize($_POST['date'] ?? date('Y-m-d'));
        $type        = sanitize($_POST['transaction_type'] ?? 'Deposit');
        $bankName    = sanitize($_POST['bank_name'] ?? '');
        $accountNo   = sanitize($_POST['account_number'] ?? '');
        $reference   = sanitize($_POST['reference_number'] ?? '');
        $amount      = (float)($_POST['amount'] ?? 0);
        $description = sanitize($_POST['description'] ?? '');

        if ($id <= 0 || empty($bankName) || empty($accountNo) || $amount <= 0) {
            jsonResponse(['success' => false, 'message' => 'All mandatory parameters are required.'], 400);
        }

        try {
            $stmt = $db->prepare("
                UPDATE bank_transactions 
                SET date = :date, transaction_type = :type, bank_name = :bank, account_number = :acc, reference_number = :ref, amount = :amt, description = :desc
                WHERE id = :id
            ");
            $ok = $stmt->execute([
                'date' => $date,
                'type' => $type,
                'bank' => $bankName,
                'acc'  => $accountNo,
                'ref'  => $reference,
                'amt'  => $amount,
                'desc' => $description,
                'id'   => $id
            ]);

            if ($ok) {
                auditLog('Bank Transaction Updated', "Modified bank transaction record #$id");
                jsonResponse(['success' => true, 'message' => 'Bank Transaction updated.']);
            }
            jsonResponse(['success' => false, 'message' => 'Failed to update bank transaction.']);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'delete_bank_transaction':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid ID.'], 400);
        }

        try {
            $stmt = $db->prepare("DELETE FROM bank_transactions WHERE id = ?");
            $ok = $stmt->execute([$id]);
            if ($ok) {
                auditLog('Bank Transaction Deleted', "Deleted bank transaction record #$id");
                jsonResponse(['success' => true, 'message' => 'Bank transaction deleted.']);
            }
            jsonResponse(['success' => false, 'message' => 'Failed to delete bank transaction.']);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;


    // ── DAILY COUNTER OPENING CASH ─────────────────────────────
    case 'save_opening_cash':
        $openingCash = (float)($_POST['opening_cash'] ?? 0);
        $remarks     = sanitize($_POST['remarks'] ?? '');

        if ($openingCash < 0) {
            jsonResponse(['success' => false, 'message' => 'Opening funds cannot be a negative amount.'], 400);
        }

        try {
            $db->beginTransaction();

            // Insert into opening cash details table
            $insStmt = $db->prepare("
                INSERT INTO cash_opening (date, opening_cash, remarks) 
                VALUES (CURDATE(), :amt, :remarks)
                ON DUPLICATE KEY UPDATE opening_cash = :amt2, remarks = :remarks2
            ");
            $insStmt->execute([
                'amt'      => $openingCash,
                'remarks'  => $remarks,
                'amt2'     => $openingCash,
                'remarks2' => $remarks
            ]);

            // Open cash register using global cash registry model
            if (class_exists('Cash')) {
                Cash::openRegister($openingCash, 'Setup daily counter cash starting balance.');
            }

            // Sync cash_book entry
            $db->prepare("
                INSERT INTO cash_book (date, opening_cash, income, expenses, closing_cash, balance)
                VALUES (CURDATE(), :ob, 0, 0, 0, :ob2)
                ON DUPLICATE KEY UPDATE opening_cash = :ob3, balance = :ob4
            ")->execute([
                'ob'  => $openingCash,
                'ob2' => $openingCash,
                'ob3' => $openingCash,
                'ob4' => $openingCash
            ]);

            $db->commit();
            auditLog('Cash Opening Set', "Set opening cash counter balance to Rs. " . number_format($openingCash, 2));
            jsonResponse(['success' => true, 'message' => 'Daily opening cash balance successfully registered.']);
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;


    // ── DAILY COUNTER CLOSING CASH ─────────────────────────────
    case 'save_closing_cash':
        $closingBalance = (float)($_POST['closing_balance'] ?? 0);
        $remarks        = sanitize($_POST['remarks'] ?? '');

        if ($closingBalance < 0) {
            jsonResponse(['success' => false, 'message' => 'Closing physical cash cannot be a negative value.'], 400);
        }

        try {
            $db->beginTransaction();

            // Fetch today's financial metrics to compute expected cash
            $date = date('Y-m-d');
            
            // Get opening balance
            $openingCash = (float)$db->query("SELECT COALESCE(SUM(opening_cash),0) FROM cash_opening WHERE date = '$date'")->fetchColumn();

            // Get fee collection + other income today
            $feeIncome = (float)$db->query("
                SELECT COALESCE(SUM(amount),0) FROM income 
                WHERE income_date = '$date' AND source = 'Student Fee Collection'
            ")->fetchColumn();
            
            $otherIncome = (float)$db->query("
                SELECT COALESCE(SUM(amount),0) FROM income 
                WHERE income_date = '$date' AND source != 'Student Fee Collection' AND payment_method = 'Cash'
            ")->fetchColumn();

            // Get expenses today
            $expenses = (float)$db->query("
                SELECT COALESCE(SUM(amount),0) FROM expenses 
                WHERE expense_date = '$date' AND payment_method = 'Cash'
            ")->fetchColumn();

            $expectedCashInHand = ($openingCash + $feeIncome + $otherIncome) - $expenses;
            $difference = $closingBalance - $expectedCashInHand;

            // Log details into cash_closing table
            $clsStmt = $db->prepare("
                INSERT INTO cash_closing (date, opening_cash, fee_collection, other_income, expenses, cash_in_hand, closing_balance, difference, verified_by, remarks)
                VALUES (:date, :op, :fee, :other, :exp, :expected, :cls, :diff, :by, :remarks)
                ON DUPLICATE KEY UPDATE 
                    opening_cash = :op2, fee_collection = :fee2, other_income = :other2, expenses = :exp2,
                    cash_in_hand = :expected2, closing_balance = :cls2, difference = :diff2, remarks = :remarks2
            ");
            $clsStmt->execute([
                'date'      => $date,
                'op'        => $openingCash,
                'fee'       => $feeIncome,
                'other'     => $otherIncome,
                'exp'       => $expenses,
                'expected'  => $expectedCashInHand,
                'cls'       => $closingBalance,
                'diff'      => $difference,
                'by'        => $_SESSION['user_id'] ?? null,
                'remarks'   => $remarks,
                'op2'        => $openingCash,
                'fee2'       => $feeIncome,
                'other2'     => $otherIncome,
                'exp2'       => $expenses,
                'expected2'  => $expectedCashInHand,
                'cls2'       => $closingBalance,
                'diff2'      => $difference,
                'remarks2'   => $remarks
            ]);

            // Save to cash_book ledger
            $db->prepare("
                INSERT INTO cash_book (date, opening_cash, income, expenses, closing_cash, balance)
                VALUES (:date, :op, :inc, :exp, :cls, :bal)
                ON DUPLICATE KEY UPDATE 
                    opening_cash = :op2, income = :inc2, expenses = :exp2, closing_cash = :cls2, balance = :bal2
            ")->execute([
                'date' => $date,
                'op'   => $openingCash,
                'inc'  => $feeIncome + $otherIncome,
                'exp'  => $expenses,
                'cls'  => $closingBalance,
                'bal'  => $closingBalance,
                'op2'   => $openingCash,
                'inc2'  => $feeIncome + $otherIncome,
                'exp2'  => $expenses,
                'cls2'  => $closingBalance,
                'bal2'  => $closingBalance
            ]);

            // Close register using the legacy system Cash model to sync everything
            if (class_exists('Cash')) {
                Cash::closeRegister($closingBalance, 'Day closing cash desk reconciliation. Diff: ' . $difference);
            }

            $db->commit();
            auditLog('Cash Register Closed', "Closed daily cash counter. Expected: " . number_format($expectedCashInHand, 2) . " | Counted: " . number_format($closingBalance, 2));
            jsonResponse(['success' => true, 'message' => 'Daily cash counter register closed successfully.']);
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Request action not supported.'], 404);
        break;
}
