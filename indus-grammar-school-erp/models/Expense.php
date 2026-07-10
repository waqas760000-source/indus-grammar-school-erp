<?php
/**
 * Indus Grammar School ERP - Expense Model
 * Version 1.0.0
 */

class Expense {

    public static function all(array $filters = [], int $limit = 20, int $offset = 0): array {
        try {
            $db  = Database::getConnection();
            $sql = "SELECT * FROM expenses WHERE 1=1";
            $params = [];
            if (!empty($filters['category'])) { $sql .= " AND category = :cat"; $params['cat'] = $filters['category']; }
            if (!empty($filters['from']))      { $sql .= " AND expense_date >= :from"; $params['from'] = $filters['from']; }
            if (!empty($filters['to']))        { $sql .= " AND expense_date <= :to";   $params['to']   = $filters['to']; }
            $sql .= " ORDER BY expense_date DESC LIMIT :lim OFFSET :off";
            $stmt = $db->prepare($sql);
            foreach ($params as $k => $v) $stmt->bindValue($k, $v);
            $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
            $stmt->bindValue('off', $offset, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Expense::all error: " . $e->getMessage());
            return [];
        }
    }

    public static function create(array $data): int|bool {
        try {
            $db   = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO expenses (category, description, amount, expense_date, paid_to, receipt_reference, recorded_by)
                VALUES (:cat, :desc, :amt, :dt, :to, :ref, :by)
            ");
            $stmt->execute([
                'cat'  => $data['category'],
                'desc' => $data['description'],
                'amt'  => $data['amount'],
                'dt'   => $data['expense_date'],
                'to'   => $data['paid_to'] ?? null,
                'ref'  => $data['receipt_reference'] ?? null,
                'by'   => $_SESSION['user_id'] ?? null,
            ]);
            // Deduct from cash register
            $db->prepare("
                UPDATE cash_register SET total_expenses = total_expenses + :amt
                WHERE date = CURDATE() AND status = 'Open'
            ")->execute(['amt' => $data['amount']]);
            return (int)$db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Expense::create error: " . $e->getMessage());
            return false;
        }
    }

    public static function getTotalByDateRange(string $from, string $to): float {
        try {
            $db   = Database::getConnection();
            $stmt = $db->prepare("SELECT SUM(amount) FROM expenses WHERE expense_date BETWEEN :from AND :to");
            $stmt->execute(['from' => $from, 'to' => $to]);
            return (float)($stmt->fetchColumn() ?? 0);
        } catch (PDOException $e) {
            return 0.0;
        }
    }

    public static function getTodayTotal(): float {
        return self::getTotalByDateRange(date('Y-m-d'), date('Y-m-d'));
    }

    public static function getCategories(): array {
        return ['Utilities', 'Stationery', 'Salaries', 'Maintenance', 'Transport', 'Events', 'Food', 'IT & Tech', 'Miscellaneous'];
    }

    public static function delete(int $id): bool {
        try {
            $db = Database::getConnection();
            return $db->prepare("DELETE FROM expenses WHERE id = :id")->execute(['id' => $id]);
        } catch (PDOException $e) {
            error_log("Expense::delete error: " . $e->getMessage());
            return false;
        }
    }
}
