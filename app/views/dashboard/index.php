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

<?php
$locationLabels = [];
foreach ($devices as $deviceRow) {
    $locationLabel = trim((string) ($deviceRow['location'] ?? ''));
    if ($locationLabel === '') {
        $locationLabel = 'Sem localizacao';
    }
    $locationLabels[$locationLabel] = true;
}

$locationTabs = array_keys($locationLabels);
natcasesort($locationTabs);
$locationTabs = array_values($locationTabs);

$locationSlug = static function (string $value): string {
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');

    return $slug !== '' ? $slug : 'sem-localizacao';
};
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h5 class="mb-0">Dispositivos</h5>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <div class="btn-group btn-group-sm" role="group" aria-label="Visualizacao de dispositivos">
            <input type="radio" class="btn-check" name="deviceViewMode" id="viewModeAll" autocomplete="off" checked>
            <label class="btn btn-outline-primary" for="viewModeAll">Todos</label>

            <input type="radio" class="btn-check" name="deviceViewMode" id="viewModeByLocation" autocomplete="off">
            <label class="btn btn-outline-primary" for="viewModeByLocation">Abas por localizacao</label>
        </div>
        <div class="d-flex align-items-center gap-2" id="deviceVisibilityToggles">
            <span class="text-muted small">Filtro:</span>
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" role="switch" id="showOfflineDevices">
                <label class="form-check-label small" for="showOfflineDevices">Mostrar inativos</label>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($locationTabs)): ?>
<ul class="nav nav-tabs mb-3 d-none" id="locationTabs" role="tablist">
    <?php foreach ($locationTabs as $index => $locationTabLabel): ?>
    <?php $locationKey = $locationSlug($locationTabLabel); ?>
    <li class="nav-item" role="presentation">
        <button class="nav-link<?= $index === 0 ? ' active' : '' ?>"
                type="button"
                data-location-filter="<?= htmlspecialchars($locationKey) ?>"
                aria-selected="<?= $index === 0 ? 'true' : 'false' ?>">
            <?= htmlspecialchars($locationTabLabel) ?>
        </button>
    </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>

