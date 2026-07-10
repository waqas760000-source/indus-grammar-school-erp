<?php
/**
 * Indus Grammar School ERP - CashService
 * Version 1.0.0
 */

class CashService {

    public function openRegister(float $openingBalance, ?string $notes): array {
        $existing = Cash::getByDate(date('Y-m-d'));
        if ($existing && $existing['status'] === 'Closed') {
            return ['status' => false, 'message' => 'Today\'s register has already been closed and cannot be reopened.'];
        }
        if ($existing && $existing['status'] === 'Open') {
            return ['status' => false, 'message' => 'Cash register is already open for today.'];
        }
        if ($openingBalance < 0) return ['status' => false, 'message' => 'Opening balance cannot be negative.'];

        $id = Cash::openRegister($openingBalance, $notes);
        if ($id) {
            auditLog('Cash Register Opened', "Cash register opened for " . date('Y-m-d') . " with opening balance: Rs. " . number_format($openingBalance, 2));
            return ['status' => true, 'message' => 'Cash register opened successfully.'];
        }
        return ['status' => false, 'message' => 'Failed to open cash register.'];
    }

    public function closeRegister(float $physicalCash, ?string $notes): array {
        $register = Cash::getOpenRegister();
        if (!$register) return ['status' => false, 'message' => 'No open register found for today.'];

        // Recalculate from actual transactions
        Cash::recalculate($register['date']);
        $register = Cash::getByDate(date('Y-m-d'));
        $expectedClosing = $register['opening_balance'] + $register['total_collections'] - $register['total_expenses'];

        $ok = Cash::closeRegister($physicalCash, $notes);
        if ($ok) {
            $variance = $physicalCash - $expectedClosing;
            $varStr   = ($variance >= 0 ? '+' : '') . 'Rs. ' . number_format(abs($variance), 2) . ($variance >= 0 ? ' surplus' : ' shortage');
            auditLog('Cash Register Closed', "Cash register closed. Physical: Rs. " . number_format($physicalCash, 2) . " | Expected: Rs. " . number_format($expectedClosing, 2) . " | Variance: $varStr");
            return [
                'status'           => true,
                'message'          => 'Cash register closed successfully.',
                'expected_closing' => $expectedClosing,
                'physical_cash'    => $physicalCash,
                'variance'         => $variance,
            ];
        }
        return ['status' => false, 'message' => 'Failed to close cash register.'];
    }

    public function recordExpense(array $data): array {
        $errors = [];
        if (empty($data['category']))    $errors[] = 'Category is required.';
        if (empty($data['description'])) $errors[] = 'Description is required.';
        if (empty($data['amount']) || $data['amount'] <= 0) $errors[] = 'Valid amount is required.';
        if (empty($data['expense_date'])) $errors[] = 'Date is required.';
        if ($errors) return ['status' => false, 'message' => implode(' ', $errors)];

        $id = Expense::create($data);
        if ($id) {
            auditLog('Expense Recorded', "Expense: {$data['category']} — Rs. {$data['amount']} on {$data['expense_date']}");
            return ['status' => true, 'message' => 'Expense recorded successfully.', 'id' => $id];
        }
        return ['status' => false, 'message' => 'Failed to record expense.'];
    }
}
