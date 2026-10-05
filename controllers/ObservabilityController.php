<?php
declare(strict_types=1);

require_once 'config/database.php';
require_once 'config/security.php';
require_once 'controllers/LogsController.php';
require_once 'models/AppEventModel.php';
require_once 'models/BackgroundJobModel.php';
require_once 'lib/ObservabilityService.php';

class ObservabilityController {
    private function requireAdmin(): void {
        security_start_session();

        if (!isset($_SESSION['user'])) {
            header('Location: index.php?c=auth&a=index');
            exit;
        }

        $role = strtoupper((string)($_SESSION['user']['role'] ?? ''));
        if (!in_array($role, ['ADMIN', 'SUPERADMIN'], true)) {
            http_response_code(403);
            $_SESSION['error_msg'] = 'คุณไม่มีสิทธิ์เข้าถึงศูนย์ติดตามสถานะระบบ';
            header('Location: index.php?c=dashboard');
            exit;
        }
    }

    private function requireMutation(): void {
        $this->requireAdmin();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            exit('Method Not Allowed');
        }

        if (!security_is_valid_post_csrf()) {
            http_response_code(403);
            $_SESSION['error_msg'] = 'คำขอหมดอายุหรือไม่ถูกต้อง กรุณาลองใหม่';
            header('Location: index.php?c=observability');
            exit;
        }
    }

    public function index(): void {
        $this->requireAdmin();

        $db = (new Database())->getConnection();
        $service = new ObservabilityService($db);
        $eventModel = new AppEventModel($db);
        $jobModel = new BackgroundJobModel($db);

        try {
            $summary = $service->currentSummary();
            $openEvents = $eventModel->getOpenEvents(100);
            $failedJobs = $jobModel->getFailedJobs(100);
            $recentJobs = $jobModel->getRecentJobs(50);
            $snapshots = $service->recentSnapshots(48);
        } catch (Throwable $e) {
            error_log('Observability dashboard load failed: ' . $e->getMessage());
            $summary = [
                'overall_status' => 'UNHEALTHY',
                'db_status' => 'ERROR',
                'migration' => ['pending' => 0, 'blocking' => 0],
                'queue' => ['pending' => 0, 'running' => 0, 'failed' => 0, 'done' => 0, 'delayed' => 0],
                'events' => ['open_total' => 0, 'critical_open' => 0, 'error_open' => 0, 'events_24h' => 0, 'open_24h' => 0],
                'disk_free_mb' => null,
            ];
            $openEvents = [];
            $failedJobs = [];
            $recentJobs = [];
            $snapshots = [];
            $_SESSION['error_msg'] = 'ไม่สามารถโหลดข้อมูลติดตามระบบได้ครบถ้วน';
        }

        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/observability/index.php';
        echo "</main></div></body></html>";
    }

    public function resolve_event(): void {
        $this->requireMutation();

        $eventId = max(0, (int)($_POST['event_id'] ?? 0));
        $userId = (int)($_SESSION['user']['id'] ?? 0);

        $db = (new Database())->getConnection();
        $model = new AppEventModel($db);

        if ($eventId > 0 && $model->resolve($eventId, $userId)) {
            LogsController::addLog($db, $userId, LogsController::ACTION_UPDATE, "Resolve observability event #{$eventId}");
            $_SESSION['success_msg'] = 'ปิดเหตุการณ์เรียบร้อยแล้ว';
        } else {
            $_SESSION['error_msg'] = 'ไม่พบเหตุการณ์หรือไม่สามารถปิดเหตุการณ์ได้';
        }

        header('Location: index.php?c=observability');
        exit;
    }

    public function retry_job(): void {
        $this->requireMutation();

        $jobId = max(0, (int)($_POST['job_id'] ?? 0));
        $userId = (int)($_SESSION['user']['id'] ?? 0);

        $db = (new Database())->getConnection();
        $model = new BackgroundJobModel($db);

        if ($jobId > 0 && $model->retryFailed($jobId)) {
            LogsController::addLog($db, $userId, LogsController::ACTION_UPDATE, "Retry failed background job #{$jobId}");
            $_SESSION['success_msg'] = 'นำงานกลับเข้าคิวเพื่อ Retry แล้ว';
        } else {
            $_SESSION['error_msg'] = 'งานนี้ไม่อยู่ในสถานะ FAILED หรือไม่สามารถ Retry ได้';
        }

        header('Location: index.php?c=observability');
        exit;
    }

    public function capture_health(): void {
        $this->requireMutation();

        $db = (new Database())->getConnection();
        $service = new ObservabilityService($db);
        $snapshotId = $service->captureHealthSnapshot();

        LogsController::addLog(
            $db,
            (int)($_SESSION['user']['id'] ?? 0),
            LogsController::ACTION_CREATE,
            "Capture system health snapshot #{$snapshotId}"
        );

        $_SESSION['success_msg'] = 'บันทึก Health Snapshot ล่าสุดแล้ว';
        header('Location: index.php?c=observability');
        exit;
    }
}
