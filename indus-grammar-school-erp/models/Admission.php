<?php
/**
 * Indus Grammar School ERP - Admission Model
 * Version 1.0.0
 */

class Admission {

    /**
     * Retrieve list of admission applications with filters and pagination
     *
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function all(array $filters = [], int $limit = 10, int $offset = 0): array {
        try {
            $db = Database::getConnection();
            
            $sql = "SELECT a.*, c.class_name, c.section 
                    FROM admissions a
                    JOIN classes c ON a.class_id = c.id
                    WHERE 1=1";
            $params = [];

            if (!empty($filters['class_id'])) {
                $sql .= " AND a.class_id = :class_id";
                $params['class_id'] = $filters['class_id'];
            }

            if (!empty($filters['status'])) {
                $sql .= " AND a.status = :status";
                $params['status'] = $filters['status'];
            }

            if (!empty($filters['search'])) {
                $sql .= " AND (a.first_name LIKE :search 
                               OR a.last_name LIKE :search 
                               OR a.application_no LIKE :search 
                               OR a.guardian_name LIKE :search)";
                $params['search'] = '%' . $filters['search'] . '%';
            }

            $sql .= " ORDER BY a.id DESC LIMIT :limit OFFSET :offset";
            
            $stmt = $db->prepare($sql);
            
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
            
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Admission::all error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Count total admission applications matching the filters
     *
     * @param array $filters
     * @return int
     */
    public static function count(array $filters = []): int {
        try {
            $db = Database::getConnection();
            
            $sql = "SELECT COUNT(*) FROM admissions a WHERE 1=1";
            $params = [];

            if (!empty($filters['class_id'])) {
                $sql .= " AND a.class_id = :class_id";
                $params['class_id'] = $filters['class_id'];
            }

            if (!empty($filters['status'])) {
                $sql .= " AND a.status = :status";
                $params['status'] = $filters['status'];
            }

            if (!empty($filters['search'])) {
                $sql .= " AND (a.first_name LIKE :search 
                               OR a.last_name LIKE :search 
                               OR a.application_no LIKE :search 
                               OR a.guardian_name LIKE :search)";
                $params['search'] = '%' . $filters['search'] . '%';
            }

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Admission::count error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Find an admission record by ID
     *
     * @param int $id
     * @return array|bool
     */
    public static function findById(int $id): array|bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT a.*, c.class_name, c.section 
                FROM admissions a
                JOIN classes c ON a.class_id = c.id
                WHERE a.id = :id
            ");
            $stmt->execute(['id' => $id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Admission::findById error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Insert a new admission applicant record
     *
     * @param array $data Sanitized applicant details
     * @return int|bool Last inserted ID or false
     */
    public static function create(array $data): int|bool {
        try {
            $db = Database::getConnection();
            $sql = "INSERT INTO admissions (
                        application_no, first_name, last_name, gender, date_of_birth, 
                        class_id, status, guardian_name, guardian_phone, 
                        guardian_email, address, application_date, notes
                    ) VALUES (
                        :application_no, :first_name, :last_name, :gender, :date_of_birth, 
                        :class_id, :status, :guardian_name, :guardian_phone, 
                        :guardian_email, :address, :application_date, :notes
                    )";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                'application_no' => $data['application_no'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'gender' => $data['gender'],
                'date_of_birth' => $data['date_of_birth'],
                'class_id' => $data['class_id'],
                'status' => $data['status'] ?? 'Pending',
                'guardian_name' => $data['guardian_name'],
                'guardian_phone' => $data['guardian_phone'],
                'guardian_email' => $data['guardian_email'] ?? null,
                'address' => $data['address'],
                'application_date' => $data['application_date'] ?? date('Y-m-d'),
                'notes' => $data['notes'] ?? null
            ]);

            return (int)$db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Admission::create error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update application status
     *
     * @param int $id
     * @param string $status 'Pending', 'Approved', 'Rejected'
     * @return bool
     */
    public static function updateStatus(int $id, string $status): bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("UPDATE admissions SET status = :status WHERE id = :id");
            return $stmt->execute([
                'id' => $id,
                'status' => $status
            ]);
        } catch (PDOException $e) {
            error_log("Admission::updateStatus error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate next sequence unique application number
     *
     * @return string
     */
    public static function generateApplicationNumber(): string {
        try {
            $year = date('Y');
            $db = Database::getConnection();
            $stmt = $db->query("SELECT MAX(id) as max_id FROM admissions");
            $row = $stmt->fetch();
            $nextId = ($row['max_id'] ?? 0) + 1;
            return sprintf("APP-%s-%04d", $year, $nextId);
        } catch (Exception $e) {
            error_log("Admission::generateApplicationNumber error: " . $e->getMessage());
            return "APP-" . date('Y') . "-" . rand(1000, 9999);
        }
    }
}
