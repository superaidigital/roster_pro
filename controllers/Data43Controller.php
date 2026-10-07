<?php
require_once 'config/database.php';
require_once 'models/Data43SubmissionModel.php';
require_once 'services/Data43ImportService.php';
require_once 'services/Data43MetricRegistry.php';
require_once 'services/Data43RegistryService.php';
require_once 'services/Data43FormRegistry.php';
require_once 'services/Data43SystemHealthService.php';
require_once 'services/Data43StorageService.php';
require_once 'controllers/LogsController.php';

class Data43Controller
{
    private const ALLOWED_ROLES = ['SUPERADMIN','ADMIN','DIRECTOR','SCHEDULER','STAFF'];
    private const ADMIN_ROLES = ['SUPERADMIN','ADMIN'];

    private function role(): string
    {
        return strtoupper(trim((string)($_SESSION['user']['role'] ?? '')));
    }

    private function data43Role(): string
    {
        return in_array($this->role(), self::ADMIN_ROLES, true) ? 'ADMIN' : 'SURVEYOR';
    }

    private function requireAccess(): void
    {
        if (!isset($_SESSION['user'])) {
            header('Location: index.php?c=auth&a=index');
            exit;
        }

        if (!in_array($this->role(), self::ALLOWED_ROLES, true)) {
            http_response_code(403);
            $_SESSION['error_msg'] = 'คุณไม่มีสิทธิ์ใช้งานเมนูนำส่งข้อมูล 43 แฟ้ม';
            header('Location: index.php?c=dashboard');
            exit;
        }
    }

    private function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    private function verifyCsrf(): void
    {
        $session = (string)($_SESSION['csrf_token'] ?? '');
        $posted = (string)($_POST['csrf_token'] ?? '');

        if ($session === '' || $posted === '' || !hash_equals($session, $posted)) {
            throw new RuntimeException('คำขอหมดอายุหรือ CSRF Token ไม่ถูกต้อง');
        }
    }

    private function selectedHospitalId(): ?int
    {
        $role = $this->role();

        if (in_array($role, self::ADMIN_ROLES, true)) {
            $candidate = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT);
            return $candidate ?: null;
        }

