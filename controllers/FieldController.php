<?php

require_once 'config/database.php';
require_once 'config/security.php';
require_once 'models/FieldVisitModel.php';
require_once 'models/HospitalModel.php';
require_once 'controllers/LogsController.php';

class FieldController {
    private const MAX_PHOTOS = 3;
    private const MAX_PHOTO_BYTES = 5242880; // 5 MB

    private function currentUser(): array {
        security_start_session();
        if (!isset($_SESSION['user'])) {
            header('Location: index.php?c=auth&a=index');
            exit;
        }

        $user = $_SESSION['user'];
        $role = strtoupper((string)($user['role'] ?? 'STAFF'));

        if ($role === 'HR') {
            $_SESSION['error_msg'] = 'คุณไม่มีสิทธิ์เข้าถึงข้อมูลเยี่ยมบ้าน';
            header('Location: index.php?c=dashboard');
            exit;
        }

        return $user;
    }

    private function requirePost(): void {
        if (!security_is_valid_post_csrf()) {
            http_response_code(403);
            $_SESSION['error_msg'] = 'คำขอไม่ถูกต้องหรือหมดอายุ กรุณาลองใหม่';
            header('Location: index.php?c=field');
            exit;
        }
    }

    private function isGlobal(array $user): bool {
        return in_array(strtoupper((string)($user['role'] ?? '')), ['SUPERADMIN', 'ADMIN'], true);
    }

    private function cleanString(string $key, int $max = 255): string {
        $value = trim((string)($_POST[$key] ?? ''));
        if (mb_strlen($value, 'UTF-8') > $max) {
            $value = mb_substr($value, 0, $max, 'UTF-8');
        }
        return $value;
    }

    private function nullableInt(string $key, int $min, int $max): ?int {
        $raw = trim((string)($_POST[$key] ?? ''));
        if ($raw === '') return null;
        if (!preg_match('/^-?\d+$/', $raw)) {
            throw new InvalidArgumentException("ข้อมูล {$key} ไม่ถูกต้อง");
        }
        $value = (int)$raw;
        if ($value < $min || $value > $max) {
            throw new InvalidArgumentException("ข้อมูล {$key} อยู่นอกช่วงที่ระบบรองรับ");
        }
        return $value;
    }

    private function nullableFloat(string $key, float $min, float $max): ?float {
        $raw = trim((string)($_POST[$key] ?? ''));
        if ($raw === '') return null;
        if (!is_numeric($raw)) {
            throw new InvalidArgumentException("ข้อมูล {$key} ไม่ถูกต้อง");
        }
        $value = (float)$raw;
        if ($value < $min || $value > $max) {
            throw new InvalidArgumentException("ข้อมูล {$key} อยู่นอกช่วงที่ระบบรองรับ");
        }
        return $value;
    }

    private function validDate(string $value): bool {
        $dt = DateTime::createFromFormat('Y-m-d', $value);
        return $dt instanceof DateTime && $dt->format('Y-m-d') === $value;
    }

    private function collectPhotos(): array {
        if (empty($_FILES['photos']) || !is_array($_FILES['photos']['name'] ?? null)) {
            return [];
        }

        $names = $_FILES['photos']['name'];
        $tmpNames = $_FILES['photos']['tmp_name'];
        $sizes = $_FILES['photos']['size'];
        $errors = $_FILES['photos']['error'];

        $photos = [];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        foreach ($names as $index => $originalName) {
            $error = (int)($errors[$index] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) continue;
            if ($error !== UPLOAD_ERR_OK) {
                throw new RuntimeException('อัปโหลดรูปไม่สำเร็จ กรุณาลองใหม่');
            }

            if (count($photos) >= self::MAX_PHOTOS) {
                throw new RuntimeException('แนบรูปได้ไม่เกิน 3 รูปต่อการเยี่ยมบ้าน');
            }

            $size = (int)($sizes[$index] ?? 0);
            if ($size <= 0 || $size > self::MAX_PHOTO_BYTES) {
                throw new RuntimeException('รูปแต่ละไฟล์ต้องมีขนาดไม่เกิน 5 MB');
            }

            $tmp = (string)($tmpNames[$index] ?? '');
            if ($tmp === '' || !is_uploaded_file($tmp)) {
                throw new RuntimeException('ไม่พบไฟล์อัปโหลดที่ถูกต้อง');
            }

            $mime = (string)$finfo->file($tmp);
            if (!isset($allowed[$mime])) {
                throw new RuntimeException('รองรับเฉพาะรูป JPG, PNG และ WebP');
            }

            $photos[] = [
                'tmp' => $tmp,
                'original' => basename((string)$originalName),
                'size' => $size,
                'mime' => $mime,
                'ext' => $allowed[$mime],
            ];
        }

        return $photos;
    }

