<?php
/**
 * Indus Grammar School ERP - WhatsAppHistory Model
 * Version 4.0.0
 */

class WhatsAppHistory {

    public static function getLogs(string $dateFrom = '', string $dateTo = '', string $search = '', string $status = ''): array {
        try {
            $db = Database::getConnection();
            $sql = "
                SELECT h.*, u.username as sent_by_name 
                FROM whatsapp_history h 
                LEFT JOIN users u ON h.sent_by = u.id 
                WHERE 1=1
            ";
            $params = [];

            if ($dateFrom !== '') {
                $sql .= " AND h.created_at >= :from";
                $params['from'] = $dateFrom . ' 00:00:00';
            }
            if ($dateTo !== '') {
                $sql .= " AND h.created_at <= :to";
                $params['to'] = $dateTo . ' 23:59:59';
            }
            if ($status !== '') {
                $sql .= " AND h.status = :status";
                $params['status'] = $status;
            }
            if ($search !== '') {
                $sql .= " AND (h.recipient_name LIKE :q OR h.phone LIKE :q OR h.message LIKE :q OR h.template_name LIKE :q)";
                $params['q'] = '%' . $search . '%';
            }

            $sql .= " ORDER BY h.id DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("WhatsAppHistory::getLogs error: " . $e->getMessage());
            return [];
        }
    }

    public static function log(array $data): bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO whatsapp_history (recipient_type, recipient_name, phone, message, template_name, sent_by, status)
                VALUES (:type, :name, :phone, :msg, :template, :sent_by, :status)
            ");
            return $stmt->execute([
                'type'     => sanitize($data['recipient_type'] ?? 'Single Student'),
                'name'     => sanitize($data['recipient_name'] ?? 'Unknown Recipient'),
                'phone'    => sanitize($data['phone'] ?? ''),
                'msg'      => sanitize($data['message'] ?? ''),
                'template' => !empty($data['template_name']) ? sanitize($data['template_name']) : null,
                'sent_by'  => $_SESSION['user_id'] ?? null,
                'status'   => sanitize($data['status'] ?? 'Opened')
            ]);
        } catch (PDOException $e) {
            error_log("WhatsAppHistory::log error: " . $e->getMessage());
            return false;
        }
    }

    public static function delete(int $id): bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("DELETE FROM whatsapp_history WHERE id = :id");
            return $stmt->execute(['id' => $id]);
        } catch (PDOException $e) {
            error_log("WhatsAppHistory::delete error: " . $e->getMessage());
            return false;
        }
    }
}
