<?php
/**
 * Indus Grammar School ERP - CommSetting Model
 * Version 4.0.0
 */

class CommSetting {

    public static function get(): array {
        try {
            $db = Database::getConnection();
            $stmt = $db->query("SELECT * FROM communication_settings ORDER BY id ASC LIMIT 1");
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($res) return $res;
            
            // If empty, return standard defaults
            return [
                'sms_gateway_api_key' => '',
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
            // Get the first ID or insert
            $id = (int)$db->query("SELECT id FROM communication_settings ORDER BY id ASC LIMIT 1")->fetchColumn();
            
            if ($id > 0) {
                $stmt = $db->prepare("
                    UPDATE communication_settings 
                    SET sms_gateway_api_key = :sms_key, sms_sender_id = :sms_sid, sms_default_lang = :sms_lang,
                        smtp_host = :smtp_host, smtp_port = :smtp_port, smtp_username = :smtp_user, 
                        smtp_password = :smtp_pass, smtp_encryption = :smtp_enc, 
                        default_sender_email = :sender_email, default_sender_name = :sender_name
                    WHERE id = :id
                ");
                return $stmt->execute([
                    'sms_key'      => sanitize($data['sms_gateway_api_key'] ?? ''),
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
                    (sms_gateway_api_key, sms_sender_id, sms_default_lang, smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption, default_sender_email, default_sender_name)
                    VALUES (:sms_key, :sms_sid, :sms_lang, :smtp_host, :smtp_port, :smtp_user, :smtp_pass, :smtp_enc, :sender_email, :sender_name)
                ");
                return $stmt->execute([
                    'sms_key'      => sanitize($data['sms_gateway_api_key'] ?? ''),
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
