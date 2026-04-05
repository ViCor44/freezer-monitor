/**
 * chart-helper.js
 * Manages Chart.js temperature charts per device.
 */

const _charts = {};
const _notes = {}; // Armazenar notas por canvas
const _rawData = {}; // Armazenar dados brutos (labels e temperatures) por canvas

/**
 * Load chart data for a device and period.
 * @param {number} deviceId
 * @param {string} period  '24h' | '7d' | '30d'
 * @param {HTMLElement|null} btn  - active button element (optional)
 * @param {{canvasId?: string, from?: string, to?: string, onData?: function}} options
 */
async function loadChart(deviceId, period, btn, options = {}) {
    // Update active button in the group
    if (btn) {
        const group = btn.closest('.btn-group');
        if (group) {
            group.querySelectorAll('.btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
        }
    }

    try {
        const params = new URLSearchParams();
        params.set('device_id', String(deviceId));

        if (options.from && options.to) {
            params.set('from', options.from);
            params.set('to', options.to);
        } else {
            params.set('period', period);
        }

        const res = await fetch(`${window.BASE_URL || ''}/dashboard/chart?${params.toString()}`);
        if (!res.ok) return;
        const data = await res.json();

        if (typeof options.onData === 'function') {
            options.onData(data);
        }

        const canvasId = options.canvasId || `chart-${deviceId}`;
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;

        // Carregar notas para o período
        let notes = [];
        const fromDate = options.from ? new Date(options.from) : getDateRange(period).from;
        const toDate = options.to ? new Date(options.to) : getDateRange(period).to;
        
        if (options.from && options.to) {
            notes = await fetchNotes(deviceId, options.from, options.to);
        } else {
            notes = await fetchNotes(deviceId, fromDate.toISOString(), toDate.toISOString());
        }
        _notes[canvasId] = notes || [];

        // Armazenar dados brutos para acesso posterior (e.g., ao clicar no gráfico)
        _rawData[canvasId] = {
            labels: data.labels,
            temperatures: data.temperature
        };

        // Format labels
        const labels = data.labels.map(l => {
            const d = new Date(l);
            if (period === '24h') return d.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
            if (period === 'custom') {
                return d.toLocaleString([], {month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'});
            }
            return d.toLocaleDateString([], {month: 'short', day: 'numeric'});
        });

        // Destroy existing chart
        if (_charts[canvasId]) {
            _charts[canvasId].destroy();
        }

        // Criar dataset para notas
        const notesData = data.labels.map(label => {
            const hasNote = _notes[canvasId].some(note => 
                new Date(note.noted_at).toISOString().split('T')[0] === new Date(label).toISOString().split('T')[0]
            );
            return hasNote ? {x: label, y: null} : null;
        });

        _charts[canvasId] = new Chart(canvas, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Temperatura (°C)',
                        data: data.temperature,
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13,110,253,0.08)',
                        tension: 0.3,
                        fill: true,
                        pointRadius: period === '24h' ? 3 : 2,
                    },
                    {
                        label: 'Notas',
                        data: notesData,
                        type: 'scatter',
                        pointRadius: 6,
                        pointBackgroundColor: '#ffc107',
                        pointBorderColor: '#ff9800',
                        pointBorderWidth: 2,
                        showLine: false
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top' },
                    tooltip: {
                        callbacks: {
                            afterTitle: function(context) {
                                const dataIndex = context[0].dataIndex;
                                const note = _notes[canvasId].find(n => {
                                    const noteDate = new Date(n.noted_at);
                                    const dataDate = new Date(data.labels[dataIndex]);
                                    return noteDate.getTime() === dataDate.getTime();
                                });
                                if (note) {
                                    return 'Nota: ' + note.note_text;
                                }
                                return '';
                            }
                        }
                    },
                    annotation: {
                        annotations: {
                            maxLine: {
                                type: 'line',
                                yMin: data.temp_max,
                                yMax: data.temp_max,
                                borderColor: 'rgba(220,53,69,0.6)',
                                borderWidth: 1,
                                borderDash: [6,3],
                                label: { content: `Max ${data.temp_max}°C`, display: true, position: 'end' }
                            },
                            minLine: {
                                type: 'line',
                                yMin: data.temp_min,
                                yMax: data.temp_min,
                                borderColor: 'rgba(13,202,240,0.6)',
                                borderWidth: 1,
                                borderDash: [6,3],
                                label: { content: `Min ${data.temp_min}°C`, display: true, position: 'end' }
                            }
                        }
                    }
                },
                scales: {
                    y: { title: { display: true, text: '°C' } }
                }
            }
        });

        return data;
    } catch (e) {
        console.warn('Erro ao carregar grafico:', e);
    }
}

/**
 * Buscar notas para um período específico
 */
async function fetchNotes(deviceId, from, to) {
    try {
        const params = new URLSearchParams();
        params.set('device_id', String(deviceId));
        params.set('from', from);
        params.set('to', to);

        const res = await fetch(`${window.BASE_URL || ''}/dashboard/get-notes?${params.toString()}`);
        if (!res.ok) return [];
        
        const data = await res.json();
        return data.notes || [];
    } catch (e) {
        console.warn('Erro ao carregar notas:', e);
        return [];
    }
}

/**
 * Obter intervalo de datas baseado no período
 */
function getDateRange(period) {
    const now = new Date();
    const from = new Date();

    switch (period) {
        case '7d':
            from.setDate(now.getDate() - 7);
            break;
        case '30d':
            from.setDate(now.getDate() - 30);
            break;
        case '24h':
        default:
            from.setHours(now.getHours() - 24);
            break;
    }

    return { from, to: now };
}
