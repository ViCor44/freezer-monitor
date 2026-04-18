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
        <?php if (!empty($device['location'])): ?>
            <div class="text-muted" style="font-size: 0.7rem; margin-left: 28px; line-height: 1;">
                <?= htmlspecialchars($device['location']) ?>
            </div>
        <?php endif; ?>
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
                <button type="button" class="btn btn-sm btn-secondary" id="clearCustomRange" title="Limpar pesquisa">
                    <i class="bi bi-x-lg"></i>
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
                    <div class="row g-2 mb-3" id="historyStats">
                        <div class="col-6 col-xl-2">
                            <div class="border rounded p-2 h-100 history-stat-card stat-min">
                                <div class="text-muted small">Min</div>
                                <div class="fw-semibold" id="statMinTemp">--</div>
                            </div>
                        </div>
                        <div class="col-6 col-xl-2">
                            <div class="border rounded p-2 h-100 history-stat-card stat-max">
                                <div class="text-muted small">Max</div>
                                <div class="fw-semibold" id="statMaxTemp">--</div>
                            </div>
                        </div>
                        <div class="col-6 col-xl-2">
                            <div class="border rounded p-2 h-100 history-stat-card stat-avg">
                                <div class="text-muted small">Media</div>
                                <div class="fw-semibold" id="statAvgTemp">--</div>
                            </div>
                        </div>
                        <div class="col-6 col-xl-3">
                            <div class="border rounded p-2 h-100 history-stat-card stat-last">
                                <div class="text-muted small">Ultima</div>
                                <div class="fw-semibold" id="statLastTemp">--</div>
                            </div>
                        </div>
                        <div class="col-6 col-xl-2">
                            <div class="border rounded p-2 h-100 history-stat-card stat-count">
                                <div class="text-muted small">Leituras</div>
                                <div class="fw-semibold" id="statReadingsCount">0</div>
                            </div>
                        </div>
                    </div>
                    <canvas id="selected-device-chart" height="120"></canvas>
                    <!-- Gráfico de aberturas de porta -->
                    <div class="mt-2">
                        <div class="text-muted small mb-1"><i class="bi bi-door-open me-1"></i>Aberturas de porta</div>
                        <div style="position:relative; height:70px;">
                            <canvas id="door-openings-chart"></canvas>
                        </div>
                    </div>
                    <?php if (!empty($deviceNotes)): ?>
                    <div class="mt-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-semibold small"><i class="bi bi-journal-text me-1"></i>Notas</span>
                            <span class="badge bg-secondary"><?= count($deviceNotes) ?></span>
                        </div>
                        <div class="table-responsive" style="max-height: <?= count($deviceNotes) > 7 ? '210px' : 'none' ?>; overflow-y: <?= count($deviceNotes) > 7 ? 'auto' : 'visible' ?>;">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th style="width:145px">Data/Hora</th>
                                        <th>Nota</th>
                                        <th style="width:115px">Criado em</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($deviceNotes as $note): ?>
                                    <tr>
                                        <td class="text-nowrap small"><?= htmlspecialchars(date('Y-m-d H:i', strtotime($note['noted_at']))) ?></td>
                                        <td class="small" style="white-space:pre-wrap"><?= htmlspecialchars($note['note_text']) ?></td>
                                        <td class="text-muted small text-nowrap"><?= htmlspecialchars(date('Y-m-d H:i', strtotime($note['created_at']))) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($devicePauses)): ?>
                    <div class="mt-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-semibold small"><i class="bi bi-pause-circle me-1 text-danger"></i>Histórico de pausas</span>
                            <span class="badge bg-danger"><?= count($devicePauses) ?></span>
                        </div>
                        <div class="table-responsive" style="max-height: <?= count($devicePauses) > 7 ? '210px' : 'none' ?>; overflow-y: <?= count($devicePauses) > 7 ? 'auto' : 'visible' ?>;">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th style="width:135px">Pausado em</th>
                                        <th style="width:120px">Pausado por</th>
                                        <th>Motivo</th>
                                        <th style="width:135px">Retomado em</th>
                                        <th style="width:120px">Retomado por</th>
                                        <th style="width:110px">Duração</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($devicePauses as $pause): ?>
                                    <?php
                                    $pausedAt   = $pause['paused_at']  ? date('Y-m-d H:i', strtotime($pause['paused_at']))  : '--';
                                    $resumedAt  = $pause['resumed_at'] ? date('Y-m-d H:i', strtotime($pause['resumed_at'])) : null;
                                    $duration   = '';
                                    if ($pause['paused_at']) {
                                        $end = $pause['resumed_at'] ? strtotime($pause['resumed_at']) : time();
                                        $secs = max(0, $end - strtotime($pause['paused_at']));
                                        $h = floor($secs / 3600);
                                        $m = floor(($secs % 3600) / 60);
                                        $duration = $h > 0 ? "{$h}h {$m}m" : "{$m}m";
                                    }
                                    ?>
                                    <tr class="<?= $resumedAt === null ? 'table-danger' : '' ?>">
                                        <td class="text-nowrap small"><?= $pausedAt ?></td>
                                        <td class="small text-nowrap"><?= htmlspecialchars($pause['paused_by_name'] ?? '—') ?></td>
                                        <td class="small" style="white-space:pre-wrap"><?= htmlspecialchars($pause['reason']) ?></td>
                                        <td class="text-nowrap small">
                                            <?php if ($resumedAt): ?>
                                                <?= $resumedAt ?>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Ativo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-nowrap">
                                            <?php if ($resumedAt): ?>
                                                <?= htmlspecialchars($pause['resumed_by_name'] ?? '—') ?>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-muted"><?= $duration ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
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
    const historyTableWrap = document.querySelector('.history-table-wrap');
    const statMinTemp = document.getElementById('statMinTemp');
    const statMaxTemp = document.getElementById('statMaxTemp');
    const statAvgTemp = document.getElementById('statAvgTemp');
    const statLastTemp = document.getElementById('statLastTemp');
    const statReadingsCount = document.getElementById('statReadingsCount');
    const VISIBLE_HISTORY_ROWS = 25;
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
        adjustHistoryViewport();
    }

    function formatStatTemperature(value) {
        const parsed = Number(value);
        if (!Number.isFinite(parsed)) {
            return '--';
        }

        return `${parsed.toFixed(1)}°C`;
    }

    function resetHistoryStats() {
        if (statMinTemp) statMinTemp.textContent = '--';
        if (statMaxTemp) statMaxTemp.textContent = '--';
        if (statAvgTemp) statAvgTemp.textContent = '--';
        if (statLastTemp) statLastTemp.textContent = '--';
        if (statReadingsCount) statReadingsCount.textContent = '0';
    }

    function renderHistoryStats(chartData) {
        if (!chartData || !Array.isArray(chartData.temperature) || !Array.isArray(chartData.labels)) {
            resetHistoryStats();
            return;
        }

        const temperatures = chartData.temperature;
        const labels = chartData.labels;
        const validTemps = temperatures
            .map(value => Number(value))
            .filter(value => Number.isFinite(value));

        if (!validTemps.length) {
            resetHistoryStats();
            return;
        }

        const minTemp = Math.min(...validTemps);
        const maxTemp = Math.max(...validTemps);
        const avgTemp = validTemps.reduce((acc, value) => acc + value, 0) / validTemps.length;

        let lastTemp = null;
        let lastLabel = null;
        for (let i = temperatures.length - 1; i >= 0; i -= 1) {
            const value = Number(temperatures[i]);
            if (Number.isFinite(value)) {
                lastTemp = value;
                lastLabel = labels[i] || null;
                break;
            }
        }

        if (statMinTemp) statMinTemp.textContent = formatStatTemperature(minTemp);
        if (statMaxTemp) statMaxTemp.textContent = formatStatTemperature(maxTemp);
        if (statAvgTemp) statAvgTemp.textContent = formatStatTemperature(avgTemp);
        if (statLastTemp) {
            const lastText = formatStatTemperature(lastTemp);
            statLastTemp.textContent = lastLabel ? `${lastText} (${formatDateForTable(lastLabel)})` : lastText;
        }
        if (statReadingsCount) statReadingsCount.textContent = String(validTemps.length);
    }

    function adjustHistoryViewport() {
        if (!historyTableWrap || !historyTableBody) {
            return;
        }

        const header = historyTableWrap.querySelector('thead');
        const firstRow = historyTableBody.querySelector('tr');

        if (!header || !firstRow) {
            return;
        }

        const headerHeight = header.getBoundingClientRect().height;
        const rowHeight = firstRow.getBoundingClientRect().height;
        const targetHeight = Math.ceil(headerHeight + (rowHeight * VISIBLE_HISTORY_ROWS) + 2);

        historyTableWrap.style.maxHeight = `${targetHeight}px`;
    }

    let _doorChart = null;

    async function loadDoorChart(period, options = {}) {
        const params = new URLSearchParams();
        params.set('device_id', String(deviceId));
        if (options.from && options.to) {
            params.set('from', options.from);
            params.set('to', options.to);
        } else {
            params.set('period', period);
        }

        let data;
        try {
            const res = await fetch(`${window.BASE_URL || ''}/dashboard/door-chart?${params.toString()}`);
            if (!res.ok) return;
            data = await res.json();
        } catch (e) { return; }

        const canvas = document.getElementById('door-openings-chart');
        if (!canvas) return;

        const labels = (data.labels || []).map(l => {
            const d = new Date(l);
            if (period === '24h') return d.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
            if (period === 'custom' && options.from && options.to) {
                const rangeMs = new Date(options.to) - new Date(options.from);
                if (rangeMs <= 3 * 86400 * 1000) return d.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
                return d.toLocaleDateString([], {month: 'short', day: 'numeric'});
            }
            return d.toLocaleDateString([], {month: 'short', day: 'numeric'});
        });

        if (_doorChart) { _doorChart.destroy(); }

        _doorChart = new Chart(canvas, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Aberturas',
                    data: data.counts || [],
                    backgroundColor: 'rgba(255, 193, 7, 0.6)',
                    borderColor: 'rgba(255, 152, 0, 0.8)',
                    borderWidth: 1,
                    borderRadius: 2,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: 0 },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            title: (ctx) => ctx[0]?.label ?? '',
                            label: (ctx) => `Aberturas: ${ctx.parsed.y}`
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 10 }, maxRotation: 0, autoSkip: true, maxTicksLimit: 12 }
                    },
                    y: {
                        min: 0,
                        ticks: { stepSize: 1, maxTicksLimit: 3, font: { size: 10 } },
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    }
                }
            }
        });
    }

    async function refreshChart() {
        await Promise.all([
            loadChart(deviceId, currentPeriod, null, { canvasId: 'selected-device-chart' }),
            loadDoorChart(currentPeriod),
        ]);

        const historyData = await fetchHistoryData(currentPeriod);
        renderHistoryTable(historyData);
        renderHistoryStats(historyData);
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

        await Promise.all([
            loadChart(deviceId, 'custom', null, {
                canvasId: 'selected-device-chart',
                from: fromInput.value,
                to: toInput.value,
            }),
            loadDoorChart('custom', { from: fromInput.value, to: toInput.value }),
        ]);

        const historyData = await fetchHistoryData('custom');
        renderHistoryTable(historyData);
        renderHistoryStats(historyData);
    });

    const clearBtn = document.getElementById('clearCustomRange');
    clearBtn.addEventListener('click', async function () {
        fromInput.value = '';
        toInput.value = '';
        currentPeriod = '24h';
        periodButtons.querySelectorAll('button').forEach(b => b.classList.remove('active'));
        periodButtons.querySelector('button[data-period="24h"]').classList.add('active');
        await refreshChart();
    });


    // --- Atualização automática do histórico ao receber nova leitura ---
    let lastReadingTimestamp = null;
    async function pollLastReading() {
        try {
            const res = await fetch((window.BASE_URL || '') + '/api/device/last-reading?device_id=' + deviceId, {
                credentials: 'same-origin'
            });
            if (!res.ok) return;
            const data = await res.json();
            if (data && data.timestamp) {
                if (lastReadingTimestamp !== null && lastReadingTimestamp !== data.timestamp) {
                    // Nova leitura detectada, atualizar histórico
                    await refreshChart();
                }
                lastReadingTimestamp = data.timestamp;
            }
        } catch (e) {}
    }
    // Poll a cada 5 segundos
    setInterval(pollLastReading, 5000);
    pollLastReading();

    // --- INICIALIZAÇÃO AUTOMÁTICA ---
        
        // 1. Garantir que o período inicial é 24h e o botão está visualmente ativo
        currentPeriod = '24h';
        periodButtons.querySelectorAll('button').forEach(b => {
            if(b.dataset.period === '24h') b.classList.add('active');
            else b.classList.remove('active');
        });

        // 2. Função para a carga inicial "limpa"
        async function initialLoad() {
            // Pequeno delay para garantir que bibliotecas externas (Chart.js) estão prontas
            setTimeout(async () => {
                await refreshChart();
            }, 100);
        }

        // Executar carga inicial
        initialLoad();

        // Manter o redimensionamento ativo
        window.addEventListener('resize', adjustHistoryViewport);
    
    // Modal para notas no gráfico
    const noteModal = new bootstrap.Modal(document.getElementById('noteModal'));
    const noteText = document.getElementById('noteText');
    const saveNoteBtn = document.getElementById('saveNoteBtn');
    const selectedChart = document.getElementById('selected-device-chart');
    const viewNoteModal = new bootstrap.Modal(document.getElementById('viewNoteModal'));
    let selectedNote = {
        dataIndex: null,
        label: null,
        fullDate: null,
        temperature: null,
        period: null
    };

    function findNoteForIndex(dataIndex) {
        const rawData = _rawData['selected-device-chart'];
        if (!rawData || dataIndex >= rawData.labels.length) return null;
        const fullDate = rawData.labels[dataIndex];
        const notes = _notes['selected-device-chart'] || [];
        return notes.find(n => {
            const noteDate = new Date(n.noted_at);
            const dataDate = new Date(fullDate);
            return noteDate.getFullYear() === dataDate.getFullYear() &&
                   noteDate.getMonth() === dataDate.getMonth() &&
                   noteDate.getDate() === dataDate.getDate() &&
                   noteDate.getHours() === dataDate.getHours() &&
                   noteDate.getMinutes() === dataDate.getMinutes();
        }) || null;
    }

    // Detectar cliques no gráfico
    selectedChart.addEventListener('click', async (event) => {
        const chart = _charts['selected-device-chart'];
        if (!chart) return;

        const canvasPosition = Chart.helpers.getRelativePosition(event, chart);
        const dataX = chart.scales.x.getValueForPixel(canvasPosition.x);
        const dataIndex = Math.round(dataX);

        if (dataIndex >= 0 && dataIndex < chart.data.labels.length) {
            const rawData = _rawData['selected-device-chart'];
            if (!rawData || dataIndex >= rawData.labels.length) return;

            const fullDate = rawData.labels[dataIndex];
            const temperature = rawData.temperatures[dataIndex];
            const dt = new Date(fullDate);
            const existingNote = findNoteForIndex(dataIndex);

            if (existingNote) {
                // Mostrar modal de leitura
                document.getElementById('viewNoteDateTime').textContent = dt.toLocaleString();
                document.getElementById('viewNoteValue').textContent = temperature ? temperature.toFixed(2) + '°C' : '--';
                document.getElementById('viewNoteContent').textContent = existingNote.note_text;
                viewNoteModal.show();
            } else {
                // Mostrar modal de criação
                selectedNote.dataIndex = dataIndex;
                selectedNote.label = chart.data.labels[dataIndex];
                selectedNote.fullDate = fullDate;
                selectedNote.temperature = temperature;
                selectedNote.period = currentPeriod;

                document.getElementById('noteDateTime').textContent = dt.toLocaleString();
                document.getElementById('noteValue').textContent = temperature ? temperature.toFixed(2) + '°C' : '--';
                noteText.value = '';
                noteModal.show();
            }
        }
    });

    saveNoteBtn.addEventListener('click', async () => {
        if (!selectedNote.fullDate || !noteText.value.trim()) {
            alert('Por favor, adicione uma nota');
            return;
        }

        try {
            const response = await fetch(`${window.BASE_URL || ''}/dashboard/save-note`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    device_id: deviceId,
                    noted_at: selectedNote.fullDate,
                    note_text: noteText.value.trim()
                })
            });

            if (!response.ok) {
                alert('Erro ao guardar nota');
                return;
            }

            const data = await response.json();
            if (data.success) {
                noteModal.hide();
                
                // Recarregar gráfico para mostrar notas
                await refreshChart();
                
                // Mostrar mensagem de sucesso
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-success alert-dismissible fade show';
                alertDiv.innerHTML = `Nota guardada com sucesso<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
                document.querySelector('.card.shadow-sm').insertAdjacentElement('beforebegin', alertDiv);
                
                setTimeout(() => alertDiv.remove(), 4000);
            }
        } catch (error) {
            console.error('Erro:', error);
            alert('Erro ao guardar nota');
        }
    });
});
</script>

<!-- Modal para adicionar nota -->
<div class="modal fade" id="noteModal" tabindex="-1" aria-labelledby="noteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="noteModalLabel">Adicionar nota ao gráfico</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <small class="text-muted"><strong>Data/Hora:</strong> <span id="noteDateTime">--</span></small>
                </div>
                <div class="mb-3">
                    <small class="text-muted"><strong>Valor:</strong> <span id="noteValue">--</span></small>
                </div>
                <div class="mb-3">
                    <label for="noteText" class="form-label">Nota</label>
                    <textarea class="form-control" id="noteText" rows="4" placeholder="Adicione uma nota..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="saveNoteBtn">Guardar Nota</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para ler nota existente -->
<div class="modal fade" id="viewNoteModal" tabindex="-1" aria-labelledby="viewNoteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewNoteModalLabel">📝 Nota</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <small class="text-muted"><strong>Data/Hora:</strong> <span id="viewNoteDateTime">--</span></small>
                </div>
                <div class="mb-3">
                    <small class="text-muted"><strong>Valor:</strong> <span id="viewNoteValue">--</span></small>
                </div>
                <div class="border rounded p-3 bg-light">
                    <p class="mb-0" id="viewNoteContent" style="white-space: pre-wrap;"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
