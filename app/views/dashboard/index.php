<?php require_once __DIR__ . '/../../../config/constants.php'; ?>
<?php require_once __DIR__ . '/../../../app/middleware/Auth.php'; ?>
<?php $pageTitle = 'Dashboard'; ?>
<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="icon-box bg-primary text-white rounded p-3"><i class="bi bi-cpu fs-4"></i></div>
                <div>
                    <div class="fs-4 fw-bold"><?= (int) $deviceCount ?></div>
                    <div class="text-muted small">Active Devices</div>
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
                    <div class="text-muted small">Readings Today</div>
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
                    <div class="text-muted small">Open Alerts</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Device cards with charts -->
<?php foreach ($devices as $device): ?>
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-cpu me-2"></i><?= htmlspecialchars($device['name']) ?></span>
        <div class="d-flex gap-2 align-items-center">
            <?php if ($device['last_temp'] !== null): ?>
                <span class="badge bg-<?= $device['last_temp'] > $device['temp_max'] || $device['last_temp'] < $device['temp_min'] ? 'danger' : 'success' ?> fs-6">
                    <?= number_format((float)$device['last_temp'], 1) ?>°C
                </span>
            <?php endif; ?>
            <span class="badge bg-<?= $device['active'] ? 'success' : 'secondary' ?>">
                <?= $device['active'] ? 'Online' : 'Offline' ?>
            </span>
        </div>
    </div>
    <div class="card-body">
        <div class="btn-group btn-group-sm mb-3" role="group">
            <button class="btn btn-outline-primary active" onclick="loadChart(<?= $device['id'] ?>, '24h', this)">24h</button>
            <button class="btn btn-outline-primary" onclick="loadChart(<?= $device['id'] ?>, '7d', this)">7 days</button>
            <button class="btn btn-outline-primary" onclick="loadChart(<?= $device['id'] ?>, '30d', this)">30 days</button>
        </div>
        <canvas id="chart-<?= $device['id'] ?>" height="100"></canvas>
        <?php if ($device['location']): ?>
            <p class="text-muted small mt-2"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($device['location']) ?></p>
        <?php endif; ?>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    loadChart(<?= $device['id'] ?>, '24h', null);
});
</script>
<?php endforeach; ?>

<?php if (empty($devices)): ?>
<div class="alert alert-info">No devices registered yet.</div>
<?php endif; ?>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
