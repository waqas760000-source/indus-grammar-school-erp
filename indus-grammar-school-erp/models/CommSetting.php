<?php
/**
 * Indus Grammar School ERP - CommSetting Model
 * Version 4.0.0
 */

class CommSetting {

    private static function ensureSchema(PDO $db): void {
        try {
            $cols = $db->query("SHOW COLUMNS FROM communication_settings")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('sms_provider', $cols)) {
                $db->exec("ALTER TABLE communication_settings ADD COLUMN sms_provider VARCHAR(50) DEFAULT 'test_mode'");
            }
            if (!in_array('sms_api_secret', $cols)) {
                $db->exec("ALTER TABLE communication_settings ADD COLUMN sms_api_secret VARCHAR(255) NULL");
            }

            // Also check sms_history status column
            $historyCols = $db->query("SHOW COLUMNS FROM sms_history LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
            if ($historyCols && str_contains(strtolower($historyCols['Type'] ?? ''), 'enum')) {
                $db->exec("ALTER TABLE sms_history MODIFY COLUMN status VARCHAR(30) DEFAULT 'Test Sent'");
            }
        } catch (Exception $e) {
            error_log("CommSetting::ensureSchema warning: " . $e->getMessage());
        }
    }

    public static function get(): array {
        try {
            $db = Database::getConnection();
            self::ensureSchema($db);
            $stmt = $db->query("SELECT * FROM communication_settings ORDER BY id ASC LIMIT 1");
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($res) {
                if (empty($res['sms_provider'])) {
                    $res['sms_provider'] = 'test_mode';
                }
                return $res;
            }
            
            // If empty, return standard defaults
            return [
                'sms_provider' => 'test_mode',
                'sms_gateway_api_key' => '',
                'sms_api_secret' => '',
                'sms_sender_id' => '',
                'sms_default_lang' => 'English',
                'smtp_host' => '',
                'smtp_port' => 587,
                'smtp_username' => '',
                'smtp_password' => '',
                'smtp_encryption' => 'tls',
                'default_sender_email' => '',
                'default_sender_name' => ''
            ];
        } catch (PDOException $e) {
            error_log("CommSetting::get error: " . $e->getMessage());
            return [];
        }
    }

    public static function save(array $data): bool {
        try {
            $db = Database::getConnection();
            self::ensureSchema($db);
            // Get the first ID or insert
            $id = (int)$db->query("SELECT id FROM communication_settings ORDER BY id ASC LIMIT 1")->fetchColumn();
            
            if ($id > 0) {
                $stmt = $db->prepare("
                    UPDATE communication_settings 
                    SET sms_provider = :sms_prov, sms_gateway_api_key = :sms_key, sms_api_secret = :sms_sec,
                        sms_sender_id = :sms_sid, sms_default_lang = :sms_lang,
                        smtp_host = :smtp_host, smtp_port = :smtp_port, smtp_username = :smtp_user, 
                        smtp_password = :smtp_pass, smtp_encryption = :smtp_enc, 
                        default_sender_email = :sender_email, default_sender_name = :sender_name
                    WHERE id = :id
                ");
                return $stmt->execute([
                    'sms_prov'     => sanitize($data['sms_provider'] ?? 'test_mode'),
                    'sms_key'      => sanitize($data['sms_gateway_api_key'] ?? ''),
                    'sms_sec'      => sanitize($data['sms_api_secret'] ?? ''),
                    'sms_sid'      => sanitize($data['sms_sender_id'] ?? ''),
                    'sms_lang'     => sanitize($data['sms_default_lang'] ?? 'English'),
                    'smtp_host'    => sanitize($data['smtp_host'] ?? ''),
                    'smtp_port'    => (int)($data['smtp_port'] ?? 587),
                    'smtp_user'    => sanitize($data['smtp_username'] ?? ''),
                    'smtp_pass'    => sanitize($data['smtp_password'] ?? ''),
                    'smtp_enc'     => sanitize($data['smtp_encryption'] ?? 'tls'),
                    'sender_email' => sanitize($data['default_sender_email'] ?? ''),
                    'sender_name'  => sanitize($data['default_sender_name'] ?? ''),
                    'id'           => $id
                ]);
            } else {
                $stmt = $db->prepare("
                    INSERT INTO communication_settings 
                    (sms_provider, sms_gateway_api_key, sms_api_secret, sms_sender_id, sms_default_lang, smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption, default_sender_email, default_sender_name)
                    VALUES (:sms_prov, :sms_key, :sms_sec, :sms_sid, :sms_lang, :smtp_host, :smtp_port, :smtp_user, :smtp_pass, :smtp_enc, :sender_email, :sender_name)
                ");
                return $stmt->execute([
                    'sms_prov'     => sanitize($data['sms_provider'] ?? 'test_mode'),
                    'sms_key'      => sanitize($data['sms_gateway_api_key'] ?? ''),
                    'sms_sec'      => sanitize($data['sms_api_secret'] ?? ''),
                    'sms_sid'      => sanitize($data['sms_sender_id'] ?? ''),
                    'sms_lang'     => sanitize($data['sms_default_lang'] ?? 'English'),
                    'smtp_host'    => sanitize($data['smtp_host'] ?? ''),
                    'smtp_port'    => (int)($data['smtp_port'] ?? 587),
                    'smtp_user'    => sanitize($data['smtp_username'] ?? ''),
                    'smtp_pass'    => sanitize($data['smtp_password'] ?? ''),
                    'smtp_enc'     => sanitize($data['smtp_encryption'] ?? 'tls'),
                    'sender_email' => sanitize($data['default_sender_email'] ?? ''),
                    'sender_name'  => sanitize($data['default_sender_name'] ?? '')
                ]);
            }
        } catch (PDOException $e) {
            error_log("CommSetting::save error: " . $e->getMessage());
            return false;
        }
    }
}
