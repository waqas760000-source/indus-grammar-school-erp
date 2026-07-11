<?php
/**
 * Indus Grammar School ERP - Student Repository
 * Version 1.0.0
 */

class StudentRepository {

    /**
     * Retrieve a student's complete profile
     *
     * @param int $studentId
     * @return array|null The complete structured profile or null if not found
     */
    public static function getCompleteProfile(int $studentId): ?array {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT s.id, s.admission_no, s.first_name, s.last_name, s.gender, s.date_of_birth, s.enrollment_date, s.class_id, s.status,
                       s.guardian_name, s.guardian_phone, s.guardian_email, s.address, s.academic_type, s.school_class, s.school_section,
                       c.class_name, c.section,
                       d.roll_no, d.admission_date, d.academic_session, d.campus,
                       d.blood_group, d.religion, d.nationality, d.cnic_no,
                       d.birth_cert_no, d.birth_place, d.student_mobile, d.student_email,
                       d.father_name, d.father_cnic, d.father_mobile, d.father_occupation,
                       d.father_office, d.father_income, d.father_email, d.father_photo,
                       d.mother_name, d.mother_cnic, d.mother_mobile, d.mother_occupation,
                       d.mother_email, d.mother_photo,
                       d.guardian_relationship, d.guardian_cnic, d.guardian_address,
                       d.current_address, d.permanent_address, d.city, d.province, d.postal_code, d.country,
                       d.prev_school, d.prev_class, d.prev_roll_no, d.prev_result, d.leaving_cert_no,
                       d.test_marks, d.medical_condition, d.allergies, d.disability,
                       d.emergency_contact, d.doctor_name, d.doctor_contact,
                       d.transport_required, d.transport_route, d.pickup_point, d.drop_point, d.transport_vehicle, d.transport_driver,
                       d.sponsor_required, d.sponsor_name, d.sponsor_org, d.sponsor_contact, d.sponsor_amount,
                       d.fee_plan, d.fee_admission, d.fee_monthly, d.fee_discount, d.fee_scholarship, d.fee_fine, d.fee_security,
                       d.remarks, d.special_notes, d.doc_student_photo, d.doc_father_cnic, d.doc_mother_cnic,
                       d.doc_bform, d.doc_birth_cert, d.doc_leaving_cert, d.doc_prev_result, d.doc_medical_cert, d.doc_other
                FROM students s
                LEFT JOIN classes c ON s.class_id = c.id
                LEFT JOIN student_registration_details d ON s.id = d.student_id
                WHERE s.id = :id
            ");
            $stmt->execute(['id' => $studentId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            error_log("StudentRepository::getCompleteProfile error: " . $e->getMessage());
            return null;
        }
    }
}
