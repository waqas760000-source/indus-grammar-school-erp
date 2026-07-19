<?php
/**
 * Indus Grammar School ERP - WhatsApp Service Abstraction Layer
 * Version 4.0.0
 */

require_once __DIR__ . '/../models/WhatsAppSetting.php';
require_once __DIR__ . '/../models/WhatsAppHistory.php';

class WhatsAppService {

    private array $settings;

    public function __construct() {
        $this->settings = WhatsAppSetting::get();
    }

    /**
     * Normalize Pakistani and International phone numbers to standard 92XXXXXXXXXX format
     *
     * @param string $phone Raw phone string
     * @return string Normalized digits (e.g. 923001234567)
     */
    public function normalizePakistaniPhone(string $phone): string {
        $digits = preg_replace('/[^0-9]/', '', $phone);

        // Standard 11-digit mobile starting with 03xx -> 923xx
        if (strlen($digits) === 11 && str_starts_with($digits, '03')) {
            return '92' . substr($digits, 1);
        }

        // 10-digit mobile starting with 3xx -> 923xx
        if (strlen($digits) === 10 && str_starts_with($digits, '3')) {
            return '92' . $digits;
        }

        // Already 12 digits starting with 923xx
        if (strlen($digits) === 12 && str_starts_with($digits, '923')) {
            return $digits;
        }

        // Starts with 00923...
        if (strlen($digits) === 14 && str_starts_with($digits, '0092')) {
            return substr($digits, 2);
        }

        // Default fallback if digits start with 92
        if (str_starts_with($digits, '92') && strlen($digits) >= 11) {
            return $digits;
        }

        return $digits;
    }

    /**
     * Substitute dynamic variables in template body text
     *
     * @param string $template Body template string with {{Tags}}
     * @param array $variables Key-value pairs of student/school data
     * @return string Rendered message text
     */
    public function renderTemplate(string $template, array $variables): string {
        $schoolName = $variables['school_name'] ?? 'Indus Grammar School';

        $map = [
            '{{StudentName}}'    => $variables['student_name'] ?? 'Student',
            '{{FatherName}}'     => $variables['father_name'] ?? 'Parent/Guardian',
            '{{GuardianName}}'   => $variables['guardian_name'] ?? ($variables['father_name'] ?? 'Parent/Guardian'),
            '{{Class}}'          => $variables['class_name'] ?? 'N/A',
            '{{Section}}'        => $variables['section'] ?? 'A',
            '{{StudentID}}'      => $variables['student_id'] ?? ($variables['admission_no'] ?? 'N/A'),
            '{{OutstandingFee}}' => $variables['outstanding_fee'] ?? '0',
            '{{DueDate}}'        => $variables['due_date'] ?? 'N/A',
            '{{Attendance}}'     => $variables['attendance'] ?? 'Present',
            '{{SchoolName}}'     => $schoolName
        ];

        return strtr($template, $map);
    }

    /**
     * Generate official WhatsApp Click-to-Chat URL
     *
     * @param string $phone
     * @param string $message
     * @return string Encoded wa.me URL
     */
    public function generateClickToChatUrl(string $phone, string $message): string {
        $normalizedPhone = $this->normalizePakistaniPhone($phone);
        $encodedMessage  = rawurlencode($message);
        return "https://wa.me/{$normalizedPhone}?text={$encodedMessage}";
    }

    /**
     * Dispatch WhatsApp message or prepare Click-to-Chat URL
     *
     * @param array $recipientData ['name' => ..., 'phone' => ..., 'type' => ...]
     * @param string $message Text body
     * @param string|null $templateName Optional template name
     * @return array Status, click URL, and info
     */
    public function dispatch(array $recipientData, string $message, ?string $templateName = null): array {
        $rawPhone = $recipientData['phone'] ?? '';
        $normalizedPhone = $this->normalizePakistaniPhone($rawPhone);

        if (empty($normalizedPhone) || strlen($normalizedPhone) < 10) {
            return [
                'status'  => 'error',
                'message' => 'No valid WhatsApp number available.'
            ];
        }

        $recipientName = $recipientData['name'] ?? 'Recipient';
        $recipientType = $recipientData['type'] ?? 'Single Student';
        $mode          = $this->settings['mode'] ?? 'click_to_chat';

        $clickUrl = $this->generateClickToChatUrl($normalizedPhone, $message);

        // Always log outgoing attempt to whatsapp_history table
        WhatsAppHistory::log([
            'recipient_type' => $recipientType,
            'recipient_name' => $recipientName,
            'phone'          => $normalizedPhone,
            'message'        => $message,
            'template_name'  => $templateName,
            'status'         => ($mode === 'official_api') ? 'Pending' : 'Opened'
        ]);

        if ($mode === 'official_api' && !empty($this->settings['access_token'])) {
            // Official WhatsApp Business Cloud API Integration Placeholder
            // (Uses official graph.facebook.com endpoint when configured)
            return [
                'status'  => 'success',
                'mode'    => 'official_api',
                'message' => 'Message queued for Official WhatsApp Cloud API dispatch.',
                'url'     => $clickUrl,
                'phone'   => $normalizedPhone
            ];
        }

        return [
            'status'  => 'success',
            'mode'    => 'click_to_chat',
            'message' => 'WhatsApp Click-to-Chat link generated successfully.',
            'url'     => $clickUrl,
            'phone'   => $normalizedPhone
        ];
    }
}
