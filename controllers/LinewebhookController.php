<?php
require_once 'config/database.php';

class LinewebhookController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = (new Database())->getConnection();
    }

    public function index()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit;
        }

        $rawBody = (string)file_get_contents('php://input');
        $signature = (string)($_SERVER['HTTP_X_LINE_SIGNATURE'] ?? '');
        $secret = $this->setting('line_channel_secret');

        if ($rawBody === '' || $signature === '' || $secret === '') {
            http_response_code(400);
            exit;
        }

        $expected = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));
        if (!hash_equals($expected, $signature)) {
            http_response_code(401);
            exit;
        }

        $payload = json_decode($rawBody, true);
        foreach (($payload['events'] ?? []) as $event) {
            $source = is_array($event['source'] ?? null) ? $event['source'] : [];
            $sourceType = (string)($source['type'] ?? '');
            $sourceId = '';

            if ($sourceType === 'user') {
                $sourceId = (string)($source['userId'] ?? '');
            } elseif ($sourceType === 'group') {
                $sourceId = (string)($source['groupId'] ?? '');
            } elseif ($sourceType === 'room') {
                $sourceId = (string)($source['roomId'] ?? '');
            }

            if ($sourceId !== '') {
                $this->saveSetting('line_last_source_id', $sourceId);
                $this->saveSetting('line_last_source_type', $sourceType);
                $this->saveSetting('line_last_source_at', date('Y-m-d H:i:s'));
            }
        }

        http_response_code(200);
        echo 'OK';
        exit;
    }

    private function setting(string $key): string
    {
        $stmt = $this->db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        return trim((string)($stmt->fetchColumn() ?: ''));
    }

    private function saveSetting(string $key, string $value): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO system_settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        $stmt->execute([$key, $value]);
    }
}
?>