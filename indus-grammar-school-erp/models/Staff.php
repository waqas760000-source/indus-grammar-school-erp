<?php
/**
 * Indus Grammar School ERP - Staff Model
 * Version 1.0.0
 */

class Staff {

    public static function all(array $filters = [], int $limit = 50, int $offset = 0): array {
        try {
            $db = Database::getConnection();
            $sql = "SELECT s.*, u.username as user_username, u.email as user_email 
                    FROM staff s 
                    LEFT JOIN users u ON s.user_id = u.id 
                    WHERE 1=1";
            $params = [];
            
            if (!empty($filters['department'])) {
                $sql .= " AND s.department = :dept";
                $params['dept'] = $filters['department'];
            }
            if (!empty($filters['status'])) {
                $sql .= " AND s.status = :status";
                $params['status'] = $filters['status'];
            }
            if (!empty($filters['search'])) {
                $sql .= " AND (s.first_name LIKE :q OR s.last_name LIKE :q OR s.employee_no LIKE :q)";
                $params['q'] = '%' . $filters['search'] . '%';
            }

            $sql .= " ORDER BY s.first_name ASC LIMIT :lim OFFSET :off";
            $stmt = $db->prepare($sql);
            
            foreach ($params as $k => $v) $stmt->bindValue($k, $v);
            $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
            $stmt->bindValue('off', $offset, PDO::PARAM_INT);
            
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Staff::all error: " . $e->getMessage());
            return [];
        }
    }

    public static function findById(int $id): array|bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT s.*, u.username as user_username, u.email as user_email 
                                  FROM staff s 
                                  LEFT JOIN users u ON s.user_id = u.id 
                                  WHERE s.id = :id");
            $stmt->execute(['id' => $id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return false;
        }
    }

    public static function generateEmployeeNo(): string {
        try {
            $db = Database::getConnection();
            $stmt = $db->query("SELECT MAX(id) as max_id FROM staff");
            $row = $stmt->fetch();
            $next = ($row['max_id'] ?? 0) + 1;
            return sprintf("EMP-%04d", $next);
        } catch (Exception $e) {
            return "EMP-" . rand(1000, 9999);
        }
    }

    public static function create(array $data): int|bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO staff (employee_no, user_id, first_name, last_name, designation, department, phone, email, address, date_of_joining, salary, status)
                VALUES (:emp_no, :uid, :fname, :lname, :desig, :dept, :phone, :email, :address, :doj, :salary, :status)
            ");
            $stmt->execute([
                'emp_no'  => $data['employee_no'],
                'uid'     => !empty($data['user_id']) ? $data['user_id'] : null,
                'fname'   => sanitize($data['first_name']),
                'lname'   => sanitize($data['last_name']),
                'desig'   => sanitize($data['designation']),
                'dept'    => sanitize($data['department']),
                'phone'   => sanitize($data['phone']),
                'email'   => sanitize($data['email'] ?? ''),
                'address' => sanitize($data['address'] ?? ''),
                'doj'     => sanitize($data['date_of_joining']),
                'salary'  => (float)($data['salary'] ?? 0),
                'status'  => sanitize($data['status'] ?? 'Active')
            ]);
            return (int)$db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Staff::create error: " . $e->getMessage());
            return false;
        }
    }

    public static function update(int $id, array $data): bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                UPDATE staff 
                SET user_id = :uid, first_name = :fname, last_name = :lname, designation = :desig, 
                    department = :dept, phone = :phone, email = :email, address = :address, 
                    date_of_joining = :doj, salary = :salary, status = :status
                WHERE id = :id
            ");
            return $stmt->execute([
                'uid'     => !empty($data['user_id']) ? $data['user_id'] : null,
                'fname'   => sanitize($data['first_name']),
                'lname'   => sanitize($data['last_name']),
                'desig'   => sanitize($data['designation']),
                'dept'    => sanitize($data['department']),
                'phone'   => sanitize($data['phone']),
                'email'   => sanitize($data['email'] ?? ''),
                'address' => sanitize($data['address'] ?? ''),
                'doj'     => sanitize($data['date_of_joining']),
                'salary'  => (float)($data['salary'] ?? 0),
                'status'  => sanitize($data['status'] ?? 'Active'),
                'id'      => $id
            ]);
        } catch (PDOException $e) {
            error_log("Staff::update error: " . $e->getMessage());
            return false;
        }
    }
}
