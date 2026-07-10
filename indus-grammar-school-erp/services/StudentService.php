<?php
/**
 * Indus Grammar School ERP - Student Service
 * Version 1.0.0
 */

class StudentService {

    /**
     * Create a new student record directly
     *
     * @param array $data Input fields
     * @return array Status and result/errors
     */
    public function registerStudent(array $data): array {
        $validation = $this->validateStudentData($data);
        if (!$validation['status']) {
            return $validation;
        }

        // Generate unique admission number
        $data['admission_no'] = Student::generateAdmissionNumber();

        // Save
        $studentId = Student::create($data);

        if ($studentId) {
            $fullName = $data['first_name'] . ' ' . $data['last_name'];
            auditLog('Student Registration', "Registered new student: $fullName (Admission No: {$data['admission_no']})");
            return ['status' => true, 'message' => "Student registered successfully. Admission No: {$data['admission_no']}", 'id' => $studentId];
        }

        return ['status' => false, 'message' => 'Failed to save student record in the database.'];
    }

    /**
     * Update an existing student record
     *
     * @param int $id
     * @param array $data Input fields
     * @return array Status and errors
     */
    public function updateStudent(int $id, array $data): array {
        // Find existing record
        $existing = Student::findById($id);
        if (!$existing) {
            return ['status' => false, 'message' => 'Student record not found.'];
        }

        $validation = $this->validateStudentData($data);
        if (!$validation['status']) {
            return $validation;
        }

        // Save
        $success = Student::update($id, $data);

        if ($success) {
            $fullName = $data['first_name'] . ' ' . $data['last_name'];
            auditLog('Student Updated', "Updated student details for: $fullName (Admission No: {$existing['admission_no']})");
            return ['status' => true, 'message' => 'Student record updated successfully.'];
        }

        return ['status' => false, 'message' => 'Failed to update student record.'];
    }

    /**
     * Delete/archive a student
     *
     * @param int $id
     * @return array Status and errors
     */
    public function deleteStudent(int $id): array {
        $existing = Student::findById($id);
        if (!$existing) {
            return ['status' => false, 'message' => 'Student record not found.'];
        }

        $success = Student::delete($id);

        if ($success) {
            $fullName = $existing['first_name'] . ' ' . $existing['last_name'];
            auditLog('Student Deleted', "Deleted student profile: $fullName (Admission No: {$existing['admission_no']})");
            return ['status' => true, 'message' => 'Student record deleted successfully.'];
        }

        return ['status' => false, 'message' => 'Failed to delete student record.'];
    }

    /**
     * Server-side validation of student profiles
     *
     * @param array $data
     * @return array
     */
    private function validateStudentData(array $data): array {
        $errors = [];

        // Check required fields
        $required = [
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'gender' => 'Gender',
            'date_of_birth' => 'Date of Birth',
            'enrollment_date' => 'Enrollment Date',
            'class_id' => 'Class',
            'guardian_name' => 'Guardian Name',
            'guardian_phone' => 'Guardian Phone',
            'address' => 'Address'
        ];

        foreach ($required as $field => $label) {
            if (empty($data[$field])) {
                $errors[] = "$label is required.";
            }
        }

        // Validate values
        if (!empty($data['gender']) && !in_array($data['gender'], ['Male', 'Female', 'Other'])) {
            $errors[] = "Invalid gender selection.";
        }

        if (!empty($data['date_of_birth'])) {
            $dob = strtotime($data['date_of_birth']);
            if (!$dob || $dob > time()) {
                $errors[] = "Invalid Date of Birth.";
            }
        }

        if (!empty($data['enrollment_date'])) {
            $enroll = strtotime($data['enrollment_date']);
            if (!$enroll) {
                $errors[] = "Invalid Enrollment Date.";
            }
        }

        if (!empty($data['class_id'])) {
            $class = SchoolClass::findById((int)$data['class_id']);
            if (!$class) {
                $errors[] = "Selected class does not exist.";
            }
        }

        if (!empty($data['guardian_phone']) && !preg_match('/^[0-9+\-\s]{7,17}$/', $data['guardian_phone'])) {
            $errors[] = "Invalid guardian phone number format.";
        }

        if (!empty($data['guardian_email']) && !filter_var($data['guardian_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid guardian email address format.";
        }

        if (count($errors) > 0) {
            return ['status' => false, 'message' => implode(' ', $errors)];
        }

        return ['status' => true];
    }
}
