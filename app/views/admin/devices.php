<?php require_once __DIR__ . '/../../../config/constants.php'; ?>
<?php require_once __DIR__ . '/../../../app/middleware/Auth.php'; ?>
<?php $pageTitle = 'Gestao de dispositivos'; ?>
<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0"><i class="bi bi-cpu me-2"></i>Gestao de dispositivos</h4>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addDeviceModal">
        <i class="bi bi-plus-lg me-1"></i>Adicionar dispositivo
    </button>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Nome</th>
                    <th>DevEUI</th>
                    <th>Zona</th>
                    <th>Localizacao</th>
                    <th>Max °C</th>
                    <th>Min °C</th>
                    <th>Correção</th>
                    <th>Monitor porta</th>
                    <th>Ultima comunicacao</th>
                    <th>Estado</th>
                    <th class="text-end">Acoes</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($devices as $d): ?>
                <tr>
                    <td><?= htmlspecialchars($d['name']) ?></td>
                    <td><code><?= htmlspecialchars($d['dev_eui']) ?></code></td>
                    <td><?= htmlspecialchars(trim((string) ($d['zone'] ?? '')) ?: 'Sem zona') ?></td>
                    <td><?= htmlspecialchars($d['location'] ?? '—') ?></td>
                    <td><?= number_format((float)$d['temp_max'], 1) ?></td>
                    <td><?= number_format((float)$d['temp_min'], 1) ?></td>
                    <td><?= number_format((float)($d['calibration_offset'] ?? 0), 1) ?></td>
                    <td>
                        <span class="badge bg-<?= !empty($d['monitor_door_openings']) ? 'info' : 'secondary' ?>">
                            <?= !empty($d['monitor_door_openings']) ? 'Ativa' : 'Desativada' ?>
                        </span>
                    </td>
                    <td><?= $d['last_seen_at'] ? date('Y-m-d H:i', strtotime($d['last_seen_at'])) : '—' ?></td>
                    <td>
                        <span class="device-status <?= $d['active'] ? 'device-status-active' : 'device-status-inactive' ?>">
                            <?= $d['active'] ? 'Ativo' : 'Inativo' ?>
                        </span>
                    </td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary"
                            data-bs-toggle="modal" data-bs-target="#editDeviceModal"
                            data-id="<?= $d['id'] ?>"
                            data-name="<?= htmlspecialchars($d['name']) ?>"
                            data-zone="<?= htmlspecialchars($d['zone'] ?? '') ?>"
                            data-location="<?= htmlspecialchars($d['location'] ?? '') ?>"
                            data-temp-max="<?= $d['temp_max'] ?>"
                            data-temp-min="<?= $d['temp_min'] ?>"
                            data-calibration-offset="<?= (float) ($d['calibration_offset'] ?? 0) ?>"
                            data-monitor-door="<?= (int) ($d['monitor_door_openings'] ?? 1) ?>"
                            data-active="<?= $d['active'] ?>">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form method="post" action="<?= BASE_URL ?>/admin/devices/delete" class="d-inline"
                            onsubmit="return confirm('Eliminar dispositivo?')">
                            <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
                            <input type="hidden" name="id" value="<?= $d['id'] ?>">
                            <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Adicionar Dispositivo -->
<div class="modal fade" id="addDeviceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="<?= BASE_URL ?>/admin/devices/create">
                <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
                <div class="modal-header"><h5 class="modal-title">Adicionar dispositivo</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Nome</label><input type="text" name="name" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">DevEUI <small class="text-muted">(16 hex chars)</small></label><input type="text" name="dev_eui" class="form-control" maxlength="16" pattern="[0-9a-fA-F]{16}" required></div>
                    <div class="mb-3"><label class="form-label">Zona</label><input type="text" name="zone" class="form-control" placeholder="Ex: Restaurante"></div>
                    <div class="mb-3"><label class="form-label">Localizacao</label><input type="text" name="location" class="form-control"></div>
                    <div class="row">
                        <div class="col"><label class="form-label">Max °C</label><input type="number" name="temp_max" class="form-control" value="<?= TEMP_MAX ?>" step="0.1"></div>
                        <div class="col"><label class="form-label">Min °C</label><input type="number" name="temp_min" class="form-control" value="<?= TEMP_MIN ?>" step="0.1"></div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Fator de calibracao (°C)</label>
                        <input type="number" name="calibration_offset" class="form-control" value="0" step="0.1">
                        <div class="form-text">Use valores positivos para somar e negativos para subtrair.</div>
                    </div>
                    <div class="mt-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="monitor_door_openings" value="1" id="addDeviceMonitorDoor" checked>
                            <label class="form-check-label" for="addDeviceMonitorDoor">Monitorizacao da abertura de porta</label>
                        </div>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="active" value="1" id="addDeviceActive" checked>
                            <label class="form-check-label" for="addDeviceActive">Ativo</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Adicionar</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Dispositivo -->
<div class="modal fade" id="editDeviceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="<?= BASE_URL ?>/admin/devices/update">
                <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
                <input type="hidden" name="id" id="editDeviceId">
                <div class="modal-header"><h5 class="modal-title">Editar dispositivo</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Nome</label><input type="text" name="name" id="editDeviceName" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Zona</label><input type="text" name="zone" id="editDeviceZone" class="form-control" placeholder="Ex: Restaurante"></div>
                    <div class="mb-3"><label class="form-label">Localizacao</label><input type="text" name="location" id="editDeviceLocation" class="form-control"></div>
                    <div class="row">
                        <div class="col"><label class="form-label">Max °C</label><input type="number" name="temp_max" id="editDeviceTempMax" class="form-control" step="0.1"></div>
                        <div class="col"><label class="form-label">Min °C</label><input type="number" name="temp_min" id="editDeviceTempMin" class="form-control" step="0.1"></div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Fator de calibracao (°C)</label>
                        <input type="number" name="calibration_offset" id="editDeviceCalibrationOffset" class="form-control" step="0.1">
                        <div class="form-text">Use valores positivos para somar e negativos para subtrair.</div>
                    </div>
                    <div class="mt-3">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="monitor_door_openings" id="editDeviceMonitorDoor" value="1">
                            <label class="form-check-label" for="editDeviceMonitorDoor">Monitorizacao da abertura de porta</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="active" id="editDeviceActive" value="1">
                            <label class="form-check-label" for="editDeviceActive">Ativo</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar</button></div>
            </form>
        </div>
    </div>
</div>

<script>
const editModal = document.getElementById('editDeviceModal');
if (editModal) {
    editModal.addEventListener('show.bs.modal', function (e) {
        const btn = e.relatedTarget;
        document.getElementById('editDeviceId').value       = btn.dataset.id;
        document.getElementById('editDeviceName').value     = btn.dataset.name;
        document.getElementById('editDeviceZone').value     = btn.dataset.zone ?? '';
        document.getElementById('editDeviceLocation').value = btn.dataset.location;
        document.getElementById('editDeviceTempMax').value  = btn.dataset.tempMax;
        document.getElementById('editDeviceTempMin').value  = btn.dataset.tempMin;
        document.getElementById('editDeviceCalibrationOffset').value = btn.dataset.calibrationOffset ?? '0';
        document.getElementById('editDeviceMonitorDoor').checked = btn.dataset.monitorDoor === '1';
        document.getElementById('editDeviceActive').checked = btn.dataset.active === '1';
    });
}
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
