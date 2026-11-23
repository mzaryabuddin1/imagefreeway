/* Modern dashboard charts (ApexCharts, loaded only on pages that have a chart). */
const PALETTE = ['#0ea5e9', '#f59e0b', '#8b5cf6', '#10b981', '#ef4444', '#ec4899', '#64748b'];

const brand = () => getComputedStyle(document.documentElement).getPropertyValue('--brand').trim() || '#6aa31a';
const dark = () => document.documentElement.classList.contains('dark');

const base = (c, height) => ({
    chart: { height, fontFamily: 'inherit', toolbar: { show: false }, animations: { enabled: true, easing: 'easeinout', speed: 650 }, foreColor: '#64748b', background: 'transparent' },
    colors: c,
    grid: { borderColor: '#e2e8f0', strokeDashArray: 4, padding: { left: 6, right: 6 } },
    dataLabels: { enabled: false },
    tooltip: { theme: dark() ? 'dark' : 'light' },
    states: { hover: { filter: { type: 'lighten', value: 0.04 } } },
});

function build(spec, color) {
    const labels = spec.labels;
    const series = spec.values;
    const colors = [color, ...PALETTE];
    const money = spec.prefix || '';
    const fmt = (v) => money + Number(v).toLocaleString(undefined, { maximumFractionDigits: 2 });

    switch (spec.type) {
        case 'area':
            return {
                ...base([color], 260),
                chart: { ...base([color], 260).chart, type: 'area', zoom: { enabled: false } },
                series: [{ name: spec.name || 'Documents', data: series }],
                xaxis: { type: 'category', categories: labels, tickAmount: 8, labels: { rotate: 0, hideOverlappingLabels: true }, axisBorder: { show: false }, axisTicks: { show: false }, tooltip: { enabled: false } },
                yaxis: { min: 0, forceNiceScale: true, labels: { formatter: (v) => Math.round(v) } },
                stroke: { curve: 'smooth', width: 3 },
                fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 1, opacityTo: 0.1, stops: [0, 95, 100] } },
                markers: { size: 0, hover: { size: 5 } },
            };
        case 'donut':
            return {
                ...base(colors, 330),
                chart: { ...base(colors, 330).chart, type: 'donut' },
                series, labels,
                stroke: { width: 3, colors: ['#fff'] },
                legend: { position: 'bottom', fontSize: '13px', markers: { size: 6, shape: 'circle' }, itemMargin: { horizontal: 8, vertical: 3 } },
                plotOptions: { pie: { donut: { size: '72%', labels: { show: true, name: { show: true, fontSize: '12px', offsetY: 18 }, value: { show: true, fontSize: '26px', fontWeight: 700, color: '#0f172a', offsetY: -14, formatter: (v) => Number(v).toLocaleString() }, total: { show: true, showAlways: true, label: spec.center || 'Total', fontSize: '12px', color: '#64748b', formatter: (w) => w.globals.seriesTotals.reduce((a, b) => a + b, 0).toLocaleString() } } } } },
                tooltip: { ...base(colors).tooltip, y: { formatter: (v) => Number(v).toLocaleString() } },
            };
        case 'bar': // horizontal
            return {
                ...base([color], Math.max(150, labels.length * 44 + 30)),
                chart: { ...base([color], Math.max(150, labels.length * 44 + 30)).chart, type: 'bar' },
                series: [{ name: spec.name || 'Documents', data: series }],
                plotOptions: { bar: { horizontal: true, borderRadius: 6, borderRadiusApplication: 'end', barHeight: '58%' } },
                xaxis: { categories: labels.map((l) => (l.length > 17 ? l.slice(0, 16) + '…' : l)), labels: { show: false }, axisBorder: { show: false }, axisTicks: { show: false } },
                yaxis: { labels: { maxWidth: 130, align: 'left', offsetX: -2, style: { fontSize: '13px', fontWeight: 500, colors: '#334155' } } },
                dataLabels: { enabled: true, textAnchor: 'start', offsetX: 4, style: { fontSize: '12px', fontWeight: 600, colors: ['#334155'] }, formatter: fmt },
                grid: { show: false, padding: { left: 0, right: 34 } },
                tooltip: { ...base([color]).tooltip, x: { formatter: (_, o) => labels[o.dataPointIndex] }, y: { formatter: fmt } },
            };
        case 'column':
            return {
                ...base([color], 260),
                chart: { ...base([color], 260).chart, type: 'bar' },
                series: [{ name: spec.name || 'Amount', data: series }],
                plotOptions: { bar: { borderRadius: 7, columnWidth: '42%' } },
                xaxis: { type: 'category', categories: labels, axisBorder: { show: false }, axisTicks: { show: false } },
                yaxis: { labels: { formatter: (v) => money + (v >= 1000 ? Math.round(v / 1000) + 'k' : Math.round(v)) } },
                fill: { type: 'gradient', gradient: { shade: 'light', type: 'vertical', shadeIntensity: 0.2, opacityFrom: 1, opacityTo: 0.72 } },
                tooltip: { ...base([color]).tooltip, y: { formatter: fmt } },
            };
    }

    return null;
}

export async function initCharts() {
    const nodes = [...document.querySelectorAll('[data-chart]:not([data-ready])')];
    if (!nodes.length) return;
    const { default: ApexCharts } = await import('apexcharts');
    const color = brand();
    nodes.forEach((el) => {
        el.dataset.ready = '1';
        try {
            const opts = build(JSON.parse(el.dataset.chart), color);
            if (opts) new ApexCharts(el, opts).render();
        } catch (e) {
            el.textContent = 'This chart could not be drawn.';
            console.error(e);
        }
    });
}
