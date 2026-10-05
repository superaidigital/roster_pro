<?php
$summary = $summary ?? [];
$queue = $summary['queue'] ?? [];
$events = $summary['events'] ?? [];
$migration = $summary['migration'] ?? [];
$overall = strtoupper((string)($summary['overall_status'] ?? 'UNKNOWN'));
$statusClass = $overall === 'OK' ? 'success' : ($overall === 'DEGRADED' ? 'warning' : 'danger');
?>
<style>
.obs-card{border:1px solid #e2e8f0;border-radius:1.15rem;background:#fff;box-shadow:0 8px 28px rgba(15,23,42,.04)}
.metric{font-size:clamp(1.55rem,4vw,2.25rem);font-weight:850;letter-spacing:-.04em}
.metric-label{font-size:.78rem;color:#64748b;font-weight:700;text-transform:uppercase;letter-spacing:.04em}
.event-message,.job-error{overflow-wrap:anywhere;white-space:normal}
.code-mini{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:.75rem}
.health-dot{width:.7rem;height:.7rem;border-radius:50%;display:inline-block}
@media(max-width:767.98px){.table-observe{min-width:850px}.obs-card .card-body{padding:1rem}}
</style>

<div class="container-fluid px-3 px-md-4 py-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <div class="text-primary fw-bold small mb-1">PRODUCTION OBSERVABILITY</div>
            <h2 class="fw-bolder mb-1">System Health & Reliability</h2>
            <p class="text-muted mb-0">ติดตาม Error, Queue, Migration, Storage และงานที่ต้อง Retry จากจุดเดียว</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="index.php?c=settings&a=system" class="btn btn-light border rounded-pill px-3">
                <i class="bi bi-gear me-1"></i> ตั้งค่าระบบ
            </a>
            <form method="post" action="index.php?c=observability&a=capture_health">
                <?= security_csrf_input() ?>
                <button class="btn btn-primary rounded-pill px-3" type="submit">
                    <i class="bi bi-activity me-1"></i> Capture Health
                </button>
            </form>
        </div>
    </div>

    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success border-0 rounded-4"><?= htmlspecialchars((string)$_SESSION['success_msg'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-danger border-0 rounded-4"><?= htmlspecialchars((string)$_SESSION['error_msg'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php unset($_SESSION['error_msg']); ?>
    <?php endif; ?>

    <div class="obs-card p-3 p-md-4 mb-4 border-start border-4 border-<?= $statusClass ?>">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
            <div>
                <div class="metric-label">Overall Status</div>
                <div class="fs-3 fw-bolder text-<?= $statusClass ?>">
                    <span class="health-dot bg-<?= $statusClass ?> me-2"></span><?= htmlspecialchars($overall, ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>
            <div class="small text-muted">
                DB: <b><?= htmlspecialchars((string)($summary['db_status'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></b>
                <span class="mx-2">·</span>
                Disk Free: <b><?= $summary['disk_free_mb'] === null ? '-' : number_format((int)$summary['disk_free_mb']) . ' MB' ?></b>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <?php
        $cards = [
            ['Open Errors', (int)($events['open_total'] ?? 0), 'bi-bug', 'danger'],
            ['Critical', (int)($events['critical_open'] ?? 0), 'bi-exclamation-octagon', 'danger'],
            ['Queue Pending', (int)($queue['pending'] ?? 0), 'bi-hourglass-split', 'primary'],
            ['Queue Failed', (int)($queue['failed'] ?? 0), 'bi-x-octagon', 'danger'],
            ['Queue Delayed', (int)($queue['delayed'] ?? 0), 'bi-clock-history', 'warning'],
            ['Migration Pending', (int)($migration['pending'] ?? 0), 'bi-database-gear', 'info'],
        ];
        foreach ($cards as [$label,$value,$icon,$color]):
        ?>
        <div class="col-6 col-lg-2">
            <div class="obs-card h-100 p-3">
                <i class="bi <?= $icon ?> text-<?= $color ?> fs-4"></i>
                <div class="metric mt-2"><?= number_format($value) ?></div>
                <div class="metric-label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="obs-card mb-4 overflow-hidden">
        <div class="p-3 p-md-4 border-bottom d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bolder mb-1">Open Error Events</h5>
                <div class="small text-muted">เหตุการณ์ซ้ำจะรวมด้วย fingerprint และนับ occurrence</div>
            </div>
            <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill"><?= count($openEvents ?? []) ?> รายการ</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-observe">
                <thead class="table-light">
                    <tr><th>Severity</th><th>เหตุการณ์</th><th>Route / Request</th><th>จำนวน</th><th>ล่าสุด</th><th></th></tr>
                </thead>
                <tbody>
                <?php if (empty($openEvents)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-5">ไม่มี Open Error Event</td></tr>
                <?php else: foreach ($openEvents as $event): ?>
                    <?php $sev = strtoupper((string)$event['severity']); $sevClass = $sev === 'CRITICAL' || $sev === 'ERROR' ? 'danger' : ($sev === 'WARNING' ? 'warning' : 'secondary'); ?>
                    <tr>
                        <td><span class="badge text-bg-<?= $sevClass ?>"><?= htmlspecialchars($sev, ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td style="max-width:430px">
                            <div class="fw-bold event-message"><?= htmlspecialchars((string)$event['message'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="small text-muted code-mini"><?= htmlspecialchars((string)($event['exception_class'] ?? $event['category']), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string)($event['source_file'] ?? ''), ENT_QUOTES, 'UTF-8') ?>:<?= (int)($event['source_line'] ?? 0) ?></div>
                        </td>
                        <td class="small">
                            <div><?= htmlspecialchars((string)($event['route'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="code-mini text-muted"><?= htmlspecialchars((string)($event['request_id'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div>
                        </td>
                        <td class="fw-bold"><?= number_format((int)$event['occurrence_count']) ?></td>
                        <td class="small"><?= htmlspecialchars((string)$event['last_seen_at'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <form method="post" action="index.php?c=observability&a=resolve_event">
                                <?= security_csrf_input() ?>
                                <input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">
                                <button class="btn btn-sm btn-outline-success rounded-pill" type="submit">Resolve</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="obs-card mb-4 overflow-hidden">
        <div class="p-3 p-md-4 border-bottom">
            <h5 class="fw-bolder mb-1">Failed Job Center</h5>
            <div class="small text-muted">งานที่ retry อัตโนมัติครบจำนวนครั้งแล้วจะมารอการตรวจสอบที่นี่</div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-observe">
                <thead class="table-light"><tr><th>ID</th><th>Job Type</th><th>Attempts</th><th>Last Error</th><th>Updated</th><th></th></tr></thead>
                <tbody>
                <?php if (empty($failedJobs)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-5">ไม่มี Failed Job</td></tr>
                <?php else: foreach ($failedJobs as $job): ?>
                    <tr>
                        <td class="fw-bold">#<?= (int)$job['id'] ?></td>
                        <td><span class="badge bg-dark"><?= htmlspecialchars((string)$job['job_type'], ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td><?= (int)$job['attempts'] ?>/<?= (int)$job['max_attempts'] ?></td>
                        <td style="max-width:480px"><div class="job-error small"><?= nl2br(htmlspecialchars((string)($job['last_error'] ?? '-'), ENT_QUOTES, 'UTF-8')) ?></div></td>
                        <td class="small"><?= htmlspecialchars((string)$job['updated_at'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <form method="post" action="index.php?c=observability&a=retry_job">
                                <?= security_csrf_input() ?>
                                <input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>">
                                <button class="btn btn-sm btn-outline-primary rounded-pill" type="submit"><i class="bi bi-arrow-repeat me-1"></i>Retry</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-6">
            <div class="obs-card h-100 overflow-hidden">
                <div class="p-3 p-md-4 border-bottom"><h5 class="fw-bolder mb-0">Recent Queue Activity</h5></div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th>ID</th><th>Type</th><th>Status</th><th>Attempts</th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice($recentJobs ?? [],0,15) as $job): ?>
                            <tr>
                                <td>#<?= (int)$job['id'] ?></td>
                                <td class="small"><?= htmlspecialchars((string)$job['job_type'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars((string)$job['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><?= (int)$job['attempts'] ?>/<?= (int)$job['max_attempts'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recentJobs)): ?><tr><td colspan="4" class="text-muted text-center py-4">ยังไม่มี Queue Activity</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="obs-card h-100 overflow-hidden">
                <div class="p-3 p-md-4 border-bottom"><h5 class="fw-bolder mb-0">Health Snapshot History</h5></div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th>Status</th><th>Queue</th><th>Errors</th><th>Captured</th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice($snapshots ?? [],0,15) as $snap): ?>
                            <?php $sc = $snap['overall_status'] === 'OK' ? 'success' : ($snap['overall_status'] === 'DEGRADED' ? 'warning' : 'danger'); ?>
                            <tr>
                                <td><span class="badge text-bg-<?= $sc ?>"><?= htmlspecialchars((string)$snap['overall_status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><?= (int)$snap['queue_pending'] ?> / failed <?= (int)$snap['queue_failed'] ?></td>
                                <td><?= (int)$snap['open_errors_24h'] ?></td>
                                <td class="small"><?= htmlspecialchars((string)$snap['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($snapshots)): ?><tr><td colspan="4" class="text-muted text-center py-4">ยังไม่มี Health Snapshot</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
