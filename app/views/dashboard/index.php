<?php require_once __DIR__ . '/../../../config/constants.php'; ?>
<?php require_once __DIR__ . '/../../../app/middleware/Auth.php'; ?>
<?php $pageTitle = 'Painel'; ?>
<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <a href="<?= BASE_URL ?>/admin/devices" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="icon-box bg-primary text-white rounded p-3"><i class="bi bi-cpu fs-4"></i></div>
                <div>
                    <div class="fs-4 fw-bold"><?= (int) $deviceCount ?></div>
                    <div class="text-muted small">Dispositivos ativos</div>
                </div>
            </div>
        </div>
        </a>
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
        <a href="<?= BASE_URL ?>/admin/alerts" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="icon-box bg-danger text-white rounded p-3"><i class="bi bi-bell fs-4"></i></div>
                <div>
                    <div class="fs-4 fw-bold"><?= (int) $openAlerts ?></div>
                    <div class="text-muted small">Alertas abertos</div>
                </div>
            </div>
        </div>
        </a>
    </div>
</div>

<div class="d-flex align-items-center justify-content-between mb-3">
    <h5 class="mb-0">Dispositivos</h5>
</div>

<div class="row g-3 mb-4" id="deviceCards">
<?php foreach ($devices as $device): ?>
    <?php
    $lastSeen = $device['last_seen_at'] ?? $device['last_reading'] ?? null;
    $secondsSinceSeen = isset($device['seconds_since_seen']) ? (int) $device['seconds_since_seen'] : null;
    $secondsSinceReading = isset($device['seconds_since_reading']) ? (int) $device['seconds_since_reading'] : null;
    $isRecentlySeen = $secondsSinceSeen !== null
        && $secondsSinceSeen >= 0
        && $secondsSinceSeen <= (DEVICE_ONLINE_WINDOW_MINUTES * 60);
    $isOnline = !empty($device['active']) && $isRecentlySeen;
    $hasRecentTemperature = $device['last_temp'] !== null
        && $secondsSinceReading !== null
        && $secondsSinceReading >= 0
        && $secondsSinceReading <= (DEVICE_ONLINE_WINDOW_MINUTES * 60);
    $isTempAlert = $hasRecentTemperature
        && ((float) $device['last_temp'] > (float) $device['temp_max']
        || (float) $device['last_temp'] < (float) $device['temp_min']);
    $rangeBadgeClass = !$hasRecentTemperature ? 'secondary' : ($isTempAlert ? 'danger' : 'success');
    $rangeBadgeText = !$hasRecentTemperature ? 'Sem dados recentes' : ($isTempAlert ? 'Fora do intervalo' : 'Dentro do intervalo');
    $hasDoorState = !empty($device['door_updated_at']);
    $isDoorOpen = isset($device['door_open']) && (int) $device['door_open'] === 1;
    $doorBadgeClass = !$hasDoorState ? 'secondary' : ($isDoorOpen ? 'warning' : 'success');
    $doorBadgeText = !$hasDoorState ? 'Estado da porta desconhecido' : ($isDoorOpen ? 'Porta aberta' : 'Porta fechada');
    ?>
    <div class="col-sm-6 col-lg-4 col-xl-3" data-device-id="<?= (int) $device['id'] ?>">
        <a
            href="<?= BASE_URL ?>/dashboard/device?id=<?= (int) $device['id'] ?>"
            class="card border-0 shadow-sm w-100 text-start device-selector-card"
            style="text-decoration: none; color: inherit;"
        >
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="fw-semibold device-name"><i class="bi bi-cpu me-2"></i><?= htmlspecialchars($device['name']) ?></span>
                    <span class="badge device-online-badge bg-<?= $isOnline ? 'success' : 'secondary' ?>"><?= $isOnline ? 'Online' : 'Offline' ?></span>
                    
                </div>
                <?php if (!empty($device['location'])): ?>
                    <div class="text-muted" style="font-size: 0.7rem; margin-left: 28px; line-height: 1;">
                        <?= htmlspecialchars($device['location']) ?>
                    </div>
                <?php endif; ?>
                <div class="d-flex justify-content-between align-items-end">
                    <div>
                        <div class="text-muted small">Temperatura</div>
                        <div class="fs-4 fw-bold device-temp-value">
                            <?= $hasRecentTemperature ? number_format((float) $device['last_temp'], 1) . '°C' : '--' ?>
                        </div>
                    </div>
                    <span class="badge device-range-badge bg-<?= $rangeBadgeClass ?>">
                        <?= $rangeBadgeText ?>
                    </span>
                </div>
                <div class="mt-2">
                    <span class="badge device-door-badge bg-<?= $doorBadgeClass ?>"><?= $doorBadgeText ?></span>
                </div>
                <div class="text-muted small mt-2 device-last-seen">
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
