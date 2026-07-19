<?php
/**
 * Indus Grammar School ERP - WhatsAppTemplate Model
 * Version 4.0.0
 */

class WhatsAppTemplate {

    public static function seedDefaults(PDO $db): void {
        try {
            $count = (int)$db->query("SELECT COUNT(*) FROM whatsapp_templates")->fetchColumn();
            if ($count === 0) {
                $defaults = [
                    [
                        'name' => 'Fee Reminder',
                        'category' => 'Fee Reminder',
                        'body' => "Dear {{FatherName}}, this is a reminder from {{SchoolName}} regarding {{StudentName}} (Class {{Class}}-{{Section}}). Pending fee of Rs. {{OutstandingFee}} is due on {{DueDate}}. Please clear the dues to avoid late penalty. Thank you."
                    ],
                    [
                        'name' => 'Attendance Alert',
                        'category' => 'Attendance Alert',
                        'body' => "Dear Parent, your child {{StudentName}} (Class {{Class}}-{{Section}}) was marked {{Attendance}} today at {{SchoolName}}. Please contact school administration if you have any questions."
                    ],
                    [
                        'name' => 'Exam Schedule Notification',
                        'category' => 'Exam Reminder',
                        'body' => "Dear {{FatherName}}, upcoming examinations for {{StudentName}} (ID: {{StudentID}}) will start as per schedule. Please ensure regular attendance and study preparation. Management - {{SchoolName}}."
                    ],
                    [
                        'name' => 'Holiday Announcement',
                        'category' => 'Holiday Notice',
                        'body' => "Respected Parents, {{SchoolName}} will remain closed for holidays as per official schedule. Classes will resume normally. Best regards."
                    ],
                    [
                        'name' => 'Admission Confirmation',
                        'category' => 'Admission Confirmation',
                        'body' => "Congratulations {{FatherName}}! Admission for {{StudentName}} (ID: {{StudentID}}) in Class {{Class}}-{{Section}} has been confirmed at {{SchoolName}}. Welcome to our school family!"
                    ],
                    [
                        'name' => 'General Announcement',
                        'category' => 'General Announcement',
                        'body' => "Important Notice from {{SchoolName}} for {{StudentName}} (Class {{Class}}-{{Section}}): Please check portal for recent updates."
                    ],
                    [
                        'name' => 'Birthday Wish',
                        'category' => 'Birthday Wish',
                        'body' => "Happy Birthday {{StudentName}}! {{SchoolName}} wishes you great health, happiness, and academic success on your special day!"
                    ]
                ];

                $stmt = $db->prepare("INSERT INTO whatsapp_templates (name, category, body) VALUES (:name, :cat, :body)");
                foreach ($defaults as $d) {
                    $stmt->execute(['name' => $d['name'], 'cat' => $d['category'], 'body' => $d['body']]);
                }
            }
        } catch (Exception $e) {
            error_log("WhatsAppTemplate::seedDefaults error: " . $e->getMessage());
        }
    }

    public static function all(): array {
        try {
            $db = Database::getConnection();
            self::seedDefaults($db);
            $stmt = $db->query("SELECT * FROM whatsapp_templates WHERE is_active = 1 ORDER BY name ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("WhatsAppTemplate::all error: " . $e->getMessage());
            return [];
        }
    }

    public static function save(array $data): bool {
        try {
            $db = Database::getConnection();
            self::seedDefaults($db);

            $id       = (int)($data['id'] ?? 0);
            $name     = sanitize($data['name'] ?? '');
            $category = sanitize($data['category'] ?? 'Custom Template');
            $body     = sanitize($data['body'] ?? '');

            if ($id > 0) {
                $stmt = $db->prepare("UPDATE whatsapp_templates SET name = :name, category = :cat, body = :body WHERE id = :id");
                return $stmt->execute(['name' => $name, 'cat' => $category, 'body' => $body, 'id' => $id]);
            } else {
                $stmt = $db->prepare("INSERT INTO whatsapp_templates (name, category, body) VALUES (:name, :cat, :body)");
                return $stmt->execute(['name' => $name, 'cat' => $category, 'body' => $body]);
            }
        } catch (PDOException $e) {
            error_log("WhatsAppTemplate::save error: " . $e->getMessage());
            return false;
        }
    }

    public static function delete(int $id): bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("DELETE FROM whatsapp_templates WHERE id = :id");
            return $stmt->execute(['id' => $id]);
        } catch (PDOException $e) {
            error_log("WhatsAppTemplate::delete error: " . $e->getMessage());
            return false;
        }
    }
}
