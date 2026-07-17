<?php
/**
 * Indus Grammar School ERP - CommTemplate Model
 * Version 4.0.0
 */

class CommTemplate {

    public static function all(): array {
        try {
            $db = Database::getConnection();
            $stmt = $db->query("SELECT * FROM communication_templates ORDER BY name ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("CommTemplate::all error: " . $e->getMessage());
            return [];
        }
    }

    public static function find(int $id): array {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT * FROM communication_templates WHERE id = :id");
            $stmt->execute(['id' => $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log("CommTemplate::find error: " . $e->getMessage());
            return [];
        }
    }

    public static function save(array $data): bool {
        try {
            $db = Database::getConnection();
            $id = isset($data['id']) ? (int)$data['id'] : 0;
            
            if ($id > 0) {
                $stmt = $db->prepare("
                    UPDATE communication_templates 
                    SET name = :name, type = :type, subject = :subject, message = :msg 
                    WHERE id = :id
                ");
                return $stmt->execute([
                    'name'    => sanitize($data['name']),
                    'type'    => sanitize($data['type']),
                    'subject' => sanitize($data['subject'] ?? ''),
                    'msg'     => sanitize($data['message']),
                    'id'      => $id
                ]);
            } else {
                $stmt = $db->prepare("
                    INSERT INTO communication_templates (name, type, subject, message)
                    VALUES (:name, :type, :subject, :msg)
                ");
                return $stmt->execute([
                    'name'    => sanitize($data['name']),
                    'type'    => sanitize($data['type']),
                    'subject' => sanitize($data['subject'] ?? ''),
                    'msg'     => sanitize($data['message'])
                ]);
            }
        } catch (PDOException $e) {
            error_log("CommTemplate::save error: " . $e->getMessage());
            return false;
        }
    }

    public static function delete(int $id): bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("DELETE FROM communication_templates WHERE id = :id");
            return $stmt->execute(['id' => $id]);
        } catch (PDOException $e) {
            error_log("CommTemplate::delete error: " . $e->getMessage());
            return false;
        }
    }
}
