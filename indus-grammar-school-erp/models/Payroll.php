<?php
/**
 * Indus Grammar School ERP - Payroll Model
 * Version 1.0.0
 */

class Payroll {

    public static function all(array $filters = [], int $limit = 50, int $offset = 0): array {
        try {
            $db = Database::getConnection();
            $sql = "SELECT ps.*, s.first_name, s.last_name, s.employee_no, s.designation, s.department
                    FROM staff_salaries ps
                    JOIN staff s ON ps.staff_id = s.id
                    WHERE 1=1";
            $params = [];
            
            if (!empty($filters['month'])) {
                $sql .= " AND ps.month = :month";
                $params['month'] = $filters['month'];
            }
            if (!empty($filters['year'])) {
                $sql .= " AND ps.year = :year";
                $params['year'] = $filters['year'];
            }
            if (!empty($filters['status'])) {
                $sql .= " AND ps.payment_status = :status";
                $params['status'] = $filters['status'];
            }

            $sql .= " ORDER BY ps.year DESC, ps.month DESC, s.first_name ASC LIMIT :lim OFFSET :off";
            $stmt = $db->prepare($sql);
            
            foreach ($params as $k => $v) $stmt->bindValue($k, $v);
            $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
            $stmt->bindValue('off', $offset, PDO::PARAM_INT);
            
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Payroll::all error: " . $e->getMessage());
            return [];
        }
    }

    public static function findById(int $id): array|bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT ps.*, s.first_name, s.last_name, s.employee_no, s.designation, s.department
                                  FROM staff_salaries ps
                                  JOIN staff s ON ps.staff_id = s.id
                                  WHERE ps.id = :id");
            $stmt->execute(['id' => $id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return false;
        }
    }

    public static function saveSalary(array $data): bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO staff_salaries (staff_id, month, year, basic_salary, allowances, deductions, net_salary, payment_status, payment_date)
                VALUES (:sid, :month, :year, :basic, :allowances, :deductions, :net, :status, :pdate)
                ON DUPLICATE KEY UPDATE 
                    basic_salary = VALUES(basic_salary),
                    allowances = VALUES(allowances),
                    deductions = VALUES(deductions),
                    net_salary = VALUES(net_salary),
                    payment_status = VALUES(payment_status),
                    payment_date = VALUES(payment_date)
            ");
            return $stmt->execute([
                'sid'        => $data['staff_id'],
                'month'      => $data['month'],
                'year'       => $data['year'],
                'basic'      => $data['basic_salary'],
                'allowances' => $data['allowances'],
                'deductions' => $data['deductions'],
                'net'        => $data['net_salary'],
                'status'     => $data['payment_status'] ?? 'Pending',
                'pdate'      => !empty($data['payment_date']) ? $data['payment_date'] : null
            ]);
        } catch (PDOException $e) {
            error_log("Payroll::saveSalary error: " . $e->getMessage());
            return false;
        }
    }

    public static function updateStatus(int $id, string $status, string $paymentDate): bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("UPDATE staff_salaries SET payment_status = :status, payment_date = :pdate WHERE id = :id");
            return $stmt->execute(['status' => $status, 'pdate' => $paymentDate, 'id' => $id]);
        } catch (PDOException $e) {
            return false;
        }
    }
}
