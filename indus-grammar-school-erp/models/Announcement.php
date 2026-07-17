<?php
/**
 * Indus Grammar School ERP - Announcement Model
 * Version 4.0.0
 */

class Announcement {

    public static function all(): array {
        try {
            $db = Database::getConnection();
            $stmt = $db->query("
                SELECT a.*, u.username as published_by_name 
                FROM announcements a 
                LEFT JOIN users u ON a.published_by = u.id 
                ORDER BY a.start_date DESC, a.id DESC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Announcement::all error: " . $e->getMessage());
            return [];
        }
    }

    public static function activeForAudience(string $audience): array {
        try {
            $db = Database::getConnection();
            $today = date('Y-m-d');
            
            // Build statement targeting audience or Everyone
            $stmt = $db->prepare("
                SELECT * FROM announcements 
                WHERE status = 'Active' 
                  AND :audience IN (audience, 'Everyone')
                  AND :today BETWEEN start_date AND end_date
                ORDER BY priority = 'Urgent' DESC, priority = 'Important' DESC, start_date DESC
            ");
            $stmt->execute(['audience' => $audience, 'today' => $today]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Announcement::activeForAudience error: " . $e->getMessage());
            return [];
        }
    }

    public static function save(array $data): bool {
        try {
            $db = Database::getConnection();
            $id = isset($data['id']) ? (int)$data['id'] : 0;
            
            if ($id > 0) {
                $stmt = $db->prepare("
                    UPDATE announcements 
                    SET title = :title, content = :content, audience = :audience, 
                        start_date = :start, end_date = :end, priority = :priority, status = :status
                    WHERE id = :id
                ");
                return $stmt->execute([
                    'title'    => sanitize($data['title']),
                    'content'  => sanitize($data['content']),
                    'audience' => sanitize($data['audience']),
                    'start'    => sanitize($data['start_date']),
                    'end'      => sanitize($data['end_date']),
                    'priority' => sanitize($data['priority']),
                    'status'   => sanitize($data['status']),
                    'id'       => $id
                ]);
            } else {
                $stmt = $db->prepare("
                    INSERT INTO announcements (title, content, audience, start_date, end_date, priority, status, published_by)
                    VALUES (:title, :content, :audience, :start, :end, :priority, :status, :published_by)
                ");
                return $stmt->execute([
                    'title'        => sanitize($data['title']),
                    'content'      => sanitize($data['content']),
                    'audience'     => sanitize($data['audience']),
                    'start'        => sanitize($data['start_date']),
                    'end'          => sanitize($data['end_date']),
                    'priority'     => sanitize($data['priority']),
                    'status'       => sanitize($data['status']),
                    'published_by' => $_SESSION['user_id'] ?? null
                ]);
            }
        } catch (PDOException $e) {
            error_log("Announcement::save error: " . $e->getMessage());
            return false;
        }
    }

    public static function delete(int $id): bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("DELETE FROM announcements WHERE id = :id");
            return $stmt->execute(['id' => $id]);
        } catch (PDOException $e) {
            error_log("Announcement::delete error: " . $e->getMessage());
            return false;
        }
    }
}