<div class="row g-3 mb-4" id="deviceCards">
<?php foreach ($devices as $device): ?>
    <?php
    $locationLabel = trim((string) ($device['location'] ?? ''));
    if ($locationLabel === '') {
        $locationLabel = 'Sem localizacao';
    }
    $locationKey = $locationSlug($locationLabel);

    $isInactive = empty($device['active']);
    $lastSeen = $device['last_seen_at'] ?? $device['last_reading'] ?? null;
    $secondsSinceSeen = isset($device['seconds_since_seen']) ? (int) $device['seconds_since_seen'] : null;
    $secondsSinceReading = isset($device['seconds_since_reading']) ? (int) $device['seconds_since_reading'] : null;
    $isRecentlySeen = $secondsSinceSeen !== null
        && $secondsSinceSeen >= 0
        && $secondsSinceSeen <= (DEVICE_ONLINE_WINDOW_MINUTES * 60);
    $isOnline = !$isInactive && $isRecentlySeen;
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
    $isDoorMonitoringEnabled = !empty($device['monitor_door_openings']);
    $doorBadgeClass = !$isDoorMonitoringEnabled
        ? 'secondary'
        : (!$hasDoorState ? 'secondary' : ($isDoorOpen ? 'warning' : 'success'));
    $doorBadgeText = !$isDoorMonitoringEnabled
        ? 'Monitorizacao da porta desativada'
        : (!$hasDoorState ? 'Estado da porta desconhecido' : ($isDoorOpen ? 'Porta aberta' : 'Porta fechada'));
    $isPaused = !empty($device['recordings_paused']);
    $pauseReason = htmlspecialchars($device['pause_reason'] ?? '');
    $pausedAt = !empty($device['paused_at']) ? date('Y-m-d H:i', strtotime($device['paused_at'])) : '';
    $statusBadgeClass = $isInactive ? 'danger' : ($isOnline ? 'success' : 'secondary');
    $statusBadgeText = $isInactive ? 'Inativo' : ($isOnline ? 'Online' : 'Offline');
    $cardClasses = 'card border-0 shadow-sm w-100 device-card';
    if ($isInactive) {
        $cardClasses .= ' device-card-inactive';
    } elseif ($isPaused) {
        $cardClasses .= ' border border-danger border-2';
    }
    $cardStyle = $isPaused && !$isInactive ? 'background-color:#fff5f5;' : '';
    ?>
    <div class="col-sm-6 col-lg-4 col-xl-3 d-flex flex-column"
         data-device-id="<?= (int) $device['id'] ?>"
         data-location-group="<?= htmlspecialchars($locationKey) ?>"
            data-device-online="<?= $isOnline ? '1' : '0' ?>"
            data-device-inactive="<?= $isInactive ? '1' : '0' ?>">
        <div class="<?= $cardClasses ?> flex-fill d-flex flex-column"
             style="<?= $cardStyle ?>">

            <?php if ($isPaused && !$isInactive): ?>
            <div class="card-header bg-danger text-white py-2 px-3 d-flex align-items-center gap-2">
                <i class="bi bi-pause-circle-fill"></i>
                <span class="fw-semibold small">Registos pausados</span>
            </div>
            <?php endif; ?>

            <a href="<?= BASE_URL ?>/dashboard/device?id=<?= (int) $device['id'] ?>"
               class="card-body text-decoration-none d-block device-card-body flex-grow-1"
               style="color: inherit;">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="fw-semibold device-name"><i class="bi bi-cpu me-2"></i><?= htmlspecialchars($device['name']) ?></span>
                    <span class="badge device-online-badge bg-<?= $statusBadgeClass ?>"><?= $statusBadgeText ?></span>
                    
                </div>
                <?php if (!$isInactive && !empty($device['location'])): ?>
                    <div class="text-muted" style="font-size: 0.7rem; margin-left: 28px; line-height: 1;">
                        <?= htmlspecialchars($device['location']) ?>
                    </div>
                <?php endif; ?>

                <?php if ($isInactive): ?>
                <div class="device-card-inactive-message my-auto">
                    <div class="device-card-inactive-title">
                        <i class="bi bi-slash-circle me-2"></i>Dispositivo inativo
                    </div>
                    <div class="device-card-inactive-text">A monitorizacao e os registos estao desativados.</div>
                </div>
                <?php elseif ($isPaused): ?>
                <div class="mb-2">
                    <div class="text-danger small fw-semibold"><i class="bi bi-info-circle me-1"></i>Motivo:</div>
                    <div class="text-danger small"><?= $pauseReason ?></div>
                    <?php if ($pausedAt): ?>
                    <div class="text-muted small">Pausado em: <?= $pausedAt ?></div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
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
                <?php endif; ?>

                <div class="text-muted small mt-2 device-last-seen">
                    Ultima comunicacao: <?= $lastSeen ? htmlspecialchars(date('Y-m-d H:i', strtotime($lastSeen))) : 'N/A' ?>
                </div>
            </a>

            <div class="card-footer bg-transparent border-0 pt-0 pb-2 px-3">
                <?php if ($isInactive): ?>
                <div class="device-card-footer-placeholder" aria-hidden="true"></div>
                <?php elseif ($isPaused): ?>
                <button type="button"
                        class="btn btn-success btn-sm w-100 btn-resume-recordings"
                        data-device-id="<?= (int) $device['id'] ?>"
                        data-device-name="<?= htmlspecialchars($device['name']) ?>">
                    <i class="bi bi-play-circle me-1"></i>Retomar registos
                </button>
                <?php else: ?>
                <button type="button"
                        class="btn btn-outline-danger btn-sm w-100 btn-pause-recordings"
                        data-device-id="<?= (int) $device['id'] ?>"
                        data-device-name="<?= htmlspecialchars($device['name']) ?>">
                    <i class="bi bi-pause-circle me-1"></i>Parar registos
                </button>
                <?php endif; ?>
            </div>

        </div>
    </div>
<?php endforeach; ?>
</div>

<?php if (!empty($devices)): ?>
<div class="alert alert-info d-none" id="deviceFilterEmptyState">Nenhum dispositivo corresponde ao filtro selecionado.</div>
<?php endif; ?>

<?php if (empty($devices)): ?>
<div class="alert alert-info">Ainda nao existem dispositivos registados.</div>
<?php endif; ?>

