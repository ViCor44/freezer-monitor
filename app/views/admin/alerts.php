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
        <?php
        $groupedAlerts = [];
        foreach ($alerts as $a) {
            $deviceId = (int) ($a['device_id'] ?? 0);
            $deviceKey = (string) $deviceId;

            if (!isset($groupedAlerts[$deviceKey])) {
                $groupedAlerts[$deviceKey] = [
                    'device_id' => $deviceId,
                    'device_name' => (string) ($a['device_name'] ?? ('Dispositivo #' . $deviceId)),
                    'latest' => $a,
                    'total' => 0,
                    'open_count' => 0,
                    'ack_count' => 0,
                    'resolved_count' => 0,
                    'latest_open_id' => null,
                    'latest_actionable_id' => null,
                ];
            }

            $groupedAlerts[$deviceKey]['total']++;

            if (($a['status'] ?? '') === ALERT_STATUS_OPEN) {
                $groupedAlerts[$deviceKey]['open_count']++;
                if ($groupedAlerts[$deviceKey]['latest_open_id'] === null) {
                    $groupedAlerts[$deviceKey]['latest_open_id'] = (int) $a['id'];
                }
            } elseif (($a['status'] ?? '') === ALERT_STATUS_ACKNOWLEDGED) {
                $groupedAlerts[$deviceKey]['ack_count']++;
            } elseif (($a['status'] ?? '') === ALERT_STATUS_RESOLVED) {
                $groupedAlerts[$deviceKey]['resolved_count']++;
            }

            if (($a['status'] ?? '') !== ALERT_STATUS_RESOLVED && $groupedAlerts[$deviceKey]['latest_actionable_id'] === null) {
                $groupedAlerts[$deviceKey]['latest_actionable_id'] = (int) $a['id'];
            }
        }
        ?>
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Dispositivo</th>
                    <th>Resumo</th>
                    <th>Tipo</th>
                    <th>Mensagem</th>
                    <th>Temperatura</th>
                    <th>Estado</th>
                    <th>Criado em</th>
                    <th class="text-end">Acoes</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($groupedAlerts as $group): ?>
                <?php $a = $group['latest']; ?>
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
                    <td><?= htmlspecialchars((string)($a['device_name'] ?? ('Dispositivo #' . ((int)($a['device_id'] ?? 0))))) ?></td>
                    <td>
                        <span class="badge bg-danger"><?= (int) $group['open_count'] ?> aberto(s)</span>
                        <span class="badge bg-warning text-dark"><?= (int) $group['ack_count'] ?> reconhecido(s)</span>
                        <span class="badge bg-success"><?= (int) $group['resolved_count'] ?> resolvido(s)</span>
                    </td>
                    <td><span class="badge bg-<?= $typeColor ?>"><?= htmlspecialchars($typeLabel) ?></span></td>
                    <td><?= htmlspecialchars($a['message']) ?></td>
                    <td><?= $a['temperature'] !== null ? number_format((float)$a['temperature'], 1) . '°C' : '—' ?></td>
                    <td><span class="badge bg-<?= $statusColor ?>"><?= htmlspecialchars($statusLabel) ?></span></td>
                    <td><?= date('Y-m-d H:i', strtotime($a['created_at'])) ?></td>
                    <td class="text-end">
                        <?php if ($group['latest_open_id'] !== null): ?>
                        <form method="post" action="<?= BASE_URL ?>/admin/alerts/acknowledge" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
                            <input type="hidden" name="id" value="<?= (int) $group['latest_open_id'] ?>">
                            <button class="btn btn-sm btn-warning"><i class="bi bi-check2"></i> Reconhecer</button>
                        </form>
                        <?php endif; ?>
                        <?php if ($group['latest_actionable_id'] !== null && Auth::isAdmin()): ?>
                        <button type="button" class="btn btn-sm btn-outline-success"
                            data-bs-toggle="modal" data-bs-target="#resolveModal"
                            data-action="resolve-all"
                            data-device-id="<?= (int) $group['device_id'] ?>"
                            data-device-name="<?= htmlspecialchars((string)($a['device_name'] ?? '')) ?>"
                            data-csrf="<?= Auth::csrfToken() ?>">
                            <i class="bi bi-check2-circle"></i> Resolver todos
                        </button>
                        <button type="button" class="btn btn-sm btn-success"
                            data-bs-toggle="modal" data-bs-target="#resolveModal"
                            data-action="resolve"
                            data-alert-id="<?= (int) $group['latest_actionable_id'] ?>"
                            data-device-name="<?= htmlspecialchars((string)($a['device_name'] ?? '')) ?>"
                            data-csrf="<?= Auth::csrfToken() ?>">
                            <i class="bi bi-check2-all"></i> Resolver
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($groupedAlerts)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">Nao foram encontrados alertas.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Resolver Alerta -->
<div class="modal fade" id="resolveModal" tabindex="-1" aria-labelledby="resolveModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="resolveModalLabel">Resolver alerta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="resolveForm" method="post">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" id="resolveCsrf">
                    <input type="hidden" name="id" id="resolveAlertId">
                    <input type="hidden" name="device_id" id="resolveDeviceId">
                    <p class="text-muted mb-3">Dispositivo: <strong id="resolveDeviceName"></strong></p>
                    <div class="mb-3">
                        <label for="resolveNoteText" class="form-label">Nota <span class="text-muted">(opcional)</span></label>
                        <textarea class="form-control" id="resolveNoteText" name="note_text" rows="3"
                            placeholder="Ex: Porta estava aberta, temperatura normalizada..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check2-all me-1"></i>Confirmar resolucao</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('resolveModal').addEventListener('show.bs.modal', function (event) {
    const btn = event.relatedTarget;
    const action = btn.dataset.action;
    const form = document.getElementById('resolveForm');
    const csrf = btn.dataset.csrf;

    document.getElementById('resolveCsrf').value = csrf;
    document.getElementById('resolveDeviceName').textContent = btn.dataset.deviceName || '';
    document.getElementById('resolveNoteText').value = '';
    document.getElementById('resolveAlertId').value = '';
    document.getElementById('resolveDeviceId').value = '';

    if (action === 'resolve-all') {
        form.action = '<?= BASE_URL ?>/admin/alerts/resolve-all';
        document.getElementById('resolveDeviceId').value = btn.dataset.deviceId;
        document.getElementById('resolveModalLabel').textContent = 'Resolver todos os alertas';
    } else {
        form.action = '<?= BASE_URL ?>/admin/alerts/resolve';
        document.getElementById('resolveAlertId').value = btn.dataset.alertId;
        document.getElementById('resolveModalLabel').textContent = 'Resolver alerta';
    }
});
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
