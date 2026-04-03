<?php require_once __DIR__ . '/../../../config/constants.php'; ?>
<?php require_once __DIR__ . '/../../../app/middleware/Auth.php'; ?>
<?php $pageTitle = 'Alertas'; ?>
<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0"><i class="bi bi-bell me-2"></i>Monitorizacao de alertas</h4>
    <div class="btn-group btn-group-sm">
        <a href="?status=" class="btn btn-outline-secondary <?= ($status ?? '') === '' ? 'active' : '' ?>">Todos</a>
        <a href="?status=open" class="btn btn-outline-danger <?= ($status ?? '') === 'open' ? 'active' : '' ?>">Abertos</a>
        <a href="?status=acknowledged" class="btn btn-outline-warning <?= ($status ?? '') === 'acknowledged' ? 'active' : '' ?>">Reconhecidos</a>
        <a href="?status=resolved" class="btn btn-outline-success <?= ($status ?? '') === 'resolved' ? 'active' : '' ?>">Resolvidos</a>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Dispositivo</th>
                    <th>Tipo</th>
                    <th>Mensagem</th>
                    <th>Temperatura</th>
                    <th>Estado</th>
                    <th>Criado em</th>
                    <th class="text-end">Acoes</th>
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
                $typeLabel = match($a['type']) {
                    ALERT_HIGH    => 'Temperatura alta',
                    ALERT_LOW     => 'Temperatura baixa',
                    ALERT_OFFLINE => 'Dispositivo offline',
                    default       => $a['type']
                };
                $statusLabel = match($a['status']) {
                    ALERT_STATUS_OPEN         => 'Aberto',
                    ALERT_STATUS_ACKNOWLEDGED => 'Reconhecido',
                    ALERT_STATUS_RESOLVED     => 'Resolvido',
                    default                   => $a['status']
                };
                ?>
                <tr>
                    <td><?= htmlspecialchars($a['device_name']) ?></td>
                    <td><span class="badge bg-<?= $typeColor ?>\"><?= htmlspecialchars($typeLabel) ?></span></td>
                    <td><?= htmlspecialchars($a['message']) ?></td>
                    <td><?= $a['temperature'] !== null ? number_format((float)$a['temperature'], 1) . '°C' : '—' ?></td>
                    <td><span class="badge bg-<?= $statusColor ?>\"><?= htmlspecialchars($statusLabel) ?></span></td>
                    <td><?= date('Y-m-d H:i', strtotime($a['created_at'])) ?></td>
                    <td class="text-end">
                        <?php if ($a['status'] === ALERT_STATUS_OPEN): ?>
                        <form method="post" action="<?= BASE_URL ?>/admin/alerts/acknowledge" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                            <button class="btn btn-sm btn-warning"><i class="bi bi-check2"></i> Reconhecer</button>
                        </form>
                        <?php endif; ?>
                        <?php if ($a['status'] !== ALERT_STATUS_RESOLVED && Auth::isAdmin()): ?>
                        <form method="post" action="<?= BASE_URL ?>/admin/alerts/resolve" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                            <button class="btn btn-sm btn-success"><i class="bi bi-check2-all"></i> Resolver</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($alerts)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Nao foram encontrados alertas.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
