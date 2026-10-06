<?php

require_once __DIR__ . '/../models/NotificationModel.php';
require_once __DIR__ . '/LineMessagingService.php';

class NotificationService
{
    private PDO $db;
    private NotificationModel $notificationModel;
    private LineMessagingService $line;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->notificationModel = new NotificationModel($db);
        $this->line = new LineMessagingService($db);
    }

    public function addInApp(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?string $link = null
    ): bool {
        return $this->notificationModel->addNotification(
            $userId,
            $type,
            $title,
            $message,
            $link
        );
    }

    public function notifyRoles(
        int $hospitalId,
        array $roles,
        string $type,
        string $title,
        string $message,
        ?string $link = null
    ): int {
        $roles = array_values(array_unique(array_filter(array_map(
            static fn($role) => strtoupper(trim((string)$role)),
            $roles
        ))));

        if (!$roles) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        $params = array_merge([$hospitalId], $roles);

        $stmt = $this->db->prepare("
            SELECT id
            FROM users
            WHERE hospital_id = ?
              AND role IN ($placeholders)
              AND deleted_at IS NULL
              AND is_active = 1
        ");
        $stmt->execute($params);

        $count = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($this->addInApp((int)$row['id'], $type, $title, $message, $link)) {
                $count++;
            }
        }

        return $count;
    }

    public function sendLineEvent(string $event, string $message): array
    {
        return $this->line->sendEvent($event, $message);
    }

    public function testLine(string $message): array
    {
        return $this->line->pushText($message);
    }

    public function isLineConfigured(): bool
    {
        return $this->line->isConfigured();
    }
}
?>