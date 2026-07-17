<?php
/**
 * Indus Grammar School ERP - Circular Model
 * Version 4.0.0
 */

class Circular {

    public static function all(): array {
        try {
            $db = Database::getConnection();
            $stmt = $db->query("
                SELECT c.*, cl.class_name, cl.section as class_section, u.username as published_by_name 
                FROM circulars c 
                LEFT JOIN classes cl ON c.class_id = cl.id
                LEFT JOIN users u ON c.published_by = u.id 
                ORDER BY c.issue_date DESC, c.id DESC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Circular::all error: " . $e->getMessage());
            return [];
        }
    }

    public static function activeForUser(string $type, int $classId = 0): array {
        try {
            $db = Database::getConnection();
            $today = date('Y-m-d');
            
            // Build query based on user's program/class type
            $sql = "
                SELECT c.*, cl.class_name, cl.section as class_section
                FROM circulars c
                LEFT JOIN classes cl ON c.class_id = cl.id
                WHERE :today BETWEEN c.issue_date AND c.expiry_date
                  AND (c.audience = 'Everyone' 
                       OR (:type = 'School' AND c.audience = 'School')
                       OR (:type = 'Academy' AND c.audience = 'Academy')
                       OR (c.class_id IS NOT NULL AND c.class_id = :class_id)
                      )
                ORDER BY c.issue_date DESC
            ";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                'today'    => $today,
                'type'     => $type,
                'class_id' => $classId
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Circular::activeForUser error: " . $e->getMessage());
            return [];
        }
    }

    public static function save(array $data): bool {
        try {
            $db = Database::getConnection();
            $id = isset($data['id']) ? (int)$data['id'] : 0;
            $classId = isset($data['class_id']) && (int)$data['class_id'] > 0 ? (int)$data['class_id'] : null;
            
            if ($id > 0) {
                $stmt = $db->prepare("
                    UPDATE circulars 
                    SET title = :title, description = :description, audience = :audience, 
                        class_id = :class_id, attachment_path = :attachment, 
                        issue_date = :issue, expiry_date = :expiry
                    WHERE id = :id
                ");
                return $stmt->execute([
                    'title'       => sanitize($data['title']),
                    'description' => sanitize($data['description']),
                    'audience'    => sanitize($data['audience']),
                    'class_id'    => $classId,
                    'attachment'  => sanitize($data['attachment_path'] ?? ''),
                    'issue'       => sanitize($data['issue_date']),
                    'expiry'      => sanitize($data['expiry_date']),
                    'id'          => $id
                ]);
            } else {
                $stmt = $db->prepare("
                    INSERT INTO circulars (title, description, audience, class_id, attachment_path, issue_date, expiry_date, published_by)
                    VALUES (:title, :description, :audience, :class_id, :attachment, :issue, :expiry, :published_by)
                ");
                return $stmt->execute([
                    'title'        => sanitize($data['title']),
                    'description'  => sanitize($data['description']),
                    'audience'     => sanitize($data['audience']),
                    'class_id'     => $classId,
                    'attachment'   => sanitize($data['attachment_path'] ?? ''),
                    'issue'        => sanitize($data['issue_date']),
                    'expiry'       => sanitize($data['expiry_date']),
                    'published_by' => $_SESSION['user_id'] ?? null
                ]);
            }
        } catch (PDOException $e) {
            error_log("Circular::save error: " . $e->getMessage());
            return false;
        }
    }

    public static function delete(int $id): bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("DELETE FROM circulars WHERE id = :id");
            return $stmt->execute(['id' => $id]);
        } catch (PDOException $e) {
            error_log("Circular::delete error: " . $e->getMessage());
            return false;
        }
    }
}
