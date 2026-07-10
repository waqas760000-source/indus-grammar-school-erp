<?php
/**
 * Indus Grammar School ERP - StaffService
 * Version 1.0.0
 */

class StaffService {

    /**
     * Generate payroll for all active staff for a specific month and year
     *
     * @param int $month
     * @param int $year
     * @return array
     */
    public function generatePayroll(int $month, int $year): array {
        if ($month < 1 || $month > 12 || $year < 2000) {
            return ['status' => false, 'message' => 'Invalid month or year.'];
        }

        $activeStaff = Staff::all(['status' => 'Active'], 1000);
        if (empty($activeStaff)) {
            return ['status' => false, 'message' => 'No active staff found.'];
        }

        $successCount = 0;
        $errorCount = 0;

        foreach ($activeStaff as $staff) {
            $basicSalary = (float)$staff['salary'];
            
            // Basic payroll calculation (can be expanded later for specific deductions based on attendance/leaves)
            $allowances = 0;
            $deductions = 0;
            $netSalary = $basicSalary + $allowances - $deductions;

            $ok = Payroll::saveSalary([
                'staff_id'       => $staff['id'],
                'month'          => $month,
                'year'           => $year,
                'basic_salary'   => $basicSalary,
                'allowances'     => $allowances,
                'deductions'     => $deductions,
                'net_salary'     => $netSalary,
                'payment_status' => 'Pending'
            ]);

            if ($ok) $successCount++;
            else $errorCount++;
        }

        auditLog('Payroll Generated', "Payroll generated for $month/$year. Success: $successCount, Errors: $errorCount");
        
        return [
            'status' => true,
            'message' => "Payroll generated successfully. ($successCount records created/updated)"
        ];
    }

    /**
     * Process salary payment
     *
     * @param int $salaryId
     * @param string $paymentDate
     * @param string $paymentMethod
     * @return array
     */
    public function paySalary(int $salaryId, string $paymentDate, string $paymentMethod = 'Bank'): array {
        $salary = Payroll::findById($salaryId);
        if (!$salary) return ['status' => false, 'message' => 'Salary record not found.'];
        if ($salary['payment_status'] === 'Paid') return ['status' => false, 'message' => 'Salary already paid.'];

        $ok = Payroll::updateStatus($salaryId, 'Paid', $paymentDate);
        
        if ($ok) {
            // Record expense if paid in cash
            if ($paymentMethod === 'Cash') {
                Expense::create([
                    'category'          => 'Salaries',
                    'description'       => "Salary paid to {$salary['first_name']} {$salary['last_name']} for {$salary['month']}/{$salary['year']}",
                    'amount'            => $salary['net_salary'],
                    'expense_date'      => $paymentDate,
                    'paid_to'           => $salary['first_name'] . ' ' . $salary['last_name'],
                    'receipt_reference' => 'SAL-' . $salaryId
                ]);
            }
            
            auditLog('Salary Paid', "Salary paid to {$salary['first_name']} {$salary['last_name']} (ID: $salaryId)");
            return ['status' => true, 'message' => 'Salary marked as paid successfully.'];
        }

        return ['status' => false, 'message' => 'Failed to update salary status.'];
    }
}
