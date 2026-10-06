<?php

class LineMessagingService
{
    private PDO $db;
    private array $settings = [];

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->settings = $this->loadSettings();
    }

    private function loadSettings(): array
    {
        $keys = [
            'line_messaging_enabled',
            'line_channel_access_token',
            'line_channel_secret',
            'line_target_id',
            'line_messaging_on_roster',
            'line_messaging_on_leave',
            'line_messaging_on_swap',
            'line_messaging_on_holiday',
        ];

        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $this->db->prepare("
            SELECT setting_key, setting_value
            FROM system_settings
            WHERE setting_key IN ($placeholders)
        ");
        $stmt->execute($keys);

        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = (string)$row['setting_value'];
        }

        return $settings;
    }

    public function isEnabled(): bool
    {
        return ($this->settings['line_messaging_enabled'] ?? '0') === '1';
    }

    public function isConfigured(): bool
    {
        return $this->isEnabled()
            && trim($this->settings['line_channel_access_token'] ?? '') !== ''
            && trim($this->settings['line_target_id'] ?? '') !== '';
    }

    public function getTargetId(): string
    {
        return trim($this->settings['line_target_id'] ?? '');
    }

    public function sendEvent(string $event, string $message): array
    {
        $event = strtolower(trim($event));
        $map = [
            'roster' => 'line_messaging_on_roster',
            'leave' => 'line_messaging_on_leave',
            'swap' => 'line_messaging_on_swap',
            'holiday' => 'line_messaging_on_holiday',
        ];

        if (!isset($map[$event])) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Unknown LINE notification event.',
            ];
        }

        if (($this->settings[$map[$event]] ?? '0') !== '1') {
            return [
                'success' => true,
                'skipped' => true,
                'message' => 'LINE notification event is disabled.',
            ];
        }

        return $this->pushText($message);
    }

    public function pushText(string $message, ?string $targetId = null): array
    {
        if (!$this->isEnabled()) {
            return [
                'success' => true,
                'skipped' => true,
                'message' => 'LINE Messaging API is disabled.',
            ];
        }

        $token = trim($this->settings['line_channel_access_token'] ?? '');
        $target = trim($targetId ?: $this->getTargetId());
        $message = trim($message);

        if ($token === '' || $target === '') {
            return [
                'success' => false,
                'skipped' => false,
                'message' => 'LINE Messaging API configuration is incomplete.',
            ];
        }

        // LINE source IDs are issued as user/group/room IDs beginning with U/C/R.
        if (!preg_match('/^[UCR][0-9a-f]{32}$/i', $target)) {
            return [
                'success' => false,
                'skipped' => false,
                'message' => 'LINE target ID format is invalid.',
            ];
        }

        if ($message === '') {
            return [
                'success' => false,
                'skipped' => false,
                'message' => 'Message is empty.',
            ];
        }

        if (mb_strlen($message, 'UTF-8') > 4900) {
            $message = mb_substr($message, 0, 4890, 'UTF-8') . '…';
        }

        $payload = json_encode([
            'to' => $target,
            'messages' => [
                [
                    'type' => 'text',
                    'text' => $message,
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($payload === false) {
            return [
                'success' => false,
                'skipped' => false,
                'message' => 'Unable to encode LINE message.',
            ];
        }

        $ch = curl_init('https://api.line.me/v2/bot/message/push');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
                'X-Line-Retry-Key: ' . $this->createUuidV4(),
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $responseBody = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($responseBody === false) {
            error_log('LINE Messaging API transport error: ' . $curlError);
            return [
                'success' => false,
                'skipped' => false,
                'status' => $httpCode,
                'message' => 'Unable to connect to LINE Messaging API.',
            ];
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'skipped' => false,
                'status' => $httpCode,
                'message' => 'Message sent.',
            ];
        }

        $decoded = json_decode((string)$responseBody, true);
        $apiMessage = is_array($decoded) && isset($decoded['message'])
            ? (string)$decoded['message']
            : 'LINE Messaging API rejected the request.';

        error_log(sprintf(
            'LINE Messaging API error HTTP %d: %s',
            $httpCode,
            $apiMessage
        ));

        return [
            'success' => false,
            'skipped' => false,
            'status' => $httpCode,
            'message' => $apiMessage,
        ];
    }

    private function createUuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
?>