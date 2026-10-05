<?php
declare(strict_types=1);

require_once 'config/database.php';
require_once 'config/security.php';
require_once 'models/RosterRevisionModel.php';

class VerifyController {
    private function render(?array $verification, ?string $requestedCode, bool $searched): void {
        if (!headers_sent()) {
            header('X-Robots-Tag: noindex, nofollow, noarchive');
            header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
        }

        require 'views/verify/revision.php';
    }

    public function index(): void {
        $code = strtoupper(trim((string)($_GET['code'] ?? '')));
        if ($code !== '') {
            $this->revision();
            return;
        }

        $this->render(null, null, false);
    }

    public function revision(): void {
        $code = strtoupper(trim((string)($_GET['code'] ?? '')));
        $verification = null;

        if (preg_match('/^[A-F0-9]{32}$/', $code)) {
            try {
                $db = (new Database())->getConnection();
                $revisionModel = new RosterRevisionModel($db);
                $verification = $revisionModel->getPublicVerification($code);
            } catch (Throwable $e) {
                error_log('Public roster verification failed: ' . $e->getMessage());
                $verification = null;
            }
        }

        $this->render($verification, $code, true);
    }
}
