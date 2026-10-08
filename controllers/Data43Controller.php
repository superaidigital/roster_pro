<?php
require_once 'config/database.php';
require_once 'models/Data43SubmissionModel.php';
require_once 'services/Data43ImportService.php';
require_once 'controllers/LogsController.php';

class Data43Controller
{
    private const ALLOWED_ROLES = ['SUPERADMIN','ADMIN','DIRECTOR','SCHEDULER','STAFF'];
    private const ADMIN_ROLES = ['SUPERADMIN','ADMIN'];

    private function role(): string
    {
        return strtoupper(trim((string)($_SESSION['user']['role'] ?? '')));
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
                SELECT COUNT(*)
                FROM hospitals
                WHERE id = ?
                  AND is_active = 1
                  AND deleted_at IS NULL
            ");
            $stmt->execute([$hospitalId]);
            if ((int)$stmt->fetchColumn() !== 1) {
                throw new RuntimeException('ไม่พบ รพ.สต. หรือหน่วยบริการถูกปิดใช้งาน');
            }

            $upload = $_FILES['zip_file'] ?? null;
            if (!is_array($upload)) {
                throw new RuntimeException('กรุณาเลือกไฟล์ ZIP');
            }

            $workRoot = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'data43_temp';
            if (!is_dir($workRoot) && !mkdir($workRoot, 0750, true) && !is_dir($workRoot)) {
                throw new RuntimeException('ไม่สามารถสร้างพื้นที่ประมวลผลชั่วคราวได้');
            }

            $workDir = $workRoot . DIRECTORY_SEPARATOR
                . 'job_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8));

            $service = new Data43ImportService();
            $submissionId = null;

            try {
                $inspection = $service->inspectUploadedZip($upload, $workDir);

                $originalFilename = basename((string)($upload['name'] ?? 'submission.zip'));
                $originalFilename = preg_replace('/[^A-Za-z0-9._\-ก-๙ ]/u', '_', $originalFilename) ?: 'submission.zip';

                $clientIp = (string)($_SERVER['REMOTE_ADDR'] ?? '');
                $ipHash = $clientIp !== ''
                    ? hash_hmac('sha256', $clientIp, session_id())
                    : null;

                $submissionId = $model->createSubmission([
                    'hospital_id' => $hospitalId,
                    'report_month' => $reportMonth,
                    'original_filename' => mb_substr($originalFilename, 0, 180, 'UTF-8'),
                    'archive_sha256' => $inspection['archive_sha256'],
                    'purpose_code' => 'PUBLIC_HEALTH_REPORTING',
                    'expected_files' => 43,
                    'uploaded_by' => (int)$_SESSION['user']['id'],
                    'client_ip_hash' => $ipHash,
                ]);

                $uniqueCodes = [];
                $totalRows = 0;
                $validFiles = 0;

                foreach ($inspection['files'] as $file) {
                    $model->addFile($submissionId, $file);

                    if (($file['status'] ?? '') === 'VALID') {
                        $validFiles++;
                        $uniqueCodes[(string)$file['file_code']] = true;
                        if ($file['row_count'] !== null) {
                            $totalRows += (int)$file['row_count'];
                        }
                    }
                }

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

                $detected = count($uniqueCodes);
                $status = $detected >= 43 ? 'COMPLETE' : 'INCOMPLETE';
                $errorSummary = $status === 'INCOMPLETE'
                    ? "ตรวจพบ {$detected} ชุดข้อมูล จากที่คาดหวัง 43 ชุด"
                    : null;

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
                    "นำส่งข้อมูล 43 แฟ้ม Submission #{$submissionId}, Hospital #{$hospitalId}, รอบ {$reportMonth}, ตรวจพบ {$detected} ชุด"
                );

                $_SESSION['success_msg'] = $status === 'COMPLETE'
                    ? "นำส่งข้อมูลสำเร็จ ตรวจพบครบ {$detected} ชุดข้อมูล"
                    : "รับไฟล์เรียบร้อย แต่ตรวจพบ {$detected} จาก 43 ชุด กรุณาตรวจสอบรายละเอียด";

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
                Data43ImportService::recursiveDelete($workDir);
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
        $area_level = strtoupper(trim((string)($_GET['level'] ?? 'TAMBON')));
        $metric_code = strtoupper(trim((string)($_GET['metric'] ?? '')));

        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $report_month)) {
            $report_month = date('Y-m');
        }

        if (!in_array($area_level, ['AMPUR','TAMBON','VILLAGE'], true)) {
            $area_level = 'TAMBON';
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

        $validMetrics = array_values(array_unique(array_map(
            static fn(array $row): string => (string)$row['metric_code'],
            $metric_options
        )));

        if ($metric_code !== '' && !in_array($metric_code, $validMetrics, true)) {
            $metric_code = '';
        }

        $spatial_rows = $spatial_schema_ready
            ? $model->getSpatialSummary(
                $report_month,
                $area_level,
                $metric_code !== '' ? $metric_code : null,
                $scopeHospitalId
            )
            : [];

        $hospital_coverage = $spatial_schema_ready
            ? $model->getSpatialCoverageByHospital(
                $report_month,
                $area_level,
                $scopeHospitalId
            )
            : [];

        $spatial_summary = [
            'areas' => count($spatial_rows),
            'records' => 0,
            'hospitals' => 0,
            'geocoded_areas' => 0,
            'max_value' => 0,
        ];

        $hospitalSet = [];
        foreach ($spatial_rows as $row) {
            $value = (int)($row['metric_value'] ?? 0);
            $spatial_summary['records'] += $value;
            $spatial_summary['max_value'] = max($spatial_summary['max_value'], $value);
            if (!empty($row['centroid_lat']) && !empty($row['centroid_lng'])) {
                $spatial_summary['geocoded_areas']++;
            }
        }

        foreach ($hospital_coverage as $row) {
            $hospitalSet[(int)$row['hospital_id']] = true;
        }
        $spatial_summary['hospitals'] = count($hospitalSet);

        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/data43/spatial.php';
        echo "</main></div></body></html>";
    }


    /** Authenticated, aggregate-only JSON endpoint. */
    public function spatial_api(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, no-store');
        try {
            if (!isset($_SESSION['user'])) throw new RuntimeException('กรุณาเข้าสู่ระบบ',401);
            if (!in_array($this->role(), self::ALLOWED_ROLES,true)) throw new RuntimeException('ไม่มีสิทธิ์',403);
            require_once 'services/Data43SpatialMapService.php';
            $db=(new Database())->getConnection();
            $service=new Data43SpatialMapService($db);
            $type=(string)($_GET['type'] ?? 'map');
            $isAdmin=in_array($this->role(),self::ADMIN_ROLES,true);
            $hospitalId=$isAdmin ? filter_input(INPUT_GET,'hospital_id',FILTER_VALIDATE_INT) : (int)($_SESSION['user']['hospital_id'] ?? 0);
            if (!$isAdmin && $hospitalId<=0) throw new RuntimeException('บัญชีไม่ได้ผูกกับ รพ.สต.',403);
            $hospitalId=$hospitalId ?: null;
            if ($type==='search') {
                $result=['items'=>$service->searchAreas((string)($_GET['q']??''))];
            } elseif ($type==='facilities') {
                $level=strtoupper((string)($_GET['level']??'TAMBON'));
                $code=(string)($_GET['code']??'');
                $result=['items'=>$service->facilities($level,$code)];
            } elseif ($type==='options') {
                $month=$service->validateMonth((string)($_GET['month']??date('Y-m')));
                $result=['items'=>$service->options($month,$hospitalId)];
            } elseif ($type==='map') {
                $month=$service->validateMonth((string)($_GET['month']??date('Y-m')));
                $level=strtoupper((string)($_GET['level']??'PROVINCE'));
                $parent=(string)($_GET['parent']??'');
                $metric=strtoupper((string)($_GET['metric']??''));
                $source=strtoupper((string)($_GET['source']??''));
                $options=$service->options($month,$hospitalId);
                if ($metric==='' || $source==='') {
                    $metric=(string)($options[0]['metric_code']??'');
                    $source=(string)($options[0]['source_file_code']??'');
                }
                $areas=$service->boundaries($level,$parent?:null);
                $values=[];
                if ($metric!=='' && $source!=='') {
                    $service->assertIndicator($options,$metric,$source);
                    $values=$service->aggregate($month,$level,$metric,$source,$hospitalId,$parent?:null);
                }
                // Publish only a coarse band. Never return suppressed numeric values.
                $features=[];
                foreach ($areas['features']??[] as $feature) {
                    $code=(string)$feature['properties']['code'];
                    $agg=$values[$code]??null;
                    $feature['properties']['band']=$agg['band']??'none';
                    $feature['properties']['count']=$agg['count']??null;
                    $feature['properties']['suppressed']=$agg['suppressed']??false;
                    $feature['properties']['facility_count']=$agg['facility_count']??0;
                    $features[]=$feature;
                }
                $result=[
                    'type'=>'FeatureCollection','features'=>$features,
                    'meta'=>['level'=>$level,'month'=>$month,'metric'=>$metric,'source'=>$source,
                        'boundary_ready'=>$service->boundaryReady(),
                        'suppression_threshold'=>Data43SpatialMapService::MIN_CELL,
                        'note'=>'จำนวนระเบียนของแฟ้มที่เลือก ไม่ใช่จำนวนผู้ป่วยไม่ซ้ำหรืออัตราป่วย']
                ];
            } else {
                throw new InvalidArgumentException('ชนิดคำขอไม่ถูกต้อง');
            }
            echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            $status=($e instanceof InvalidArgumentException)?422:((int)$e->getCode()===403?403:500);
            http_response_code($status);
            echo json_encode(['error'=>$status===500?'ไม่สามารถโหลดข้อมูลได้':$e->getMessage()],JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    /** Admin-managed official GeoJSON geometry master and hospital service area mapping. */
    public function spatial_admin(): void
    {
        $this->requireAccess();
        if (!in_array($this->role(),self::ADMIN_ROLES,true)) {
            http_response_code(403); exit('ไม่มีสิทธิ์');
        }
        if (($_SERVER['REQUEST_METHOD']??'GET')!=='POST') {
            header('Location: index.php?c=data43&a=spatial'); exit;
        }
        try {
            $this->verifyCsrf();
            require_once 'services/Data43SpatialMapService.php';
            $db=(new Database())->getConnection();
            $service=new Data43SpatialMapService($db);
            $action=(string)($_POST['operation']??'');
            if ($action==='upload_geojson') {
                $level=strtoupper((string)($_POST['level']??''));
                $count=$service->importGeoJson($_FILES['geojson']??[],$level);
                $_SESSION['success_msg']="นำเข้าขอบเขตพื้นที่สำเร็จ {$count} พื้นที่";
            } elseif ($action==='assign_area') {
                $id=filter_input(INPUT_POST,'hospital_id',FILTER_VALIDATE_INT);
                $code=(string)($_POST['tambon_code']??'');
                if (!$id) throw new InvalidArgumentException('กรุณาระบุหน่วยบริการ');
                $service->assignArea($id,$code,(string)($_POST['assign']??'1')==='1');
                $_SESSION['success_msg']='ปรับปรุงพื้นที่รับผิดชอบหน่วยบริการแล้ว';
            } else throw new InvalidArgumentException('คำสั่งไม่ถูกต้อง');
            LogsController::addLog($db,$_SESSION['user']['id'],LogsController::ACTION_CREATE,'43-file spatial admin: '.$action);
        } catch (Throwable $e) {
            error_log('Spatial admin: '.$e->getMessage());
            $_SESSION['error_msg']=$e->getMessage();
        }
        header('Location: index.php?c=data43&a=spatial');exit;
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
        $is_admin = in_array($this->role(), self::ADMIN_ROLES, true);
        $csrf_token = $this->csrfToken();

        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/data43/detail.php';
        echo "</main></div></body></html>";
    }
}
?>