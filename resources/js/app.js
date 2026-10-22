import Chart from 'chart.js/auto';

/**
 * Charts are declared in Blade through data attributes on each <canvas>, so the
 * page markup stays free of inline JavaScript and every page shares one
 * rendering path.
 */
const PALETTE = ['#4f46e5', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'];

function readJson(canvas, key) {
    const raw = canvas.dataset[key];

    return raw ? JSON.parse(raw) : null;
}

function baseOptions(extra = {}) {
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: { padding: 10 },
        },
        ...extra,
    };
}

function render() {
    // Grouped bar: churned vs retained per month.
    const trend = document.querySelector('[data-chart="churn-trend"]');

    if (trend) {
        const rows = readJson(trend, 'rows') ?? [];

        new Chart(trend, {
            type: 'bar',
            data: {
                labels: rows.map((r) => r.month),
                datasets: [
                    { label: 'Churned', data: rows.map((r) => r.churned), backgroundColor: PALETTE[4] },
                    { label: 'Retained', data: rows.map((r) => r.retained), backgroundColor: PALETTE[2] },
                ],
            },
            options: baseOptions({
                plugins: {
                    legend: { display: true, position: 'bottom' },
                    tooltip: { padding: 10 },
                },
                scales: { y: { beginAtZero: true, grid: { color: '#e2e8f0' } }, x: { grid: { display: false } } },
            }),
        });
    }

    // Doughnut: risk band distribution.
    const risk = document.querySelector('[data-chart="risk-dist"]');

    if (risk) {
        const rows = readJson(risk, 'rows') ?? [];

        new Chart(risk, {
            type: 'doughnut',
            data: {
                labels: rows.map((r) => `${r.level} (${r.count})`),
                datasets: [{
                    data: rows.map((r) => r.count),
                    backgroundColor: rows.map((r) => r.color),
                }],
            },
            options: baseOptions({
                cutout: '62%',
                plugins: {
                    legend: { display: true, position: 'bottom' },
                    tooltip: { padding: 10 },
                },
            }),
        });
    }

    // Horizontal bar: categorical breakdowns with a single measure.
    document.querySelectorAll('[data-chart="bar-h"]').forEach((canvas) => {
        const rows = readJson(canvas, 'rows') ?? [];
        const valueKey = canvas.dataset.valueKey ?? 'count';

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: rows.map((r) => r.label),
                datasets: [{
                    label: canvas.dataset.seriesLabel ?? 'Count',
                    data: rows.map((r) => Number(r[valueKey] ?? 0)),
                    backgroundColor: canvas.dataset.color ?? PALETTE[0],
                }],
            },
            options: baseOptions({
                indexAxis: 'y',
                scales: {
                    x: { beginAtZero: true, grid: { color: '#e2e8f0' } },
                    y: { grid: { display: false } },
                },
            }),
        });
    });

    // Vertical bar: categorical breakdowns.
    document.querySelectorAll('[data-chart="bar-v"]').forEach((canvas) => {
        const rows = readJson(canvas, 'rows') ?? [];
        const valueKey = canvas.dataset.valueKey ?? 'count';

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: rows.map((r) => r.label),
                datasets: [{
                    label: canvas.dataset.seriesLabel ?? 'Count',
                    data: rows.map((r) => Number(r[valueKey] ?? 0)),
                    backgroundColor: canvas.dataset.color ?? PALETTE[0],
                }],
            },
            options: baseOptions({
                scales: { y: { beginAtZero: true, grid: { color: '#e2e8f0' } }, x: { grid: { display: false } } },
            }),
        });
    });

    // Line: monthly usage series for a single customer.
    const usage = document.querySelector('[data-chart="usage-history"]');

    if (usage) {
        const rows = readJson(usage, 'rows') ?? [];

        new Chart(usage, {
            type: 'line',
            data: {
                labels: rows.map((r) => r.month),
                datasets: [
                    { label: 'Data (GB)', data: rows.map((r) => r.data_usage_gb), borderColor: PALETTE[0], tension: 0.3, fill: true, backgroundColor: 'rgba(79,70,229,.12)' },
                    { label: 'Active days', data: rows.map((r) => r.active_days), borderColor: PALETTE[1], tension: 0.3, yAxisID: 'y2' },
                ],
            },
            options: baseOptions({
                plugins: { legend: { display: true, position: 'bottom' }, tooltip: { padding: 10 } },
                scales: {
                    y: { beginAtZero: true, position: 'left', grid: { color: '#e2e8f0' } },
                    y2: { beginAtZero: true, position: 'right', grid: { display: false }, max: 31 },
                    x: { grid: { display: false } },
                },
            }),
        });
    }
}

document.addEventListener('DOMContentLoaded', render);
