<?php
/**
 * Indus Grammar School ERP - Admission Service
 * Version 1.0.0
 */

class AdmissionService {

    /**
     * Submit a new admission application
     *
     * @param array $data Input fields
     * @return array Status and result/errors
     */
    public function registerApplicant(array $data): array {
        $validation = $this->validateApplicantData($data);
        if (!$validation['status']) {
            return $validation;
        }

        // Generate application number
        $data['application_no'] = Admission::generateApplicationNumber();
        $data['status'] = 'Pending';
        $data['application_date'] = date('Y-m-d');

        // Save application
        $appId = Admission::create($data);

        if ($appId) {
            $fullName = $data['first_name'] . ' ' . $data['last_name'];
            auditLog('Admission Request', "New admission application filed for: $fullName (App No: {$data['application_no']})");
            return ['status' => true, 'message' => "Application submitted successfully. Ref No: {$data['application_no']}", 'id' => $appId];
        }

        return ['status' => false, 'message' => 'Failed to process admission application.'];
    }

    /**
     * Approve admission application and automatically enroll as a student (Transactional)
     *
     * @param int $id Application ID
     * @return array Status and message
     */
    public function approveApplication(int $id): array {
        $app = Admission::findById($id);
        if (!$app) {
            return ['status' => false, 'message' => 'Admission application not found.'];
        }

        if ($app['status'] !== 'Pending') {
            return ['status' => false, 'message' => "Application cannot be approved. Current status is " . $app['status']];
        }

        try {
            $db = Database::getConnection();
            $db->beginTransaction();

            // 1. Update application status
            $stmtUpdate = $db->prepare("UPDATE admissions SET status = 'Approved', updated_at = NOW() WHERE id = :id");
            $stmtUpdate->execute(['id' => $id]);

            // 2. Generate unique student admission number
            $admissionNo = Student::generateAdmissionNumber();

            // 3. Insert student record
            $sqlStudent = "INSERT INTO students (
                                admission_no, first_name, last_name, gender, date_of_birth, 
                                enrollment_date, class_id, status, guardian_name, 
                                guardian_phone, guardian_email, address
                            ) VALUES (
                                :admission_no, :first_name, :last_name, :gender, :date_of_birth, 
                                :enrollment_date, :class_id, 'Active', :guardian_name, 
                                :guardian_phone, :guardian_email, :address
                            )";
            
            $stmtStudent = $db->prepare($sqlStudent);
            $stmtStudent->execute([
                'admission_no' => $admissionNo,
                'first_name' => $app['first_name'],
                'last_name' => $app['last_name'],
                'gender' => $app['gender'],
                'date_of_birth' => $app['date_of_birth'],
                'enrollment_date' => date('Y-m-d'), // Enrolled today
                'class_id' => $app['class_id'],
                'guardian_name' => $app['guardian_name'],
                'guardian_phone' => $app['guardian_phone'],
                'guardian_email' => $app['guardian_email'],
                'address' => $app['address']
            ]);

            $studentId = (int)$db->lastInsertId();

            $db->commit();

            $fullName = $app['first_name'] . ' ' . $app['last_name'];
            auditLog('Admission Approved', "Approved application APP ID: $id. Enrolled student: $fullName (Admission No: $admissionNo)");

            return ['status' => true, 'message' => "Application approved. Student enrolled successfully (Admission No: $admissionNo).", 'student_id' => $studentId];

        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("AdmissionService::approveApplication failed: " . $e->getMessage());
            return ['status' => false, 'message' => 'An error occurred during approval. Transactions rolled back.'];
        }
    }

    /**
     * Reject admission application
     *
     * @param int $id
     * @param string|null $notes Additional reasons
     * @return array Status and message
     */
    public function rejectApplication(int $id, ?string $notes = null): array {
        $app = Admission::findById($id);
        if (!$app) {
            return ['status' => false, 'message' => 'Admission application not found.'];
        }

        if ($app['status'] !== 'Pending') {
            return ['status' => false, 'message' => "Application cannot be rejected. Current status is " . $app['status']];
        }

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("UPDATE admissions SET status = 'Rejected', notes = :notes, updated_at = NOW() WHERE id = :id");
            $success = $stmt->execute([
                'id' => $id,
                'notes' => !empty($notes) ? $app['notes'] . " | Rejection Note: " . sanitize($notes) : $app['notes']
            ]);

            if ($success) {
                auditLog('Admission Rejected', "Rejected application APP ID: $id for: {$app['first_name']} {$app['last_name']}");
                return ['status' => true, 'message' => 'Admission application rejected successfully.'];
            }
        } catch (Exception $e) {
            error_log("AdmissionService::rejectApplication failed: " . $e->getMessage());
        }

        return ['status' => false, 'message' => 'Failed to reject admission application.'];
    }

    /**
     * Validate applicants input
     *
     * @param array $data
     * @return array
     */
    private function validateApplicantData(array $data): array {
        $errors = [];

        $required = [
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'gender' => 'Gender',
            'date_of_birth' => 'Date of Birth',
            'class_id' => 'Class to Apply',
            'guardian_name' => 'Guardian Name',
            'guardian_phone' => 'Guardian Phone',
            'address' => 'Address'
        ];

        foreach ($required as $field => $label) {
            if (empty($data[$field])) {
                $errors[] = "$label is required.";
            }
        }

        if (!empty($data['gender']) && !in_array($data['gender'], ['Male', 'Female', 'Other'])) {
            $errors[] = "Invalid gender selection.";
        }

        if (!empty($data['date_of_birth'])) {
            $dob = strtotime($data['date_of_birth']);
            if (!$dob || $dob > time()) {
                $errors[] = "Invalid Date of Birth.";
            }
        }

        if (!empty($data['class_id'])) {
            $class = SchoolClass::findById((int)$data['class_id']);
            if (!$class) {
                $errors[] = "Selected class does not exist.";
            }
        }

        if (!empty($data['guardian_phone']) && !preg_match('/^[0-9+\-\s]{7,17}$/', $data['guardian_phone'])) {
            $errors[] = "Invalid phone format.";
        }

        if (!empty($data['guardian_email']) && !filter_var($data['guardian_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format.";
        }

        if (count($errors) > 0) {
            return ['status' => false, 'message' => implode(' ', $errors)];
        }

        return ['status' => true];
    }
}
