<?php require_once __DIR__ . '/../../../config/constants.php'; ?>
<?php require_once __DIR__ . '/../../../app/middleware/Auth.php'; ?>
<?php $pageTitle = 'Alerts'; ?>
<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0"><i class="bi bi-bell me-2"></i>Alert Monitoring</h4>
    <div class="btn-group btn-group-sm">
        <a href="?status=" class="btn btn-outline-secondary <?= ($status ?? '') === '' ? 'active' : '' ?>">All</a>
        <a href="?status=open" class="btn btn-outline-danger <?= ($status ?? '') === 'open' ? 'active' : '' ?>">Open</a>
        <a href="?status=acknowledged" class="btn btn-outline-warning <?= ($status ?? '') === 'acknowledged' ? 'active' : '' ?>">Acknowledged</a>
        <a href="?status=resolved" class="btn btn-outline-success <?= ($status ?? '') === 'resolved' ? 'active' : '' ?>">Resolved</a>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Device</th>
                    <th>Type</th>
                    <th>Message</th>
                    <th>Temperature</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($alerts as $a): ?>
                <?php
                $typeColor = match($a['type']) {
                    ALERT_HIGH    => 'danger',
                    ALERT_LOW     => 'info',
                    ALERT_OFFLINE => 'secondary',
                    default       => 'dark'
                };
                $statusColor = match($a['status']) {
                    ALERT_STATUS_OPEN         => 'danger',
                    ALERT_STATUS_ACKNOWLEDGED => 'warning',
                    ALERT_STATUS_RESOLVED     => 'success',
                    default                   => 'secondary'
                };
                ?>
                <tr>
                    <td><?= htmlspecialchars($a['device_name']) ?></td>
                    <td><span class="badge bg-<?= $typeColor ?>"><?= htmlspecialchars($a['type']) ?></span></td>
                    <td><?= htmlspecialchars($a['message']) ?></td>
                    <td><?= $a['temperature'] !== null ? number_format((float)$a['temperature'], 1) . '°C' : '—' ?></td>
                    <td><span class="badge bg-<?= $statusColor ?>"><?= htmlspecialchars($a['status']) ?></span></td>
                    <td><?= date('Y-m-d H:i', strtotime($a['created_at'])) ?></td>
                    <td class="text-end">
                        <?php if ($a['status'] === ALERT_STATUS_OPEN): ?>
                        <form method="post" action="<?= BASE_URL ?>/admin/alerts/acknowledge" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                            <button class="btn btn-sm btn-warning"><i class="bi bi-check2"></i> Acknowledge</button>
                        </form>
                        <?php endif; ?>
                        <?php if ($a['status'] !== ALERT_STATUS_RESOLVED && Auth::isAdmin()): ?>
                        <form method="post" action="<?= BASE_URL ?>/admin/alerts/resolve" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                            <button class="btn btn-sm btn-success"><i class="bi bi-check2-all"></i> Resolve</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($alerts)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No alerts found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
