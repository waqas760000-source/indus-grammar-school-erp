<?php
/**
 * Indus Grammar School ERP - System Constants
 * Version 1.0.0
 */

// School Metadata
define('SCHOOL_NAME', 'Indus Grammar School');
define('SCHOOL_OWNER', 'Saeed');
define('SCHOOL_SHORT_NAME', 'IGS');
define('SCHOOL_EMAIL', 'info@indus.edu.pk');
define('SCHOOL_PHONE', '');
define('SCHOOL_ADDRESS', 'Main Campus, Lahore, Pakistan');

// Payment & Account Metadata
define('SCHOOL_BANK_NAME', 'Meezan Bank Ltd');
define('SCHOOL_BANK_TITLE', 'Indus Grammar School');
define('SCHOOL_BANK_ACCOUNT', 'PK64 MEZN 0001 0203 0405 0607');
define('SCHOOL_JAZZCASH_TITLE', 'Indus Grammar School');
define('SCHOOL_JAZZCASH_NUMBER', '03066544806');

// Auto-generated numbers prefixes & settings
define('PREFIX_STUDENT_ID', 'STU');
define('PREFIX_ADMISSION_NO', 'IGS');
define('PREFIX_REGISTRATION_NO', 'IGS');
define('PREFIX_ROLL_NO', 'IGS');
define('PREFIX_ENROLLMENT_NO', 'IGS');

// Role Codes (must match the database `roles` table)
define('ROLE_SUPER_ADMIN', 'super_admin');
define('ROLE_SCHOOL_ADMIN', 'school_admin');
define('ROLE_ACCOUNTANT', 'accountant');

// Status constants
define('STATUS_ACTIVE', 1);
define('STATUS_INACTIVE', 0);

// Academic terms/sessions
define('CURRENT_ACADEMIC_YEAR', '2026-2027');