        $hospitalId = (int)($_SESSION['user']['hospital_id'] ?? 0);
        return $hospitalId > 0 ? $hospitalId : null;
    }

    private function resolveUploadHospitalId(): int
    {
        $role = $this->role();

        if (in_array($role, self::ADMIN_ROLES, true)) {
            $hospitalId = filter_input(INPUT_POST, 'hospital_id', FILTER_VALIDATE_INT);
            if (!$hospitalId) {
                throw new RuntimeException('กรุณาเลือก รพ.สต. ที่ต้องการนำส่งข้อมูล');
            }
            return (int)$hospitalId;
        }

        $hospitalId = (int)($_SESSION['user']['hospital_id'] ?? 0);
        if ($hospitalId <= 0) {
            throw new RuntimeException('บัญชีนี้ยังไม่ได้ผูกกับ รพ.สต.');
        }

        return $hospitalId;
    }

    public function index(): void
    {
        $this->requireAccess();

        $db = (new Database())->getConnection();
        $model = new Data43SubmissionModel($db);

        $schema_ready = $model->schemaReady();
        $csrf_token = $this->csrfToken();
        $selected_hospital_id = $this->selectedHospitalId();
        $is_admin = in_array($this->role(), self::ADMIN_ROLES, true);

        $hospitals = [];
        if ($is_admin) {
            $stmt = $db->query("
                SELECT id, hospital_code, name
                FROM hospitals
                WHERE is_active = 1
                  AND deleted_at IS NULL
                ORDER BY name ASC
            ");
            $hospitals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $history = $schema_ready
            ? $model->getHistory($is_admin ? $selected_hospital_id : $selected_hospital_id, 100)
            : [];

        $summary = [
            'total' => count($history),
            'complete' => 0,
            'incomplete' => 0,
            'failed' => 0,
        ];

        foreach ($history as $row) {
            if ($row['status'] === 'COMPLETE') $summary['complete']++;
            elseif ($row['status'] === 'INCOMPLETE') $summary['incomplete']++;
            elseif ($row['status'] === 'FAILED') $summary['failed']++;
        }

        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/data43/index.php';
        echo "</main></div></body></html>";
    }

    public function dashboard(): void
    {
        $this->requireAccess();

        $db = (new Database())->getConnection();
        $model = new Data43SubmissionModel($db);

        $schema_ready = $model->schemaReady();
        $is_admin = in_array($this->role(), self::ADMIN_ROLES, true);
        $selected_hospital_id = $this->selectedHospitalId();
        $report_month = trim((string)($_GET['month'] ?? date('Y-m')));

        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $report_month)) {
            $report_month = date('Y-m');
        }

        $hospitals = [];
        if ($is_admin) {
            $stmt = $db->query("
                SELECT id, hospital_code, name
                FROM hospitals
                WHERE is_active = 1
                  AND deleted_at IS NULL
                  AND COALESCE(hospital_code, '') <> '0'
                ORDER BY name ASC
            ");
            $hospitals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $scopeHospitalId = $is_admin ? $selected_hospital_id : $selected_hospital_id;

        $tracking = $schema_ready
            ? $model->getHospitalTracking($report_month, $scopeHospitalId)
            : [];
        $latest_rows = $schema_ready
            ? $model->getLatestStatusRows($report_month, $scopeHospitalId)
            : [];
        $trend = $schema_ready
            ? $model->getTrend($scopeHospitalId, 12)
            : [];
        $low_coverage = $schema_ready
            ? $model->getLowestFileCoverage($report_month, $scopeHospitalId, 10)
            : [];
        $attempts = $schema_ready
            ? $model->getMonthSubmissionAttempts($report_month, $scopeHospitalId)
            : 0;

        $total_hospitals = $schema_ready
            ? $model->getActiveHospitalCount($scopeHospitalId)
            : 0;

        $dashboard = [
            'total_hospitals' => $total_hospitals,
            'submitted' => 0,
            'not_submitted' => 0,
            'complete' => 0,
            'incomplete' => 0,
            'failed' => 0,
            'processing' => 0,
            'submission_rate' => 0.0,
            'completeness_rate' => 0.0,
            'total_rows' => 0,
            'attempts' => $attempts,
        ];

        $detectedSum = 0;
        $expectedSum = 0;

        foreach ($tracking as $row) {
            if (empty($row['submission_id'])) {
                $dashboard['not_submitted']++;
                continue;
            }

            $dashboard['submitted']++;
            $status = (string)($row['status'] ?? '');
            if ($status === 'COMPLETE') $dashboard['complete']++;
            elseif ($status === 'INCOMPLETE') $dashboard['incomplete']++;
            elseif ($status === 'FAILED') $dashboard['failed']++;
            elseif ($status === 'PROCESSING') $dashboard['processing']++;

            $detectedSum += (int)($row['detected_files'] ?? 0);
            $expectedSum += max(0, (int)($row['expected_files'] ?? 0));
            $dashboard['total_rows'] += (int)($row['total_rows'] ?? 0);
        }

        if ($dashboard['total_hospitals'] > 0) {
            $dashboard['submission_rate'] = round(
                ($dashboard['submitted'] / $dashboard['total_hospitals']) * 100,
                1
            );
        }

        if ($expectedSum > 0) {
            $dashboard['completeness_rate'] = round(
                ($detectedSum / $expectedSum) * 100,
                1
            );
        }

        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/data43/dashboard.php';
        echo "</main></div></body></html>";
    }

    public function upload(): void
    {
        $this->requireAccess();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?c=data43');
            exit;
        }

        $db = (new Database())->getConnection();
        $model = new Data43SubmissionModel($db);

        try {
            $this->verifyCsrf();

            if (!$model->schemaReady()) {
                throw new RuntimeException('กรุณารัน migration สำหรับระบบ 43 แฟ้มก่อน');
            }

            $hospitalId = $this->resolveUploadHospitalId();
            $reportMonth = trim((string)($_POST['report_month'] ?? ''));

            if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $reportMonth)) {
                throw new RuntimeException('รอบเดือนข้อมูลไม่ถูกต้อง');
            }

            $stmt = $db->prepare("
                SELECT hospital_code, name
                FROM hospitals
                WHERE id = ?
                  AND is_active = 1
                  AND deleted_at IS NULL
                LIMIT 1
            ");
            $stmt->execute([$hospitalId]);
            $hospitalRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$hospitalRow) {
                throw new RuntimeException('ไม่พบ รพ.สต. หรือหน่วยบริการถูกปิดใช้งาน');
            }

            $expectedHospcode = trim((string)($hospitalRow['hospital_code'] ?? ''));
            if ($expectedHospcode !== '' && ctype_digit($expectedHospcode)) {
                $expectedHospcode = str_pad($expectedHospcode, 5, '0', STR_PAD_LEFT);
            }

            $upload = $_FILES['zip_file'] ?? null;
            if (!is_array($upload)) {
                throw new RuntimeException('กรุณาเลือกไฟล์ ZIP');
            }

            $workDir = Data43StorageService::createJobDirectory('import');

            $service = new Data43ImportService();
            $submissionId = null;

            try {
                $inspection = $service->inspectUploadedZip($upload, $workDir, $expectedHospcode);

                $originalFilename = basename((string)($upload['name'] ?? 'submission.zip'));
                $originalFilename = preg_replace('/[^A-Za-z0-9._\-ก-๙ ]/u', '_', $originalFilename) ?: 'submission.zip';

                $clientIp = (string)($_SERVER['REMOTE_ADDR'] ?? '');
                $ipHash = $clientIp !== ''
                    ? hash_hmac('sha256', $clientIp, session_id())
                    : null;

                $quality = $inspection['quality_summary'] ?? [];

                $duplicate = $model->findDuplicateArchive(
                    $hospitalId,
                    $reportMonth,
                    (string)$inspection['archive_sha256']
                );
                if ($duplicate) {
                    throw new RuntimeException(
                        'ไฟล์ ZIP ชุดนี้เคยนำส่งแล้วในรอบเดือนเดียวกัน (Submission #'
                        . (int)$duplicate['id'] . ')'
                    );
                }

                $expectedFiles = max(1, (int)($quality['expected_files'] ?? 45));
                $detectedExpected = max(0, (int)($quality['detected_expected_files'] ?? 0));

                $submissionId = $model->createSubmission([
                    'hospital_id' => $hospitalId,
                    'report_month' => $reportMonth,
                    'original_filename' => mb_substr($originalFilename, 0, 180, 'UTF-8'),
                    'archive_sha256' => $inspection['archive_sha256'],
                    'purpose_code' => 'PUBLIC_HEALTH_REPORTING',
                    'standard_version' => (string)($quality['standard_version'] ?? '2.4.1'),
                    'profile_code' => (string)($quality['profile_code'] ?? 'RPHST_V241'),
                    'expected_files' => $expectedFiles,
                    'uploaded_by' => (int)$_SESSION['user']['id'],
                    'client_ip_hash' => $ipHash,
                ]);

                $totalRows = 0;
                $validFiles = 0;

                foreach ($inspection['files'] as $file) {
                    $model->addFile($submissionId, $file);

                    if (($file['status'] ?? '') === 'VALID') {
                        $validFiles++;
                        if ($file['row_count'] !== null) {
                            $totalRows += (int)$file['row_count'];
                        }
                    }
                }

                // Persist aggregate quality diagnostics; no raw person identifiers are stored.
                $model->saveQualitySummary($submissionId, $quality);
                $model->saveQualityIssues($submissionId, (array)($inspection['quality_issues'] ?? []));

                // Persist privacy-preserving spatial aggregates when the spatial schema is installed.
                // Failure here must not invalidate the core 43-file submission.
                if (!empty($inspection['spatial_metrics']) && $model->spatialSchemaReady()) {
                    try {
                        $model->addSpatialMetrics(
                            $submissionId,
                            $hospitalId,
                            $reportMonth,
                            $inspection['spatial_metrics']
                        );
                    } catch (Throwable $spatialError) {
                        error_log('Data43 spatial aggregate error: ' . $spatialError->getMessage());
                    }
                }

                $detected = $detectedExpected;
                $missingCodes = array_values((array)($quality['missing_expected_codes'] ?? []));
                $headerIssues = (array)($quality['header_issues'] ?? []);
                $invalidExpectedCodes = array_values((array)($quality['invalid_expected_codes'] ?? []));
                $rowQualityIssues = (array)($inspection['quality_issues'] ?? []);
                $rowQualityErrorCount = 0;
                foreach ($rowQualityIssues as $issue) {
                    if (($issue['severity'] ?? '') === 'ERROR') {
                        $rowQualityErrorCount += (int)($issue['issue_count'] ?? 0);
                    }
                }

                $strictProfile = ((string)($quality['profile_code'] ?? '')) === Data43StandardV241::PROFILE;
                $status = (
                    $strictProfile
                    && $detected >= $expectedFiles
                    && empty($headerIssues)
                    && empty($invalidExpectedCodes)
                    && $rowQualityErrorCount === 0
                ) ? 'COMPLETE' : 'INCOMPLETE';

                $summaryParts = [];
                if ($detected < $expectedFiles) {
                    $summaryParts[] = "ตรวจพบ {$detected}/{$expectedFiles} โครงสร้างสำหรับ รพ.สต.";
                }
                if (!empty($missingCodes)) {
                    $preview = implode(', ', array_slice($missingCodes, 0, 8));
                    $more = count($missingCodes) > 8 ? ' +' . (count($missingCodes) - 8) . ' แฟ้ม' : '';
                    $summaryParts[] = 'ขาด: ' . $preview . $more;
                }
                if (!empty($headerIssues)) {
                    $summaryParts[] = 'พบปัญหาโครงสร้างคอลัมน์ ' . count($headerIssues) . ' แฟ้ม';
                }
                if (!empty($invalidExpectedCodes)) {
                    $summaryParts[] = 'แฟ้มที่ยังประมวลผลไม่ได้: ' . implode(', ', array_slice($invalidExpectedCodes, 0, 8));
                }
                if (!$strictProfile) {
                    $summaryParts[] = 'รูปแบบไฟล์เป็น Legacy/Partial compatibility ยังไม่ผ่านมาตรฐาน 2.4.1 แบบ strict';
                }
                if ($rowQualityErrorCount > 0) {
                    $summaryParts[] = 'พบข้อผิดพลาดระดับข้อมูล ' . number_format($rowQualityErrorCount) . ' รายการ';
                }
                $errorSummary = $summaryParts ? mb_substr(implode(' | ', $summaryParts), 0, 500, 'UTF-8') : null;

                $model->finishSubmission(
                    $submissionId,
                    $status,
                    $detected,
                    $totalRows,
                    $errorSummary
                );

                LogsController::addLog(
                    $db,
                    $_SESSION['user']['id'],
                    LogsController::ACTION_CREATE,
                    "นำส่งข้อมูลมาตรฐานสุขภาพ v2.4.1 Submission #{$submissionId}, Hospital #{$hospitalId}, รอบ {$reportMonth}, ตรวจพบ {$detected}/{$expectedFiles} โครงสร้าง รพ.สต."
                );

                $_SESSION['success_msg'] = $status === 'COMPLETE'
                    ? "นำส่งข้อมูลสำเร็จและผ่าน Profile รพ.สต. Version 2.4.1 แบบ strict ({$detected}/{$expectedFiles} โครงสร้าง)"
                    : "รับไฟล์เรียบร้อยเพื่อการตรวจสอบ/วิเคราะห์ แต่ยังไม่ผ่านมาตรฐาน 2.4.1 แบบสมบูรณ์ กรุณาตรวจ Quality Report";

            } catch (Throwable $e) {
                if ($submissionId) {
                    $model->finishSubmission(
                        $submissionId,
                        'FAILED',
                        0,
                        0,
                        mb_substr($e->getMessage(), 0, 500, 'UTF-8')
                    );
                }
                throw $e;
            } finally {
                Data43StorageService::recursiveDelete($workDir);
            }

        } catch (Throwable $e) {
            error_log('Data43 upload error: ' . $e->getMessage());
            $_SESSION['error_msg'] = $e->getMessage();
        }

        $query = '';
        if (in_array($this->role(), self::ADMIN_ROLES, true)) {
            $hid = filter_input(INPUT_POST, 'hospital_id', FILTER_VALIDATE_INT);
            if ($hid) $query = '&hospital_id=' . (int)$hid;
        }

        header('Location: index.php?c=data43&a=index' . $query);
        exit;
    }

    public function health(): void
    {
        $this->requireAccess();

        if ($this->data43Role() !== 'ADMIN') {
            http_response_code(403);
            $_SESSION['error_msg'] = 'เฉพาะผู้ดูแลระบบเท่านั้นที่ตรวจสุขภาพระบบได้';
            header('Location: index.php?c=data43&a=index');
            exit;
        }

        $db=(new Database())->getConnection();
        $health=(new Data43SystemHealthService($db))->inspect();

        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/data43/health.php';
        echo "</main></div></body></html>";
    }

    public function registry(): void
    {
        $this->requireAccess();

        $db=(new Database())->getConnection();
        $service=new Data43RegistryService($db);
        $schema_ready=$service->schemaReady();
        $is_admin=in_array($this->role(),self::ADMIN_ROLES,true);
        $data43_role=$this->data43Role();
        $selected_hospital_id=$this->selectedHospitalId();
        $csrf_token=$this->csrfToken();
        $schemas=Data43FormRegistry::schemas();
        $file_code=strtoupper(trim((string)($_GET['file'] ?? 'PERSON')));
        if(!isset($schemas[$file_code])) $file_code='PERSON';

        $hospitals=[];
        if($is_admin){
            $stmt=$db->query("SELECT id,hospital_code,name FROM hospitals WHERE is_active=1 AND deleted_at IS NULL ORDER BY name");
            $hospitals=$stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $records=($schema_ready && $selected_hospital_id)
            ? $service->listFile((int)$selected_hospital_id,$file_code,100)
            : [];
        $overview=($schema_ready && $selected_hospital_id)
            ? $service->overview((int)$selected_hospital_id)
            : ['people'=>0,'typearea'=>[],'age'=>[],'chronic_people'=>0,'dm'=>0,'ht'=>0,'missing_home'=>0];

        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/data43/registry.php';
        echo "</main></div></body></html>";
    }

    public function registry_save(): void
    {
        $this->requireAccess();
        header('Content-Type: application/json; charset=utf-8');
        try{
            $this->verifyCsrf();
            $db=(new Database())->getConnection();
            $service=new Data43RegistryService($db);
            if(!$service->schemaReady()) throw new RuntimeException('กรุณารัน migration data43_registry ก่อน');

            $hospitalId=$this->resolveUploadHospitalId();
            $fileCode=strtoupper(trim((string)($_POST['file_code'] ?? '')));
            $recordId=filter_input(INPUT_POST,'record_id',FILTER_VALIDATE_INT) ?: null;
            $payload=json_decode((string)($_POST['payload'] ?? '{}'),true);
            if(!is_array($payload)) throw new RuntimeException('ข้อมูลแบบฟอร์มไม่ถูกต้อง');

            $result=$service->save($hospitalId,(int)$_SESSION['user']['id'],$fileCode,$payload,$recordId);
            LogsController::addLog($db,(int)$_SESSION['user']['id'],
                $result['action']==='CREATE'?LogsController::ACTION_CREATE:LogsController::ACTION_UPDATE,
                "Data43 {$result['action']} {$fileCode} record #{$result['id']} hospital #{$hospitalId}");
            echo json_encode(['ok'=>true,'record'=>$result],JSON_UNESCAPED_UNICODE);
        }catch(InvalidArgumentException $e){
            http_response_code(422);
            $errors=json_decode($e->getMessage(),true);
            echo json_encode(['ok'=>false,'message'=>'กรุณาตรวจสอบข้อมูล','errors'=>is_array($errors)?$errors:[]],JSON_UNESCAPED_UNICODE);
        }catch(Throwable $e){
            http_response_code(400);
            echo json_encode(['ok'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    public function registry_search(): void
    {
        $this->requireAccess();
        header('Content-Type: application/json; charset=utf-8');
        try{
            $hospitalId=$this->selectedHospitalId();
            if(!$hospitalId) throw new RuntimeException('กรุณาเลือกหน่วยบริการ');
            $type=strtoupper(trim((string)($_GET['type'] ?? 'PERSON')));
            $q=trim((string)($_GET['q'] ?? ''));
            $service=new Data43RegistryService((new Database())->getConnection());
            echo json_encode(['ok'=>true,'items'=>$service->search((int)$hospitalId,$type,$q)],JSON_UNESCAPED_UNICODE);
        }catch(Throwable $e){
            http_response_code(400);
            echo json_encode(['ok'=>false,'message'=>$e->getMessage(),'items'=>[]],JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    public function registry_record(): void
    {
        $this->requireAccess();
        header('Content-Type: application/json; charset=utf-8');
        try{
            $hospitalId=$this->selectedHospitalId();
            $id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
            if(!$hospitalId||!$id) throw new RuntimeException('ไม่พบข้อมูล');
            $service=new Data43RegistryService((new Database())->getConnection());
            $record=$service->getRecord((int)$hospitalId,(int)$id);
            if(!$record) throw new RuntimeException('ไม่พบข้อมูล');
            echo json_encode(['ok'=>true,'record'=>$record],JSON_UNESCAPED_UNICODE);
        }catch(Throwable $e){
            http_response_code(404);
            echo json_encode(['ok'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    public function registry_profile(): void
    {
        $this->requireAccess();
        $hospitalId=$this->selectedHospitalId();
        $pid=trim((string)($_GET['pid'] ?? ''));
        if(!$hospitalId||$pid===''){
            $_SESSION['error_msg']='ไม่พบ PID ที่ต้องการ';
            header('Location: index.php?c=data43&a=registry'); exit;
        }
        $service=new Data43RegistryService((new Database())->getConnection());
        $profile=$service->personProfile((int)$hospitalId,$pid);
        $csrf_token=$this->csrfToken();
        $selected_hospital_id=$hospitalId;
        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/data43/person_profile.php';
        echo "</main></div></body></html>";
    }

    public function registry_delete(): void
    {
        $this->requireAccess();
        if($this->data43Role()!=='ADMIN'){
            http_response_code(403);
            $_SESSION['error_msg']='เฉพาะผู้ดูแลระบบเท่านั้นที่ลบข้อมูลได้';
            header('Location: index.php?c=data43&a=registry');
            exit;
        }
        if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}
        try{
            $this->verifyCsrf();
            $hospitalId=$this->resolveUploadHospitalId();
            $id=filter_input(INPUT_POST,'record_id',FILTER_VALIDATE_INT);
            if(!$id) throw new RuntimeException('ไม่พบรายการ');
            $db=(new Database())->getConnection();
            $service=new Data43RegistryService($db);
            $service->delete($hospitalId,(int)$_SESSION['user']['id'],(int)$id);
            LogsController::addLog($db,(int)$_SESSION['user']['id'],LogsController::ACTION_DELETE,"Data43 delete record #{$id} hospital #{$hospitalId}");
            $_SESSION['success_msg']='ลบรายการเรียบร้อย';
        }catch(Throwable $e){$_SESSION['error_msg']=$e->getMessage();}
        $query=in_array($this->role(),self::ADMIN_ROLES,true)&&!empty($_POST['hospital_id'])?'&hospital_id='.(int)$_POST['hospital_id']:'';
        header('Location: index.php?c=data43&a=registry'.$query);
        exit;
    }

    public function registry_export(): void
    {
        $this->requireAccess();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            $_SESSION['error_msg'] = 'การส่งออกข้อมูลต้องยืนยันผ่านแบบฟอร์มที่ปลอดภัย';
            header('Location: index.php?c=data43&a=registry');
            exit;
        }

        try {
            $this->verifyCsrf();
            $hospitalId = $this->resolveUploadHospitalId();
            $fileCode = strtoupper(trim((string)($_POST['file'] ?? 'PERSON')));
            $format = strtolower(trim((string)($_POST['format'] ?? 'txt')));
            if (!in_array($format, ['txt','csv','zip'], true)) $format = 'txt';

            $workDir = Data43StorageService::createJobDirectory('export');

            try {
                $db = (new Database())->getConnection();
                $service = new Data43RegistryService($db);

                if ($format === 'zip') {
                    $inner = strtolower(trim((string)($_POST['inner'] ?? 'txt')));
                    if (!in_array($inner, ['txt','csv'], true)) $inner = 'txt';
                    $path = $service->exportZip(
                        $hospitalId,
                        (int)$_SESSION['user']['id'],
                        Data43FormRegistry::codes(),
                        $inner,
                        $workDir
                    );
                    header('Content-Type: application/zip');
                    header('Content-Disposition: attachment; filename="DATA43_' . date('Ymd_His') . '.zip"');
                } else {
                    if (!Data43FormRegistry::get($fileCode)) {
                        throw new RuntimeException('ไม่รู้จักแฟ้มที่ต้องการส่งออก');
                    }
                    $path = $service->exportFile(
                        $hospitalId,
                        (int)$_SESSION['user']['id'],
                        $fileCode,
                        $format,
                        $workDir
                    );
                    header('Content-Type: ' . ($format === 'csv' ? 'text/csv' : 'text/plain') . '; charset=utf-8');
                    header('Content-Disposition: attachment; filename="' . $fileCode . '.' . $format . '"');
                }

                header('X-Content-Type-Options: nosniff');
                header('Cache-Control: no-store, private');
                header('Content-Length: ' . filesize($path));
                readfile($path);
            } finally {
                Data43StorageService::recursiveDelete($workDir);
            }
        } catch (Throwable $e) {
            error_log('Data43 export error: ' . $e->getMessage());
            $_SESSION['error_msg'] = $e->getMessage();
            $query = '';
            if (in_array($this->role(), self::ADMIN_ROLES, true) && !empty($_POST['hospital_id'])) {
                $query = '&hospital_id=' . (int)$_POST['hospital_id'];
            }
            header('Location: index.php?c=data43&a=registry' . $query);
        }
        exit;
    }

    public function spatial(): void
    {
        $this->requireAccess();

        $db = (new Database())->getConnection();
        $model = new Data43SubmissionModel($db);

        $schema_ready = $model->schemaReady();
        $spatial_schema_ready = $model->spatialSchemaReady();
        $is_admin = in_array($this->role(), self::ADMIN_ROLES, true);
        $selected_hospital_id = $this->selectedHospitalId();

        $report_month = trim((string)($_GET['month'] ?? date('Y-m')));
        $area_level = strtoupper(trim((string)($_GET['level'] ?? 'CHANGWAT')));
        $metric_code = strtoupper(trim((string)($_GET['metric'] ?? 'DM')));
        $display_mode = strtolower(trim((string)($_GET['mode'] ?? 'rate')));
        $ampur_code = preg_replace('/[^0-9]/', '', (string)($_GET['ampur'] ?? ''));
        $tambon_code = preg_replace('/[^0-9]/', '', (string)($_GET['tambon'] ?? ''));

        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $report_month)) {
            $report_month = date('Y-m');
        }

        if (!in_array($area_level, ['CHANGWAT','AMPUR','TAMBON','VILLAGE'], true)) {
            $area_level = 'CHANGWAT';
        }

        $allowedMetrics = Data43MetricRegistry::allowedCodes();
        if (!in_array($metric_code, $allowedMetrics, true)) {
            $metric_code = 'DM';
        }
        $metric_definition = Data43MetricRegistry::get($metric_code) ?? Data43MetricRegistry::get('DM');

        if (!Data43MetricRegistry::canUseRateMode($metric_code)) {
            $display_mode = 'count';
        }

        if (!in_array($display_mode, ['count','rate'], true)) {
            $display_mode = 'rate';
        }

        $ampur_code = strlen($ampur_code) === 2 ? $ampur_code : null;
        $tambon_code = strlen($tambon_code) === 2 ? $tambon_code : null;

        if (in_array($area_level, ['CHANGWAT','AMPUR'], true)) {
            $ampur_code = null;
            $tambon_code = null;
        } elseif ($area_level === 'TAMBON') {
            $tambon_code = null;
        }

        $hospitals = [];
        if ($is_admin) {
            $stmt = $db->query("
                SELECT id, hospital_code, name
                FROM hospitals
                WHERE is_active = 1
                  AND deleted_at IS NULL
                  AND COALESCE(hospital_code, '') <> '0'
                ORDER BY name ASC
            ");
            $hospitals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $scopeHospitalId = $selected_hospital_id;
        $metric_options = $spatial_schema_ready
            ? $model->getSpatialMetricOptions($report_month, $scopeHospitalId)
            : [];

        $spatial_rows = $spatial_schema_ready
            ? $model->getSpatialSummary(
                $report_month,
                $area_level,
                $metric_code,
                $scopeHospitalId,
                $ampur_code,
                $tambon_code
            )
            : [];

        $hospital_coverage = $spatial_schema_ready
            ? $model->getSpatialCoverageByHospital(
                $report_month,
                $area_level,
                $metric_code,
                $scopeHospitalId
            )
            : [];

        $spatial_summary = [
            'areas' => count($spatial_rows),
            'records' => 0,
            'population' => 0,
            'rate_per_1000' => null,
            'hospitals' => 0,
            'geocoded_areas' => 0,
            'max_value' => 0.0,
        ];

        $hospitalSet = [];
        foreach ($spatial_rows as $row) {
            $value = (int)($row['metric_value'] ?? 0);
            $population = (int)($row['population_value'] ?? 0);
            $displayValue = $display_mode === 'rate'
                ? (float)($row['display_value'] ?? 0)
                : (float)$value;

            $spatial_summary['records'] += $value;
            $spatial_summary['population'] += $population;
            $spatial_summary['max_value'] = max($spatial_summary['max_value'], $displayValue);

            if (!empty($row['centroid_lat']) && !empty($row['centroid_lng'])) {
                $spatial_summary['geocoded_areas']++;
            }
        }

        $summaryDenominator = null;
        if (($metric_definition['denominator'] ?? null) === 'POPULATION') {
            $summaryDenominator = (int)$spatial_summary['population'];
        } else {
            $summaryDenominator = 0;
            foreach ($spatial_rows as $row) {
                $summaryDenominator += (int)($row['denominator_value'] ?? 0);
            }
        }
        $spatial_summary['denominator'] = $summaryDenominator;
        $spatial_summary['display_value'] = Data43MetricRegistry::calculate(
            $metric_code,
            (int)$spatial_summary['records'],
            $metric_definition['denominator'] === null ? null : $summaryDenominator
        );
        $spatial_summary['display_unit'] = (string)($metric_definition['unit'] ?? '');

        foreach ($hospital_coverage as $row) {
            $hospitalSet[(int)$row['hospital_id']] = true;
        }
        $spatial_summary['hospitals'] = count($hospitalSet);

        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/data43/spatial.php';
        echo "</main></div></body></html>";
    }

    public function delete_submission(): void
    {
        $this->requireAccess();

        if (!in_array($this->role(), self::ADMIN_ROLES, true)) {
            http_response_code(403);
            $_SESSION['error_msg'] = 'เฉพาะ ADMIN / SUPERADMIN เท่านั้นที่ลบชุดข้อมูลได้';
            header('Location: index.php?c=data43&a=index');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?c=data43&a=index');
            exit;
        }

        $redirect = 'index.php?c=data43&a=index';

        try {
            $this->verifyCsrf();

            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) {
                throw new RuntimeException('ไม่พบรหัสชุดข้อมูลที่ต้องการลบ');
            }

            $db = (new Database())->getConnection();
            $model = new Data43SubmissionModel($db);
            $submission = $model->getSubmission((int)$id);

            if (!$submission) {
                throw new RuntimeException('ไม่พบชุดข้อมูล หรืออาจถูกลบไปแล้ว');
            }

            $hospitalId = (int)$submission['hospital_id'];
            if ($hospitalId > 0) {
                $redirect .= '&hospital_id=' . $hospitalId;
            }

            if ($model->deleteSubmission((int)$id)) {
                LogsController::addLog(
                    $db,
                    $_SESSION['user']['id'],
                    LogsController::ACTION_DELETE,
                    'ลบชุดข้อมูล 43 แฟ้ม Submission #'
                    . (int)$id
                    . ', Hospital #' . $hospitalId
                    . ', รอบ ' . (string)$submission['report_month']
                    . ', สถานะเดิม ' . (string)$submission['status']
                );

                $_SESSION['success_msg'] = 'ลบชุดข้อมูล Submission #' . (int)$id . ' เรียบร้อยแล้ว';
            } else {
                throw new RuntimeException('ไม่สามารถลบชุดข้อมูลได้');
            }
        } catch (Throwable $e) {
            error_log('Data43 delete error: ' . $e->getMessage());
            $_SESSION['error_msg'] = $e->getMessage();
        }

        header('Location: ' . $redirect);
        exit;
    }

    public function detail(): void
    {
        $this->requireAccess();

        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            http_response_code(400);
            exit('คำขอไม่ถูกต้อง');
        }

        $db = (new Database())->getConnection();
        $model = new Data43SubmissionModel($db);
        $submission = $model->getSubmission($id);

        if (!$submission) {
            http_response_code(404);
            exit('ไม่พบรายการนำส่ง');
        }

        if (!in_array($this->role(), self::ADMIN_ROLES, true)) {
            $sessionHospitalId = (int)($_SESSION['user']['hospital_id'] ?? 0);
            if ((int)$submission['hospital_id'] !== $sessionHospitalId) {
                http_response_code(403);
                exit('คุณไม่มีสิทธิ์ดูข้อมูลของหน่วยบริการอื่น');
            }
        }

        $files = $model->getFiles($id);
        $quality_summary = $model->getQualitySummary($id);
        $quality_issues = $model->getQualityIssues($id);
        $is_admin = in_array($this->role(), self::ADMIN_ROLES, true);
        $csrf_token = $this->csrfToken();

        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/data43/detail.php';
        echo "</main></div></body></html>";
    }
}
?>