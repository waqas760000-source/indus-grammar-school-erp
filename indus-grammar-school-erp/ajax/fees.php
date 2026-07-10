<?php
/**
 * Indus Grammar School ERP - Fee & Accounts AJAX Handler
 * Version 1.0.0
 */

require_once __DIR__ . '/../config/app.php';

if (!isLoggedIn()) jsonResponse(['success' => false, 'message' => 'Unauthorized.'], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
if (!validateCsrf($_POST['csrf_token'] ?? '')) jsonResponse(['success' => false, 'message' => 'Security token expired.'], 403);

$action     = $_POST['action'] ?? '';
$feeService = new FeeService();

switch ($action) {

    // ── Save fee structure ──
    case 'save_structure':
        AuthMiddleware::requirePermission('fee_manage');
        $result = $feeService->saveFeeStructure([
            'class_id'  => (int)($_POST['class_id'] ?? 0),
            'fee_type'  => sanitize($_POST['fee_type'] ?? ''),
            'amount'    => (float)($_POST['amount'] ?? 0),
            'academic_year' => sanitize($_POST['academic_year'] ?? CURRENT_ACADEMIC_YEAR),
        ]);
        jsonResponse(['success' => $result['status'], 'message' => $result['message']]);
        break;

    // ── Delete fee structure ──
    case 'delete_structure':
        AuthMiddleware::requirePermission('fee_manage');
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) jsonResponse(['success' => false, 'message' => 'Invalid ID.']);
        $ok = Fee::deleteStructure($id);
        if ($ok) auditLog('Fee Structure Deleted', "Fee structure ID $id deleted.");
        jsonResponse(['success' => $ok, 'message' => $ok ? 'Deleted successfully.' : 'Delete failed.']);
        break;

    // ── Get fee structures for a class ──
    case 'get_structures_by_class':
        AuthMiddleware::requirePermission('fee_view');
        $classId = (int)($_POST['class_id'] ?? 0);
        $structures = Fee::getStructuresByClass($classId);
        $total = array_sum(array_column($structures, 'amount'));
        jsonResponse(['success' => true, 'structures' => $structures, 'total' => $total]);
        break;

    // ── Generate challan for a student ──
    case 'generate_challan':
        AuthMiddleware::requirePermission('fee_manage');
        $studentId = (int)($_POST['student_id'] ?? 0);
        $month     = sanitize($_POST['month'] ?? '');
        $dueDate   = sanitize($_POST['due_date'] ?? '');
        if ($studentId <= 0) jsonResponse(['success' => false, 'message' => 'Invalid student.']);
        $result = $feeService->generateChallan($studentId, $month, $dueDate);
        jsonResponse(['success' => $result['status'], 'message' => $result['message'], 'challan_no' => $result['challan_no'] ?? '']);
        break;

    // ── Collect a fee payment ──
    case 'collect_payment':
        AuthMiddleware::requirePermission('fee_collect');
        $result = $feeService->collectPayment([
            'challan_id'     => !empty($_POST['challan_id']) ? (int)$_POST['challan_id'] : null,
            'student_id'     => (int)($_POST['student_id'] ?? 0),
            'amount_paid'    => (float)($_POST['amount_paid'] ?? 0),
            'payment_date'   => sanitize($_POST['payment_date'] ?? date('Y-m-d')),
            'payment_method' => sanitize($_POST['payment_method'] ?? 'Cash'),
            'remarks'        => sanitize($_POST['remarks'] ?? ''),
        ]);
        jsonResponse(['success' => $result['status'], 'message' => $result['message'], 'receipt_no' => $result['receipt_no'] ?? '']);
        break;

    // ── Apply discount ──
    case 'apply_discount':
        AuthMiddleware::requirePermission('fee_manage');
        $studentId = (int)($_POST['student_id'] ?? 0);
        $result = $feeService->addDiscount($studentId, [
            'discount_type' => sanitize($_POST['discount_type'] ?? ''),
            'percentage'    => (float)($_POST['percentage'] ?? 0),
            'flat_amount'   => (float)($_POST['flat_amount'] ?? 0),
            'reason'        => sanitize($_POST['reason'] ?? ''),
        ]);
        jsonResponse(['success' => $result['status'], 'message' => $result['message']]);
        break;

    // ── Apply fine ──
    case 'apply_fine':
        AuthMiddleware::requirePermission('fee_manage');
        $studentId = (int)($_POST['student_id'] ?? 0);
        $result = $feeService->addFine($studentId, [
            'fine_type' => sanitize($_POST['fine_type'] ?? 'Late Payment'),
            'amount'    => (float)($_POST['amount'] ?? 0),
            'reason'    => sanitize($_POST['reason'] ?? ''),
        ]);
        jsonResponse(['success' => $result['status'], 'message' => $result['message']]);
        break;

    // ── Search student for fee collection ──
    case 'search_student':
        AuthMiddleware::requirePermission('fee_view');
        $q = sanitize($_POST['q'] ?? '');
        if (strlen($q) < 2) jsonResponse(['success' => false, 'message' => 'Enter at least 2 characters.']);
        try {
            $db   = Database::getConnection();
            $stmt = $db->prepare("
                SELECT s.id, s.first_name, s.last_name, s.admission_no, c.class_name, c.section,
                       (SELECT COALESCE(SUM(net_amount), 0) FROM fee_challans WHERE student_id = s.id AND status IN ('Unpaid','Overdue')) as dues
                FROM students s JOIN classes c ON s.class_id = c.id
                WHERE (s.first_name LIKE :q OR s.last_name LIKE :q OR s.admission_no LIKE :q)
                  AND s.status = 'Active' LIMIT 10
            ");
            $stmt->execute(['q' => '%'.$q.'%']);
            jsonResponse(['success' => true, 'students' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Search failed.']);
        }
        break;

    // ── Get student challans ──
    case 'get_student_challans':
        AuthMiddleware::requirePermission('fee_view');
        $sid = (int)($_POST['student_id'] ?? 0);
        $challans = Fee::allChallans(['student_id' => $sid]);
        jsonResponse(['success' => true, 'challans' => $challans]);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Unknown action.']);
}
