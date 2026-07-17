<?php
/**
 * Indus Grammar School ERP - Routes and Permission Registry
 * Version 1.0.0
 */

return [
    'dashboard.php' => 'dashboard_view',
    
    // Students
    'modules/students/list.php' => 'student_view',
    'modules/students/add.php'  => 'student_create',
    'modules/students/edit.php' => 'student_edit',
    'modules/students/registration.php'      => 'student_view',
    'modules/students/detail_report.php'     => 'student_view',
    'modules/students/daily_diary.php'       => 'student_view',
    'modules/students/edit_diary.php'        => 'student_view',
    'modules/students/diary_report.php'      => 'student_view',
    'modules/students/view_diary.php'       => 'student_view',
    'modules/students/print_diary.php'      => 'student_view',
    'modules/students/complaint.php'         => 'student_view',
    'modules/students/card_report.php'       => 'student_view',
    'modules/students/name_cnic_report.php'  => 'student_view',
    'modules/students/add_sponsor.php'       => 'student_view',
    'modules/students/family_phone_list.php' => 'student_view',
    'modules/students/profile_report.php'    => 'student_view',
    'modules/students/summary_report.php'    => 'student_view',
    
    // Admission
    'modules/admission/applications.php' => 'admission_view',
    
    // Attendance
    'modules/attendance/student.php' => 'attendance_mark',
    'modules/attendance/staff.php'   => 'attendance_mark',
    'modules/attendance/daily.php'   => 'attendance_view',
    'modules/attendance/monthly.php' => 'attendance_view',
    'modules/attendance/leave.php'   => 'attendance_view',
    'modules/attendance/reports.php' => 'attendance_view',
    
    // Exams
    'modules/examination/dashboard.php'    => 'exam_view',
    'modules/examination/exams.php'        => 'exam_view',
    'modules/examination/schedule.php'     => 'exam_view',
    'modules/examination/subjects.php'     => 'exam_view',
    'modules/examination/marks.php'        => 'exam_marks_entry',
    'modules/examination/grades.php'       => 'exam_view',
    'modules/examination/results.php'      => 'exam_view',
    'modules/examination/report_cards.php' => 'exam_view',
    'modules/examination/positions.php'    => 'exam_view',
    'modules/examination/promotions.php'    => 'exam_view',
    'modules/examination/reports.php'       => 'exam_view',
    
    // Finance & Accounts
    'modules/fees/structure.php'  => 'fee_view',
    'modules/fees/collection.php' => 'fee_collect',
    'modules/fees/challan.php'    => 'fee_invoice',
    'modules/fees/dues.php'       => 'fee_view',
    'modules/fees/discounts.php'  => 'fee_view',
    'modules/fees/fines.php'      => 'fee_view',
    'modules/fees/receipts.php'   => 'fee_view',
    'modules/fees/expenses.php'   => 'fee_view',
    'modules/fees/reports.php'    => 'fee_view',
    
    // Cash Desk
    'modules/cash/dashboard.php'       => 'cash_view',
    'modules/cash/opening_balance.php' => 'cash_transaction',
    'modules/cash/closing.php'         => 'cash_transaction',
    'modules/cash/reconciliation.php'  => 'cash_view',
    'modules/cash/collection.php'      => 'cash_view',
    'modules/cash/expenses.php'        => 'cash_transaction',
    'modules/cash/reports.php'         => 'cash_view',
    
    // Staff & HR
    'modules/staff/list.php'               => 'staff_view',
    'modules/staff/payroll.php'            => 'hr_manage',
    'modules/staff/payroll_setup.php'      => 'hr_manage',
    'modules/staff/payroll_process.php'    => 'hr_manage',
    'modules/staff/payroll_allowances.php' => 'hr_manage',
    'modules/staff/payroll_deductions.php' => 'hr_manage',
    'modules/staff/payroll_advance.php'    => 'hr_manage',
    'modules/staff/payroll_bonuses.php'    => 'hr_manage',
    'modules/staff/payroll_reports.php'    => 'hr_manage',
    'modules/staff/payroll_settings.php'   => 'system_settings',
    'modules/staff/salary_slip.php'        => 'hr_view',
    
    // Communication
    'modules/communication/dashboard.php'     => 'communication_send',
    'modules/communication/announcements.php' => 'communication_send',
    'modules/communication/email.php'         => 'communication_send',
    'modules/communication/sms.php'           => 'communication_send',
    'modules/communication/reminders.php'     => 'communication_send',
    'modules/communication/circulars.php'      => 'communication_send',
    'modules/communication/history.php'        => 'communication_send',
    'modules/communication/templates.php'      => 'communication_send',
    'modules/communication/settings.php'       => 'communication_send',
    
    // Reports
    'modules/reports/dashboard.php'     => 'report_view',
    'modules/reports/students.php'      => 'report_view',
    'modules/reports/attendance.php'    => 'report_view',
    'modules/reports/finance.php'       => 'report_view',
    'modules/reports/fees.php'          => 'report_view',
    'modules/reports/accounts.php'      => 'report_view',
    'modules/reports/examination.php'   => 'report_view',
    'modules/reports/staff.php'         => 'report_view',
    'modules/reports/payroll.php'       => 'report_view',
    'modules/reports/communication.php' => 'report_view',
    'modules/reports/custom.php'        => 'report_view',
    'modules/reports/export.php'        => 'report_view',
    
    // Administration
    'modules/administration/settings.php'    => 'system_settings',
    'modules/administration/users.php'       => 'system_settings',
    'modules/administration/roles.php'       => 'system_settings',
    'modules/administration/permissions.php' => 'system_settings',
    'modules/administration/backup.php'      => 'system_settings',
    'modules/administration/logs.php'        => 'system_settings',
];
