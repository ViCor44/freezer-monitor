/**
 * chart-helper.js
 * Manages Chart.js temperature charts per device.
 */

const _charts = {};

/**
 * Load chart data for a device and period.
 * @param {number} deviceId
 * @param {string} period  '24h' | '7d' | '30d'
 * @param {HTMLElement|null} btn  - active button element (optional)
 */
async function loadChart(deviceId, period, btn) {
    // Update active button in the group
    if (btn) {
        const group = btn.closest('.btn-group');
        if (group) {
            group.querySelectorAll('.btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
        }
    }

    try {
        const res  = await fetch(`${window.BASE_URL || ''}/dashboard/chart?device_id=${deviceId}&period=${period}`);
        if (!res.ok) return;
        const data = await res.json();

        const canvas = document.getElementById(`chart-${deviceId}`);
        if (!canvas) return;

        // Format labels
        const labels = data.labels.map(l => {
            const d = new Date(l);
            if (period === '24h') return d.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
            return d.toLocaleDateString([], {month: 'short', day: 'numeric'});
        });

        // Destroy existing chart
        if (_charts[deviceId]) {
            _charts[deviceId].destroy();
        }

        _charts[deviceId] = new Chart(canvas, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Temperature (°C)',
                        data: data.temperature,
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13,110,253,0.08)',
                        tension: 0.3,
                        fill: true,
                        pointRadius: period === '24h' ? 3 : 4,
                    },
                    {
                        label: 'Humidity (%)',
                        data: data.humidity,
                        borderColor: '#20c997',
                        backgroundColor: 'rgba(32,201,151,0.08)',
                        tension: 0.3,
                        fill: false,
                        hidden: true,
                        pointRadius: 3,
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top' },
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
    } catch (e) {
        console.warn('Chart load error:', e);
    }
}