    public function index(): void {
        $user = $this->currentUser();
        $db = (new Database())->getConnection();
        $model = new FieldVisitModel($db);

        $filters = [
            'status' => in_array($_GET['status'] ?? '', ['DRAFT', 'COMPLETED'], true) ? $_GET['status'] : '',
            'date_from' => $this->validDate((string)($_GET['date_from'] ?? '')) ? $_GET['date_from'] : '',
            'date_to' => $this->validDate((string)($_GET['date_to'] ?? '')) ? $_GET['date_to'] : '',
            'q' => mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100, 'UTF-8'),
        ];

        $visits = $model->getVisibleVisits($user, $filters, 150);
        $summary = $model->getSummary($user);
        $canSelectHospital = $this->isGlobal($user);
        $hospitals = [];

        if ($canSelectHospital) {
            $hospitals = (new HospitalModel($db))->getAllHospitals();
        }

        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/field/index.php';
        echo '</main></div></body></html>';
    }

    public function save(): void {
        $this->requirePost();
        $user = $this->currentUser();

        try {
            $db = (new Database())->getConnection();
            $model = new FieldVisitModel($db);

            $hospitalId = $this->isGlobal($user)
                ? (int)($_POST['hospital_id'] ?? 0)
                : (int)($user['hospital_id'] ?? 0);

            if ($hospitalId <= 0) {
                throw new InvalidArgumentException('กรุณาเลือกหน่วยบริการ');
            }

            $visitDate = trim((string)($_POST['visit_date'] ?? ''));
            if (!$this->validDate($visitDate)) {
                throw new InvalidArgumentException('วันที่เยี่ยมบ้านไม่ถูกต้อง');
            }

            $patientRef = $this->cleanString('patient_ref', 50);
            if ($patientRef === '') {
                throw new InvalidArgumentException('กรุณาระบุ HN/รหัสผู้รับบริการ/รหัสครัวเรือน');
            }

            $visitTypes = ['HOME_VISIT', 'CHRONIC_FOLLOWUP', 'WOUND_CARE', 'MATERNAL_CHILD', 'ELDERLY', 'OTHER'];
            $visitType = strtoupper(trim((string)($_POST['visit_type'] ?? 'HOME_VISIT')));
            if (!in_array($visitType, $visitTypes, true)) {
                $visitType = 'OTHER';
            }

            $status = strtoupper(trim((string)($_POST['status'] ?? 'DRAFT')));
            if (!in_array($status, ['DRAFT', 'COMPLETED'], true)) {
                $status = 'DRAFT';
            }

            $photos = $this->collectPhotos();
            $photoConsent = isset($_POST['photo_consent']) ? 1 : 0;
            if ($photos && !$photoConsent) {
                throw new InvalidArgumentException('กรุณายืนยันสิทธิ์/ความยินยอมก่อนแนบรูป');
            }

            $data = [
                'hospital_id' => $hospitalId,
                'created_by' => (int)$user['id'],
                'visit_date' => $visitDate,
                'patient_ref' => $patientRef,
                'patient_name' => $this->cleanString('patient_name', 150) ?: null,
                'patient_age' => $this->nullableInt('patient_age', 0, 130),
                'visit_type' => $visitType,
                'chief_concern' => $this->cleanString('chief_concern', 255) ?: null,
                'systolic' => $this->nullableInt('systolic', 40, 320),
                'diastolic' => $this->nullableInt('diastolic', 20, 220),
                'pulse' => $this->nullableInt('pulse', 20, 260),
                'temperature' => $this->nullableFloat('temperature', 25, 50),
                'spo2' => $this->nullableInt('spo2', 1, 100),
                'weight' => $this->nullableFloat('weight', 0.1, 600),
                'height' => $this->nullableFloat('height', 20, 280),
                'symptoms' => $this->cleanString('symptoms', 3000) ?: null,
                'assessment' => $this->cleanString('assessment', 3000) ?: null,
                'care_plan' => $this->cleanString('care_plan', 3000) ?: null,
                'latitude' => $this->nullableFloat('latitude', -90, 90),
                'longitude' => $this->nullableFloat('longitude', -180, 180),
                'accuracy_m' => $this->nullableFloat('accuracy_m', 0, 100000),
                'address_note' => $this->cleanString('address_note', 255) ?: null,
                'photo_consent' => $photoConsent,
                'status' => $status,
            ];

            $movedFiles = [];
            $db->beginTransaction();

            try {
                $visitId = $model->createVisit($data);

                if ($photos) {
                    $storageRoot = dirname(__DIR__) . '/storage/field_visits';
                    $targetDir = $storageRoot . '/' . $hospitalId . '/' . $visitId;

                    if (!is_dir($targetDir) && !mkdir($targetDir, 0770, true) && !is_dir($targetDir)) {
                        throw new RuntimeException('ไม่สามารถสร้างพื้นที่จัดเก็บรูปได้');
                    }

                    foreach ($photos as $photo) {
                        $filename = bin2hex(random_bytes(16)) . '.' . $photo['ext'];
                        $absolute = $targetDir . '/' . $filename;

                        if (!move_uploaded_file($photo['tmp'], $absolute)) {
                            throw new RuntimeException('ไม่สามารถจัดเก็บรูปที่อัปโหลดได้');
                        }

                        $movedFiles[] = $absolute;
                        $relative = 'field_visits/' . $hospitalId . '/' . $visitId . '/' . $filename;
                        $model->addPhoto($visitId, $relative, $photo['original'], $photo['mime'], $photo['size']);
                    }
                }

                $db->commit();
            } catch (Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
                foreach ($movedFiles as $path) {
                    if (is_file($path)) @unlink($path);
                }
                throw $e;
            }

            LogsController::addLog(
                $db,
                (int)$user['id'],
                LogsController::ACTION_CREATE,
                'บันทึกเยี่ยมบ้าน ID ' . $visitId . ' สถานะ ' . $status
            );

            $_SESSION['success_msg'] = $status === 'COMPLETED'
                ? 'บันทึกผลเยี่ยมบ้านเรียบร้อย'
                : 'บันทึกร่างเยี่ยมบ้านเรียบร้อย';

            header('Location: index.php?c=field&saved=1');
            exit;
        } catch (InvalidArgumentException|RuntimeException $e) {
            $_SESSION['error_msg'] = $e->getMessage();
        } catch (Throwable $e) {
            error_log('Field visit save failed: ' . $e->getMessage());
            $_SESSION['error_msg'] = 'เกิดข้อผิดพลาดภายในระบบ ไม่สามารถบันทึกข้อมูลได้';
        }

        header('Location: index.php?c=field');
        exit;
    }

    public function photo(): void {
        $user = $this->currentUser();
        $photoId = (int)($_GET['id'] ?? 0);
        if ($photoId <= 0) {
            http_response_code(404);
            exit;
        }

        $db = (new Database())->getConnection();
        $photo = (new FieldVisitModel($db))->getPhotoVisible($photoId, $user);

        if (!$photo) {
            http_response_code(404);
            exit;
        }

        $storageBase = realpath(dirname(__DIR__) . '/storage');
        $filePath = realpath(dirname(__DIR__) . '/storage/' . ltrim((string)$photo['stored_path'], '/'));

        if (!$storageBase || !$filePath || !str_starts_with($filePath, $storageBase . DIRECTORY_SEPARATOR) || !is_file($filePath)) {
            http_response_code(404);
            exit;
        }

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        $mime = in_array($photo['mime_type'], $allowedMimes, true) ? $photo['mime_type'] : 'application/octet-stream';

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($filePath));
        header('Content-Disposition: inline; filename*=UTF-8\'\'' . rawurlencode((string)$photo['original_name']));
        header('Cache-Control: private, max-age=300');
        header('X-Content-Type-Options: nosniff');
        readfile($filePath);
        exit;
    }

    public function export_csv(): void {
        $user = $this->currentUser();
        $db = (new Database())->getConnection();
        $model = new FieldVisitModel($db);

        $filters = [
            'status' => in_array($_GET['status'] ?? '', ['DRAFT', 'COMPLETED'], true) ? $_GET['status'] : '',
            'date_from' => $this->validDate((string)($_GET['date_from'] ?? '')) ? $_GET['date_from'] : '',
            'date_to' => $this->validDate((string)($_GET['date_to'] ?? '')) ? $_GET['date_to'] : '',
            'q' => mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100, 'UTF-8'),
        ];

        $rows = $model->getVisibleVisits($user, $filters, 5000);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="field_visits_' . date('Ymd_His') . '.csv"');

        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['วันที่', 'หน่วยบริการ', 'รหัสผู้รับบริการ', 'ชื่อ', 'ประเภท', 'อาการ/เหตุผล', 'ผู้บันทึก', 'สถานะ']);

        foreach ($rows as $row) {
            fputcsv($out, [
                $row['visit_date'],
                $row['hospital_name'],
                $row['patient_ref'],
                $row['patient_name'],
                $row['visit_type'],
                $row['chief_concern'],
                $row['created_by_name'],
                $row['status'],
            ]);
        }

        fclose($out);
        exit;
    }
}
