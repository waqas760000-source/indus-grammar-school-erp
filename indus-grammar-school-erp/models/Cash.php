<?php
/**
 * Indus Grammar School ERP - Cash Register Model
 * Version 1.0.0
 */

class Cash {

    public static function getOpenRegister(): array|bool {
        try {
            $db   = Database::getConnection();
            $stmt = $db->query("SELECT * FROM cash_register WHERE status = 'Open' ORDER BY date DESC LIMIT 1");
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Cash::getOpenRegister error: " . $e->getMessage());
            return false;
        }
    }

    public static function getByDate(string $date): array|bool {
        try {
            $db   = Database::getConnection();
            $stmt = $db->prepare("SELECT * FROM cash_register WHERE date = :date");
            $stmt->execute(['date' => $date]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Cash::getByDate error: " . $e->getMessage());
            return false;
        }
    }

    public static function openRegister(float $openingBalance, ?string $notes): int|bool {
        try {
            $db   = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO cash_register (date, opening_balance, total_collections, total_expenses, status, notes)
                VALUES (CURDATE(), :ob, 0, 0, 'Open', :notes)
                ON DUPLICATE KEY UPDATE status = 'Open', opening_balance = :ob2, notes = :notes2
            ");
            $stmt->execute(['ob' => $openingBalance, 'notes' => $notes, 'ob2' => $openingBalance, 'notes2' => $notes]);
            return (int)$db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Cash::openRegister error: " . $e->getMessage());
            return false;
        }
    }

    public static function closeRegister(float $closingBalance, ?string $notes): bool {
        try {
            $db  = Database::getConnection();
            $uid = $_SESSION['user_id'] ?? null;
            $stmt = $db->prepare("
                UPDATE cash_register
                SET status = 'Closed', closing_balance = :cb, closed_by = :uid,
                    notes = CONCAT(COALESCE(notes,''), :notes), updated_at = NOW()
                WHERE date = CURDATE() AND status = 'Open'
            ");
            return $stmt->execute(['cb' => $closingBalance, 'uid' => $uid, 'notes' => ($notes ? ' | ' . $notes : '')]);
        } catch (PDOException $e) {
            error_log("Cash::closeRegister error: " . $e->getMessage());
            return false;
        }
    }

    public static function recalculate(string $date): bool {
        try {
            $db = Database::getConnection();
            $colStmt = $db->prepare("SELECT COALESCE(SUM(amount_paid),0) FROM fee_collections WHERE payment_date = :dt");
            $colStmt->execute(['dt' => $date]);
            $totalCol = (float)$colStmt->fetchColumn();

            $expStmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date = :dt");
            $expStmt->execute(['dt' => $date]);
            $totalExp = (float)$expStmt->fetchColumn();

            $upStmt = $db->prepare("
                UPDATE cash_register SET total_collections = :col, total_expenses = :exp
                WHERE date = :dt
            ");
            return $upStmt->execute(['col' => $totalCol, 'exp' => $totalExp, 'dt' => $date]);
        } catch (PDOException $e) {
            error_log("Cash::recalculate error: " . $e->getMessage());
            return false;
        }
    }

    public static function getHistory(int $limit = 30): array {
        try {
            $db   = Database::getConnection();
            $stmt = $db->prepare("
                SELECT cr.*, u.username as closed_by_name
                FROM cash_register cr
                LEFT JOIN users u ON cr.closed_by = u.id
                ORDER BY cr.date DESC LIMIT :lim
            ");
            $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Cash::getHistory error: " . $e->getMessage());
            return [];
        }
    }

    public static function getSummaryForRange(string $from, string $to): array {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT
                    SUM(opening_balance) as total_opening,
                    SUM(total_collections) as total_collections,
                    SUM(total_expenses) as total_expenses,
                    SUM(COALESCE(closing_balance, opening_balance + total_collections - total_expenses)) as total_closing,
                    COUNT(*) as days_count,
                    SUM(status = 'Closed') as closed_days
                FROM cash_register
                WHERE date BETWEEN :from AND :to
            ");
            $stmt->execute(['from' => $from, 'to' => $to]);
            return $stmt->fetch() ?: [];
        } catch (PDOException $e) {
            error_log("Cash::getSummaryForRange error: " . $e->getMessage());
            return [];
        }
    }
}
