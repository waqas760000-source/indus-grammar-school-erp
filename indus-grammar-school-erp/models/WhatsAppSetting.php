<?php
/**
 * Indus Grammar School ERP - WhatsAppSetting Model
 * Version 4.0.0
 */

class WhatsAppSetting {

    public static function get(): array {
        try {
            $db = Database::getConnection();
            $stmt = $db->query("SELECT * FROM whatsapp_settings ORDER BY id ASC LIMIT 1");
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($res) {
                return $res;
            }
            
            return [
                'mode'                => 'click_to_chat',
                'access_token'        => '',
                'phone_number_id'     => '',
                'business_account_id' => '',
                'webhook_url'         => '',
                'api_version'         => 'v18.0'
            ];
        } catch (PDOException $e) {
            error_log("WhatsAppSetting::get error: " . $e->getMessage());
            return [];
        }
    }

    public static function save(array $data): bool {
        try {
            $db = Database::getConnection();
            $id = (int)$db->query("SELECT id FROM whatsapp_settings ORDER BY id ASC LIMIT 1")->fetchColumn();
            
            $mode        = sanitize($data['mode'] ?? 'click_to_chat');
            $token       = sanitize($data['access_token'] ?? '');
            $phoneId     = sanitize($data['phone_number_id'] ?? '');
            $bizId       = sanitize($data['business_account_id'] ?? '');
            $webhook     = sanitize($data['webhook_url'] ?? '');
            $apiVersion  = sanitize($data['api_version'] ?? 'v18.0');

            if ($id > 0) {
                $stmt = $db->prepare("
                    UPDATE whatsapp_settings 
                    SET mode = :mode, access_token = :token, phone_number_id = :phone_id, 
                        business_account_id = :biz_id, webhook_url = :webhook, api_version = :ver
                    WHERE id = :id
                ");
                return $stmt->execute([
                    'mode'     => $mode,
                    'token'    => $token,
                    'phone_id' => $phoneId,
                    'biz_id'   => $bizId,
                    'webhook'  => $webhook,
                    'ver'      => $apiVersion,
                    'id'       => $id
                ]);
            } else {
                $stmt = $db->prepare("
                    INSERT INTO whatsapp_settings (mode, access_token, phone_number_id, business_account_id, webhook_url, api_version)
                    VALUES (:mode, :token, :phone_id, :biz_id, :webhook, :ver)
                ");
                return $stmt->execute([
                    'mode'     => $mode,
                    'token'    => $token,
                    'phone_id' => $phoneId,
                    'biz_id'   => $bizId,
                    'webhook'  => $webhook,
                    'ver'      => $apiVersion
                ]);
            }
        } catch (PDOException $e) {
            error_log("WhatsAppSetting::save error: " . $e->getMessage());
            return false;
        }
    }
}
