<?php
/**
 * Indus Grammar School ERP - Student Model
 * Version 2.0.0
 */

class Student {

    /**
     * Retrieve list of students with filters and pagination
     *
     * @param array $filters Filters such as class_id, status, search, academic_type, school_class, academy_program
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function all(array $filters = [], int $limit = 10, int $offset = 0): array {
        try {
            $db = Database::getConnection();
            
            $sql = "SELECT s.*, c.class_name, c.section 
                    FROM students s
                    LEFT JOIN classes c ON s.class_id = c.id
                    WHERE 1=1";
            $params = [];

            if (!empty($filters['class_id'])) {
                $sql .= " AND s.class_id = :class_id";
                $params['class_id'] = $filters['class_id'];
            }

            if (!empty($filters['status'])) {
                $sql .= " AND s.status = :status";
                $params['status'] = $filters['status'];
            }

            if (!empty($filters['academic_type'])) {
                $sql .= " AND s.academic_type = :academic_type";
                $params['academic_type'] = $filters['academic_type'];
            }

            if (!empty($filters['school_class'])) {
                $sql .= " AND s.school_class = :school_class";
                $params['school_class'] = $filters['school_class'];
            }

            if (!empty($filters['academy_program'])) {
                $sql .= " AND s.academy_program = :academy_program";
                $params['academy_program'] = $filters['academy_program'];
            }

            if (!empty($filters['search'])) {
                $sql .= " AND (s.first_name LIKE :search 
                               OR s.last_name LIKE :search 
                               OR s.admission_no LIKE :search 
                               OR s.academic_type LIKE :search
                               OR s.school_class LIKE :search
                               OR s.academy_program LIKE :search
                               OR s.guardian_name LIKE :search)";
                $params['search'] = '%' . $filters['search'] . '%';
            }

            $sql .= " ORDER BY s.admission_no DESC LIMIT :limit OFFSET :offset";
            
            $stmt = $db->prepare($sql);
            
            // Bind parameters
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
            
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Student::all error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Count total students matching the filters
     *
     * @param array $filters
     * @return int
     */
    public static function count(array $filters = []): int {
        try {
            $db = Database::getConnection();
            
            $sql = "SELECT COUNT(*) FROM students s WHERE 1=1";
            $params = [];

            if (!empty($filters['class_id'])) {
                $sql .= " AND s.class_id = :class_id";
                $params['class_id'] = $filters['class_id'];
            }

            if (!empty($filters['status'])) {
                $sql .= " AND s.status = :status";
                $params['status'] = $filters['status'];
            }

            if (!empty($filters['academic_type'])) {
                $sql .= " AND s.academic_type = :academic_type";
                $params['academic_type'] = $filters['academic_type'];
            }

            if (!empty($filters['school_class'])) {
                $sql .= " AND s.school_class = :school_class";
                $params['school_class'] = $filters['school_class'];
            }

            if (!empty($filters['academy_program'])) {
                $sql .= " AND s.academy_program = :academy_program";
                $params['academy_program'] = $filters['academy_program'];
            }

            if (!empty($filters['search'])) {
                $sql .= " AND (s.first_name LIKE :search 
                               OR s.last_name LIKE :search 
                               OR s.admission_no LIKE :search 
                               OR s.academic_type LIKE :search
                               OR s.school_class LIKE :search
                               OR s.academy_program LIKE :search
                               OR s.guardian_name LIKE :search)";
                $params['search'] = '%' . $filters['search'] . '%';
            }

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Student::count error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Find a student by ID
     *
     * @param int $id
     * @return array|bool
     */
    public static function findById(int $id): array|bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT s.*, c.class_name, c.section 
                FROM students s
                LEFT JOIN classes c ON s.class_id = c.id
                WHERE s.id = :id
            ");
            $stmt->execute(['id' => $id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Student::findById error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Insert a new student record
     *
     * @param array $data Sanitized student details
     * @return int|bool Last inserted ID or false
     */
    public static function create(array $data): int|bool {
        try {
            $db = Database::getConnection();
            $sql = "INSERT INTO students (
                        admission_no, first_name, last_name, gender, date_of_birth, 
                        enrollment_date, class_id, status, guardian_name, 
                        guardian_phone, guardian_email, address,
                        academic_type, school_class, school_section, academy_program, academy_batch
                    ) VALUES (
                        :admission_no, :first_name, :last_name, :gender, :date_of_birth, 
                        :enrollment_date, :class_id, :status, :guardian_name, 
                        :guardian_phone, :guardian_email, :address,
                        :academic_type, :school_class, :school_section, :academy_program, :academy_batch
                    )";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                'admission_no' => $data['admission_no'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'gender' => $data['gender'],
                'date_of_birth' => $data['date_of_birth'],
                'enrollment_date' => $data['enrollment_date'],
                'class_id' => !empty($data['class_id']) ? $data['class_id'] : null,
                'status' => $data['status'] ?? 'Active',
                'guardian_name' => $data['guardian_name'],
                'guardian_phone' => $data['guardian_phone'],
                'guardian_email' => $data['guardian_email'] ?? null,
                'address' => $data['address'],
                'academic_type' => $data['academic_type'] ?? 'School',
                'school_class' => $data['school_class'] ?? null,
                'school_section' => $data['school_section'] ?? null,
                'academy_program' => $data['academy_program'] ?? null,
                'academy_batch' => $data['academy_batch'] ?? null
            ]);

            return (int)$db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Student::create error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update student details
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public static function update(int $id, array $data): bool {
        try {
            $db = Database::getConnection();
            $sql = "UPDATE students SET 
                        first_name = :first_name, 
                        last_name = :last_name, 
                        gender = :gender, 
                        date_of_birth = :date_of_birth, 
                        enrollment_date = :enrollment_date,
                        class_id = :class_id, 
                        status = :status, 
                        guardian_name = :guardian_name, 
                        guardian_phone = :guardian_phone, 
                        guardian_email = :guardian_email, 
                        address = :address,
                        academic_type = :academic_type,
                        school_class = :school_class,
                        school_section = :school_section,
                        academy_program = :academy_program,
                        academy_batch = :academy_batch
                    WHERE id = :id";
            
            $stmt = $db->prepare($sql);
            return $stmt->execute([
                'id' => $id,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'gender' => $data['gender'],
                'date_of_birth' => $data['date_of_birth'],
                'enrollment_date' => $data['enrollment_date'],
                'class_id' => !empty($data['class_id']) ? $data['class_id'] : null,
                'status' => $data['status'],
                'guardian_name' => $data['guardian_name'],
                'guardian_phone' => $data['guardian_phone'],
                'guardian_email' => $data['guardian_email'] ?? null,
                'address' => $data['address'],
                'academic_type' => $data['academic_type'] ?? 'School',
                'school_class' => $data['school_class'] ?? null,
                'school_section' => $data['school_section'] ?? null,
                'academy_program' => $data['academy_program'] ?? null,
                'academy_batch' => $data['academy_batch'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log("Student::update error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete student profile
     *
     * @param int $id
     * @return bool
     */
    public static function delete(int $id): bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("DELETE FROM students WHERE id = :id");
            return $stmt->execute(['id' => $id]);
        } catch (PDOException $e) {
            error_log("Student::delete error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate next sequence unique admission number
     *
     * @return string
     */
    public static function generateAdmissionNumber(): string {
        try {
            $year = date('Y');
            $db = Database::getConnection();
            $stmt = $db->query("SELECT MAX(id) as max_id FROM students");
            $row = $stmt->fetch();
            $nextId = ($row['max_id'] ?? 0) + 1;
            return sprintf("IGS-%s-%04d", $year, $nextId);
        } catch (Exception $e) {
            error_log("Student::generateAdmissionNumber error: " . $e->getMessage());
            return "IGS-" . date('Y') . "-" . rand(1000, 9999);
        }
    }
}
