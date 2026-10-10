import Chart from 'chart.js/auto';

// Palette validated with the dataviz validator on the light surface (categorical slots 1 and 2).
const SERIES_1 = '#2a78d6';
const SERIES_2 = '#eb6834';
const TEXT = '#44403c';
const GRID = '#e7e5e4';

Chart.defaults.font.family = "'Be Vietnam Pro', ui-sans-serif, system-ui, sans-serif";
Chart.defaults.color = TEXT;

const read = (id) => JSON.parse(document.getElementById(id).textContent);
const kg = (value) => `${new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 1 }).format(value)} kg`;
const vnd = (value) => `${new Intl.NumberFormat('vi-VN').format(value)}đ`;

const axes = (formatTick) => ({
    x: { grid: { display: false }, border: { color: GRID } },
    y: {
        beginAtZero: true,
        grid: { color: GRID, lineWidth: 1 },
        border: { display: false },
        ticks: { callback: formatTick, maxTicksLimit: 5 },
    },
});

const wasteCanvas = document.getElementById('chart-waste');
if (wasteCanvas) {
    const data = read('chart-waste-data');
    const bar = { maxBarThickness: 24, borderRadius: { topLeft: 4, topRight: 4 }, borderSkipped: 'bottom' };

    new Chart(wasteCanvas, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [
                { label: 'Nhận từ nông hộ', data: data.received, backgroundColor: SERIES_1, ...bar },
                { label: 'Rau dư', data: data.waste, backgroundColor: SERIES_2, ...bar },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            categoryPercentage: 0.6,
            barPercentage: 0.9,
            scales: axes((value) => kg(value)),
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, boxHeight: 12 } },
                tooltip: { callbacks: { label: (item) => `${item.dataset.label}: ${kg(item.parsed.y)}` } },
            },
        },
    });
}

const forecastCanvas = document.getElementById('chart-forecast');
if (forecastCanvas) {
    const data = read('chart-forecast-data');
    const line = { borderWidth: 2, pointRadius: 0, pointHoverRadius: 5, pointHitRadius: 16, tension: 0, spanGaps: false };

    new Chart(forecastCanvas, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [
                { label: 'Đã bán thật', data: data.actual, borderColor: SERIES_1, backgroundColor: SERIES_1, ...line },
                { label: 'Máy đoán', data: data.forecast, borderColor: SERIES_2, backgroundColor: SERIES_2, borderDash: [6, 4], ...line },
                // Band: upper line filled down to the lower line; both lines hidden from the legend.
                { label: 'Ít nhất', data: data.lower, borderWidth: 0, pointRadius: 0, pointHitRadius: 0 },
                { label: 'Nhiều nhất', data: data.upper, borderWidth: 0, pointRadius: 0, pointHitRadius: 0, fill: '-1', backgroundColor: 'rgba(235, 104, 52, 0.15)' },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: { ...axes((value) => kg(value)), x: { grid: { display: false }, border: { color: GRID }, ticks: { maxTicksLimit: 8 } } },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, boxHeight: 12, filter: (item) => item.datasetIndex < 2 },
                },
                tooltip: {
                    filter: (item) => item.parsed.y !== null,
                    callbacks: { label: (item) => `${item.dataset.label}: ${kg(item.parsed.y)}` },
                },
            },
        },
    });
}

const revenueCanvas = document.getElementById('chart-revenue');
if (revenueCanvas) {
    const data = read('chart-revenue-data');

    new Chart(revenueCanvas, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Doanh thu',
                data: data.values,
                borderColor: SERIES_1,
                backgroundColor: SERIES_1,
                borderWidth: 2,
                pointRadius: 0,
                pointHoverRadius: 5,
                pointHitRadius: 16,
                tension: 0,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: axes((value) => vnd(value)),
            plugins: {
                // Single series: the section title names it, so no legend box.
                legend: { display: false },
                tooltip: { callbacks: { label: (item) => `Doanh thu: ${vnd(item.parsed.y)}` } },
            },
        },
    });
}
