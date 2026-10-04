import { Chart } from 'chart.js';

const BRAND = '#157e76';

const money = (currency) => (v) => `${currency}${Number(v).toLocaleString(undefined, { maximumFractionDigits: 0 })}`;

/**
 * Chart components read their data from a JSON attribute so no inline
 * scripts are needed: <canvas x-data="lineChart" data-chart='{...}'>
 */
function base(build) {
    return () => ({
        chart: null,
        init() {
            const config = JSON.parse(this.$el.dataset.chart || '{}');
            this.chart = new Chart(this.$el, build(config, this.$el));
        },
        destroy() {
            this.chart?.destroy();
        },
    });
}

export const lineChart = base((cfg, el) => {
    const ctx = el.getContext('2d');
    const gradient = ctx.createLinearGradient(0, 0, 0, el.height || 260);
    gradient.addColorStop(0, 'rgba(21,126,118,0.22)');
    gradient.addColorStop(1, 'rgba(21,126,118,0)');

    return {
        type: 'line',
        data: {
            labels: cfg.labels,
            datasets: [{
                label: cfg.label || 'Sales',
                data: cfg.values,
                borderColor: BRAND,
                backgroundColor: gradient,
                fill: true,
                cubicInterpolationMode: 'monotone',
                borderWidth: 2,
                pointRadius: 0,
                pointHoverRadius: 4,
                pointBackgroundColor: BRAND,
            }],
        },
        options: {
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: (c) => ' ' + money(cfg.currency || '')(c.parsed.y) } },
            },
            scales: {
                x: { grid: { display: false }, ticks: { maxTicksLimit: 8, autoSkip: true } },
                y: { beginAtZero: true, border: { display: false }, grid: { color: '#eef2f4' }, ticks: { callback: money(cfg.currency || ''), maxTicksLimit: 5 } },
            },
        },
    };
});

export const barChart = base((cfg) => ({
    type: 'bar',
    data: {
        labels: cfg.labels,
        datasets: [{ label: cfg.label || 'Total', data: cfg.values, backgroundColor: BRAND, borderRadius: 4, borderSkipped: 'start', maxBarThickness: 36 }],
    },
    options: {
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: (c) => ' ' + money(cfg.currency || '')(c.parsed.y) } },
        },
        scales: {
            x: { grid: { display: false } },
            y: { beginAtZero: true, border: { display: false }, grid: { color: '#eef2f4' }, ticks: { callback: money(cfg.currency || ''), maxTicksLimit: 5 } },
        },
    },
}));
