<?php
/**
 * Indus Grammar School ERP - CommHistory Model
 * Version 4.0.0
 */

class CommHistory {

    public static function sms(string $dateFrom = '', string $dateTo = ''): array {
        try {
            $db = Database::getConnection();
            $sql = "
                SELECT h.*, u.username as sent_by_name 
                FROM sms_history h 
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
            $sql .= " ORDER BY h.id DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("CommHistory::sms error: " . $e->getMessage());
            return [];
        }
    }

    public static function email(string $dateFrom = '', string $dateTo = ''): array {
        try {
            $db = Database::getConnection();
            $sql = "
                SELECT h.*, u.username as sent_by_name 
                FROM email_history h 
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
            $sql .= " ORDER BY h.id DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("CommHistory::email error: " . $e->getMessage());
            return [];
        }
    }

    public static function logSms(array $data): bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO sms_history (recipient_type, recipient_count, recipients_list, message, scheduled_time, sent_by, status)
                VALUES (:type, :count, :list, :msg, :sched, :sent_by, :status)
            ");
            return $stmt->execute([
                'type'    => sanitize($data['recipient_type']),
                'count'   => (int)$data['recipient_count'],
                'list'    => json_encode($data['recipients_list']),
                'msg'     => sanitize($data['message']),
                'sched'   => !empty($data['scheduled_time']) ? sanitize($data['scheduled_time']) : null,
                'sent_by' => $_SESSION['user_id'] ?? null,
                'status'  => sanitize($data['status'] ?? 'Sent')
            ]);
        } catch (PDOException $e) {
            error_log("CommHistory::logSms error: " . $e->getMessage());
            return false;
        }
    }

    public static function logEmail(array $data): bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO email_history (recipient_type, recipient_count, recipients_list, subject, body, attachment_path, priority, scheduled_time, sent_by, status)
                VALUES (:type, :count, :list, :subject, :body, :attachment, :priority, :sched, :sent_by, :status)
            ");
            return $stmt->execute([
                'type'       => sanitize($data['recipient_type']),
                'count'      => (int)$data['recipient_count'],
                'list'       => json_encode($data['recipients_list']),
                'subject'    => sanitize($data['subject']),
                'body'       => $data['body'], // Body might contain rich HTML text editor code
                'attachment' => sanitize($data['attachment_path'] ?? null),
                'priority'   => sanitize($data['priority'] ?? 'Normal'),
                'sched'      => !empty($data['scheduled_time']) ? sanitize($data['scheduled_time']) : null,
                'sent_by'    => $_SESSION['user_id'] ?? null,
                'status'     => sanitize($data['status'] ?? 'Sent')
            ]);
        } catch (PDOException $e) {
            error_log("CommHistory::logEmail error: " . $e->getMessage());
            return false;
        }
    }

    public static function deleteSmsLog(int $id): bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("DELETE FROM sms_history WHERE id = :id");
            return $stmt->execute(['id' => $id]);
        } catch (PDOException $e) {
            error_log("CommHistory::deleteSmsLog error: " . $e->getMessage());
            return false;
        }
    }

    public static function deleteEmailLog(int $id): bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("DELETE FROM email_history WHERE id = :id");
            return $stmt->execute(['id' => $id]);
        } catch (PDOException $e) {
            error_log("CommHistory::deleteEmailLog error: " . $e->getMessage());
            return false;
        }
    }
}
