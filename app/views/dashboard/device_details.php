<?php require_once __DIR__ . '/../../../config/constants.php'; ?>
<?php require_once __DIR__ . '/../../../app/middleware/Auth.php'; ?>
<?php $pageTitle = 'Detalhe do dispositivo'; ?>
<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
$lastSeen = $device['last_seen_at'] ?? $device['last_reading'] ?? null;
$secondsSinceSeen = isset($device['seconds_since_seen']) ? (int) $device['seconds_since_seen'] : null;
$isRecentlySeen = $secondsSinceSeen !== null
    && $secondsSinceSeen >= 0
    && $secondsSinceSeen <= (DEVICE_ONLINE_WINDOW_MINUTES * 60);
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
        <div class="row g-3 align-items-stretch">
            <div class="col-12 col-lg-4">
                <div class="history-list h-100 border rounded p-2">
                    <div class="table-responsive history-table-wrap">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Data/Hora</th>
                                    <th scope="col">Temp. (°C)</th>
                                </tr>
                            </thead>
                            <tbody id="temperatureHistoryTableBody">
                                <tr>
                                    <td colspan="2" class="text-muted text-center py-3">A carregar historico...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-8">
                <div class="history-chart-wrap h-100">
                    <canvas id="selected-device-chart" height="120"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const deviceId = <?= (int) $device['id'] ?>;
    const periodButtons = document.getElementById('periodButtons');
    const fromInput = document.getElementById('chartFrom');
    const toInput = document.getElementById('chartTo');
    const applyBtn = document.getElementById('applyCustomRange');
    const historyTableBody = document.getElementById('temperatureHistoryTableBody');
    let currentPeriod = '24h';

    function buildHistoryQuery(period) {
        const params = new URLSearchParams();
        params.set('device_id', String(deviceId));

        if (period === 'custom' && fromInput.value && toInput.value) {
            params.set('from', fromInput.value);
            params.set('to', toInput.value);
        } else {
            params.set('period', period);
        }

        return params.toString();
    }

    async function fetchHistoryData(period) {
        try {
            const query = buildHistoryQuery(period);
            const response = await fetch(`${window.BASE_URL || ''}/dashboard/chart?${query}`);
            if (!response.ok) {
                return null;
            }

            return await response.json();
        } catch (error) {
            return null;
        }
    }

    function formatDateForTable(value) {
        const dt = new Date(value);
        if (Number.isNaN(dt.getTime())) {
            return value;
        }

        return dt.toLocaleString([], {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function formatNumber(value) {
        const parsed = Number(value);
        if (!Number.isFinite(parsed)) {
            return '--';
        }

        return parsed.toFixed(1);
    }

    function renderHistoryTable(chartData) {
        if (!historyTableBody) {
            return;
        }

        const labels = chartData && Array.isArray(chartData.labels) ? chartData.labels : [];
        const temperatures = chartData && Array.isArray(chartData.temperature) ? chartData.temperature : [];
        if (!labels.length) {
            historyTableBody.innerHTML = '<tr><td colspan="2" class="text-muted text-center py-3">Sem dados para o periodo selecionado.</td></tr>';
            return;
        }

        const rows = labels.map((label, index) => {
            const dateText = formatDateForTable(label);
            const temperatureText = formatNumber(temperatures[index]);

            return `
                <tr>
                    <td>${dateText}</td>
                    <td>${temperatureText}</td>
                </tr>
            `;
        });

        historyTableBody.innerHTML = rows.reverse().join('');
    }

    async function refreshChart() {
        await loadChart(deviceId, currentPeriod, null, {
            canvasId: 'selected-device-chart',
        });

        const historyData = await fetchHistoryData(currentPeriod);
        renderHistoryTable(historyData);
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

        const historyData = await fetchHistoryData('custom');
        renderHistoryTable(historyData);
    });

    refreshChart();
});
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
