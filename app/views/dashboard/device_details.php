<?php require_once __DIR__ . '/../../../config/constants.php'; ?>
<?php require_once __DIR__ . '/../../../app/middleware/Auth.php'; ?>
<?php $pageTitle = 'Detalhe do dispositivo'; ?>
<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
$lastSeen = $device['last_seen_at'] ?? null;
$lastSeenTs = $lastSeen ? strtotime($lastSeen) : false;
$isRecentlySeen = $lastSeenTs !== false && (time() - $lastSeenTs) <= (DEVICE_ONLINE_WINDOW_MINUTES * 60);
$isOnline = !empty($device['active']) && $isRecentlySeen;
?>

<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h5 class="mb-1">
            Historico - <?= htmlspecialchars($device['name']) ?>
            <span class="badge bg-<?= $isOnline ? 'success' : 'secondary' ?> ms-2"><?= $isOnline ? 'Online' : 'Offline' ?></span>
        </h5>
        <div class="text-muted small">
            Ultima comunicacao: <?= $lastSeen ? htmlspecialchars(date('Y-m-d H:i', strtotime($lastSeen))) : 'N/A' ?>
        </div>
    </div>
    <a href="<?= BASE_URL ?>/dashboard" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Voltar ao painel
    </a>
</div>

<div class="card shadow-sm border-0 mb-4" id="chartSection">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="fw-semibold">Historico de temperatura</span>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="btn-group btn-group-sm" role="group" id="periodButtons">
                <button type="button" class="btn btn-outline-primary active" data-period="24h">24h</button>
                <button type="button" class="btn btn-outline-primary" data-period="7d">7 dias</button>
                <button type="button" class="btn btn-outline-primary" data-period="30d">30 dias</button>
            </div>
            <div class="d-flex align-items-center gap-1">
                <input id="chartFrom" type="datetime-local" class="form-control form-control-sm" title="De" style="width:auto">
                <span class="text-muted">–</span>
                <input id="chartTo" type="datetime-local" class="form-control form-control-sm" title="Ate" style="width:auto">
                <button type="button" class="btn btn-sm btn-primary" id="applyCustomRange" title="Pesquisar intervalo personalizado">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </div>
    </div>
    <div class="card-body">
        <canvas id="selected-device-chart" height="100"></canvas>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const deviceId = <?= (int) $device['id'] ?>;
    const periodButtons = document.getElementById('periodButtons');
    const fromInput = document.getElementById('chartFrom');
    const toInput = document.getElementById('chartTo');
    const applyBtn = document.getElementById('applyCustomRange');
    let currentPeriod = '24h';

    async function refreshChart() {
        await loadChart(deviceId, currentPeriod, null, {
            canvasId: 'selected-device-chart',
        });
    }

    periodButtons.querySelectorAll('button').forEach(button => {
        button.addEventListener('click', async function () {
            currentPeriod = button.dataset.period;
            periodButtons.querySelectorAll('button').forEach(b => b.classList.remove('active'));
            button.classList.add('active');
            await refreshChart();
        });
    });

    applyBtn.addEventListener('click', async function () {
        if (!fromInput.value || !toInput.value) {
            return;
        }

        await loadChart(deviceId, 'custom', null, {
            canvasId: 'selected-device-chart',
            from: fromInput.value,
            to: toInput.value,
        });
    });

    refreshChart();
});
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