<!-- Modal: Parar registos -->
<div class="modal fade" id="modalPauseRecordings" tabindex="-1" aria-labelledby="modalPauseLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalPauseLabel">
                    <i class="bi bi-pause-circle me-2 text-danger"></i>Parar registos
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">
                    Vai pausar os registos de temperatura de <strong id="pauseDeviceName"></strong>.
                    Indique o motivo da paragem:
                </p>
                <div class="mb-3">
                    <label for="pauseReasonInput" class="form-label fw-semibold">Motivo <span class="text-danger">*</span></label>
                    <textarea id="pauseReasonInput" class="form-control" rows="3"
                              placeholder="Ex: Limpeza da câmara, manutenção, etc." maxlength="500"></textarea>
                    <div class="invalid-feedback">Por favor indique o motivo da paragem.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnConfirmPause">
                    <i class="bi bi-pause-circle me-1"></i>Parar registos
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const BASE_URL = '<?= BASE_URL ?>';
    const VIEW_STORAGE_KEY = 'dashboard-device-view-mode-location';
    let pendingDeviceId = null;

    const cardsContainer = document.getElementById('deviceCards');
    const viewModeAll = document.getElementById('viewModeAll');
    const viewModeByLocation = document.getElementById('viewModeByLocation');
    const locationTabs = document.getElementById('locationTabs');
    const locationButtons = Array.from(document.querySelectorAll('[data-location-filter]'));
    let activeLocation = locationButtons[0]?.dataset.locationFilter || '';

    function setLocationButtonActive(locationKey) {
        locationButtons.forEach(function (button) {
            const isActive = button.dataset.locationFilter === locationKey;
            button.classList.toggle('active', isActive);
            button.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
    }

    function applyDeviceViewFilter() {
        if (!cardsContainer) {
            return;
        }

        const mode = viewModeByLocation && viewModeByLocation.checked ? 'locations' : 'all';
        const cards = cardsContainer.querySelectorAll('[data-device-id]');

        if (locationTabs) {
            locationTabs.classList.toggle('d-none', mode !== 'locations');
        }

        cards.forEach(function (card) {
            if (mode === 'all') {
                card.classList.remove('d-none');
                return;
            }

            const cardLocation = card.dataset.locationGroup || '';
            card.classList.toggle('d-none', cardLocation !== activeLocation);
        });
    }

    function saveViewMode() {
        const mode = viewModeByLocation && viewModeByLocation.checked ? 'locations' : 'all';
        localStorage.setItem(VIEW_STORAGE_KEY, mode);
    }

    if (viewModeAll) {
        viewModeAll.addEventListener('change', function () {
            saveViewMode();
            applyDeviceViewFilter();
        });
    }

    if (viewModeByLocation) {
        viewModeByLocation.addEventListener('change', function () {
            saveViewMode();
            applyDeviceViewFilter();
        });
    }

    locationButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            activeLocation = button.dataset.locationFilter || activeLocation;
            setLocationButtonActive(activeLocation);
            applyDeviceViewFilter();
        });
    });

    const storedMode = localStorage.getItem(VIEW_STORAGE_KEY);
    if (storedMode === 'locations' && viewModeByLocation) {
        viewModeByLocation.checked = true;
    } else if (viewModeAll) {
        viewModeAll.checked = true;
    }

    setLocationButtonActive(activeLocation);
    applyDeviceViewFilter();

    // --- Pausar ---
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-pause-recordings');
        if (!btn) return;
        e.preventDefault();

        pendingDeviceId = btn.dataset.deviceId;
        document.getElementById('pauseDeviceName').textContent = btn.dataset.deviceName;
        document.getElementById('pauseReasonInput').value = '';
        document.getElementById('pauseReasonInput').classList.remove('is-invalid');

        const modal = new bootstrap.Modal(document.getElementById('modalPauseRecordings'));
        modal.show();
    });

    document.getElementById('btnConfirmPause').addEventListener('click', function () {
        const reason = document.getElementById('pauseReasonInput').value.trim();
        if (!reason) {
            document.getElementById('pauseReasonInput').classList.add('is-invalid');
            return;
        }
        document.getElementById('pauseReasonInput').classList.remove('is-invalid');

        const body = new URLSearchParams({ device_id: pendingDeviceId, reason });
        fetch(BASE_URL + '/dashboard/devices/pause', { method: 'POST', body })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('modalPauseRecordings')).hide();
                    location.reload();
                } else {
                    alert(data.error ?? 'Erro ao pausar registos.');
                }
            })
            .catch(() => alert('Erro de comunicação com o servidor.'));
    });

    // --- Retomar ---
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-resume-recordings');
        if (!btn) return;
        e.preventDefault();

        if (!confirm('Retomar os registos de temperatura de "' + btn.dataset.deviceName + '"?')) return;

        const body = new URLSearchParams({ device_id: btn.dataset.deviceId });
        fetch(BASE_URL + '/dashboard/devices/resume', { method: 'POST', body })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error ?? 'Erro ao retomar registos.');
                }
            })
            .catch(() => alert('Erro de comunicação com o servidor.'));
    });
})();
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
