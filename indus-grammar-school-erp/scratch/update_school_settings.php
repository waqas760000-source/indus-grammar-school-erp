<?php
require_once __DIR__ . '/../config/app.php';

$db = Database::getConnection();

// Add bank and jazzcash columns if they don't exist
$cols = [
    'bank_name'          => "VARCHAR(100) DEFAULT 'Meezan Bank Ltd'",
    'bank_account_title' => "VARCHAR(100) DEFAULT 'Indus Grammar School'",
    'bank_account_no'    => "VARCHAR(100) DEFAULT 'PK64 MEZN 0001 0203 0405 0607'",
    'jazzcash_title'     => "VARCHAR(100) DEFAULT 'Indus Grammar School'",
    'jazzcash_number'    => "VARCHAR(30) DEFAULT '03066544806'"
];

foreach ($cols as $col => $type) {
    try {
        $db->exec("ALTER TABLE school_settings ADD COLUMN $col $type");
        echo "Added column $col\n";
    } catch (Exception $e) {
        // Column may already exist
    }
}

// Update settings row with user's specific values
$stmt = $db->prepare("
    UPDATE school_settings 
    SET city = 'Lahore',
        school_address = 'Main Campus, Lahore, Pakistan',
        phone_number = '',
        whatsapp_number = '',
        bank_name = 'Meezan Bank Ltd',
        bank_account_title = 'Indus Grammar School',
        bank_account_no = 'PK64 MEZN 0001 0203 0405 0607',
        jazzcash_title = 'Indus Grammar School',
        jazzcash_number = '03066544806'
    WHERE id = 1
");
$stmt->execute();
echo "Updated school_settings row 1 successfully.\n";

$row = $db->query("SELECT * FROM school_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
print_r($row);
