<?php require_once __DIR__ . '/../../../config/constants.php'; ?>
<?php require_once __DIR__ . '/../../../app/middleware/Auth.php'; ?>
<?php $pageTitle = 'Painel'; ?>
<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="icon-box bg-primary text-white rounded p-3"><i class="bi bi-cpu fs-4"></i></div>
                <div>
                    <div class="fs-4 fw-bold"><?= (int) $deviceCount ?></div>
                    <div class="text-muted small">Dispositivos ativos</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="icon-box bg-success text-white rounded p-3"><i class="bi bi-bar-chart-line fs-4"></i></div>
                <div>
                    <div class="fs-4 fw-bold"><?= (int) $todayCount ?></div>
                    <div class="text-muted small">Leituras hoje</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="icon-box bg-danger text-white rounded p-3"><i class="bi bi-bell fs-4"></i></div>
                <div>
                    <div class="fs-4 fw-bold"><?= (int) $openAlerts ?></div>
                    <div class="text-muted small">Alertas abertos</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex align-items-center justify-content-between mb-3">
    <h5 class="mb-0">Dispositivos</h5>
</div>

<div class="row g-3 mb-4" id="deviceCards">
<?php foreach ($devices as $device): ?>
    <?php
    $lastSeen = $device['last_seen_at'] ?? $device['last_reading'] ?? null;
    $lastSeenTs = $lastSeen ? strtotime($lastSeen) : false;
    $isRecentlySeen = $lastSeenTs !== false && (time() - $lastSeenTs) <= (DEVICE_ONLINE_WINDOW_MINUTES * 60);
    $isOnline = !empty($device['active']) && $isRecentlySeen;
    $hasRecentTemperature = $device['last_temp'] !== null && $isRecentlySeen;
    $isTempAlert = $hasRecentTemperature
        && ((float) $device['last_temp'] > (float) $device['temp_max']
        || (float) $device['last_temp'] < (float) $device['temp_min']);
    $rangeBadgeClass = !$hasRecentTemperature ? 'secondary' : ($isTempAlert ? 'danger' : 'success');
    $rangeBadgeText = !$hasRecentTemperature ? 'Sem dados recentes' : ($isTempAlert ? 'Fora do intervalo' : 'Dentro do intervalo');
    ?>
    <div class="col-sm-6 col-lg-4 col-xl-3">
        <a
            href="<?= BASE_URL ?>/dashboard/device?id=<?= (int) $device['id'] ?>"
            class="card border-0 shadow-sm w-100 text-start device-selector-card"
            style="text-decoration: none; color: inherit;"
        >
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="fw-semibold"><i class="bi bi-cpu me-2"></i><?= htmlspecialchars($device['name']) ?></span>
                    <span class="badge bg-<?= $isOnline ? 'success' : 'secondary' ?>"><?= $isOnline ? 'Online' : 'Offline' ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-end">
                    <div>
                        <div class="text-muted small">Temperatura</div>
                        <div class="fs-4 fw-bold">
                            <?= $hasRecentTemperature ? number_format((float) $device['last_temp'], 1) . '°C' : '--' ?>
                        </div>
                    </div>
                    <span class="badge bg-<?= $rangeBadgeClass ?>">
                        <?= $rangeBadgeText ?>
                    </span>
                </div>
                <div class="text-muted small mt-2">
                    Ultima comunicacao: <?= $lastSeen ? htmlspecialchars(date('Y-m-d H:i', strtotime($lastSeen))) : 'N/A' ?>
                </div>
            </div>
        </a>
    </div>
<?php endforeach; ?>
</div>

<?php if (empty($devices)): ?>
<div class="alert alert-info">Ainda nao existem dispositivos registados.</div>
<?php endif; ?>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
