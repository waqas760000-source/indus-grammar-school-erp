<?php
/**
 * Indus Grammar School ERP - Cash Desk AJAX Handler
 * Version 1.0.0
 */

require_once __DIR__ . '/../config/app.php';

if (!isLoggedIn()) jsonResponse(['success' => false, 'message' => 'Unauthorized.'], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
if (!validateCsrf($_POST['csrf_token'] ?? '')) jsonResponse(['success' => false, 'message' => 'Security token expired.'], 403);

$action      = $_POST['action'] ?? '';
$cashService = new CashService();

switch ($action) {

    // ── Open register ──
    case 'open_register':
        AuthMiddleware::requirePermission('cash_manage');
        $ob    = (float)($_POST['opening_balance'] ?? 0);
        $notes = sanitize($_POST['notes'] ?? '');
        $res   = $cashService->openRegister($ob, $notes);
        jsonResponse(['success' => $res['status'], 'message' => $res['message']]);
        break;

    // ── Close register ──
    case 'close_register':
        AuthMiddleware::requirePermission('cash_manage');
        $pc    = (float)($_POST['physical_cash'] ?? 0);
        $notes = sanitize($_POST['notes'] ?? '');
        $res   = $cashService->closeRegister($pc, $notes);
        jsonResponse([
            'success'          => $res['status'],
            'message'          => $res['message'],
            'expected_closing' => $res['expected_closing'] ?? 0,
            'variance'         => $res['variance'] ?? 0
        ]);
        break;

    // ── Record expense ──
    case 'record_expense':
        AuthMiddleware::requirePermission('cash_manage');
        $res = $cashService->recordExpense([
            'category'          => sanitize($_POST['category'] ?? ''),
            'description'       => sanitize($_POST['description'] ?? ''),
            'amount'            => (float)($_POST['amount'] ?? 0),
            'expense_date'      => sanitize($_POST['expense_date'] ?? date('Y-m-d')),
            'paid_to'           => sanitize($_POST['paid_to'] ?? ''),
            'receipt_reference' => sanitize($_POST['receipt_reference'] ?? '')
        ]);
        jsonResponse(['success' => $res['status'], 'message' => $res['message']]);
        break;

    // ── Delete expense ──
    case 'delete_expense':
        AuthMiddleware::requirePermission('cash_manage');
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) jsonResponse(['success' => false, 'message' => 'Invalid expense ID.']);
        
        // Find expense amount to add back to register
        try {
            $db = Database::getConnection();
            $exp = $db->prepare("SELECT amount, expense_date FROM expenses WHERE id = :id");
            $exp->execute(['id' => $id]);
            $expense = $exp->fetch();
            if ($expense) {
                // Remove expense
                Expense::delete($id);
                // Try to adjust register if it's for today and open
                if ($expense['expense_date'] === date('Y-m-d')) {
                    $reg = Cash::getOpenRegister();
                    if ($reg) {
                        Cash::recalculate($reg['date']);
                    }
                }
                auditLog('Expense Deleted', "Expense ID $id deleted and cash register adjusted.");
                jsonResponse(['success' => true, 'message' => 'Expense deleted successfully.']);
            }
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Failed to delete expense.']);
        }
        jsonResponse(['success' => false, 'message' => 'Expense not found.']);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Unknown action.']);
}
