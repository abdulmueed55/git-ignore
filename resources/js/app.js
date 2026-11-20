import Alpine from 'alpinejs';
import {
    Chart,
    LineController,
    BarController,
    DoughnutController,
    LineElement,
    BarElement,
    PointElement,
    ArcElement,
    CategoryScale,
    LinearScale,
    Filler,
    Tooltip,
} from 'chart.js';

Chart.register(LineController, BarController, DoughnutController, LineElement, BarElement, PointElement, ArcElement, CategoryScale, LinearScale, Filler, Tooltip);

const css = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();

/** Theme: stored per browser, defaults to OS preference. */
const storedTheme = (() => {
    try {
        return localStorage.getItem('theme');
    } catch {
        return null;
    }
})();

window.setTheme = (theme) => {
    document.documentElement.dataset.theme = theme;
    try {
        localStorage.setItem('theme', theme);
    } catch {}
    window.dispatchEvent(new CustomEvent('theme-changed'));
};

/**
 * <canvas x-data="chart({type:'line', labels:[...], series:[{label, data, tone}] })">
 * Tones map to design tokens so charts follow light/dark theme.
 */
const tones = { brand: '--brand', brass: '--brass', ok: '--ok', warn: '--warn', bad: '--bad', info: '--info', muted: '--muted' };

Alpine.data('chart', (config) => ({
    instance: null,
    init() {
        this.render();
        window.addEventListener('theme-changed', () => this.render());
    },
    render() {
        this.instance?.destroy();
        const ink = css('--muted');
        const line = css('--line');
        const datasets = config.series.map((s) => {
            const color = css(tones[s.tone ?? 'brand'] ?? '--brand');
            const base = { label: s.label, data: s.data, borderColor: color, backgroundColor: color };
            if (config.type === 'line') {
                return {
                    ...base,
                    tension: 0.35,
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    fill: s.fill ?? true,
                    backgroundColor: (ctx) => {
                        const { chart } = ctx;
                        if (!chart.chartArea) return 'transparent';
                        const g = chart.ctx.createLinearGradient(0, chart.chartArea.top, 0, chart.chartArea.bottom);
                        g.addColorStop(0, color + '38');
                        g.addColorStop(1, color + '00');
                        return g;
                    },
                    borderDash: s.dashed ? [4, 4] : [],
                };
            }
            if (config.type === 'doughnut') {
                return { ...base, backgroundColor: s.tones.map((t) => css(tones[t])), borderWidth: 0, cutout: '78%' };
            }
            return { ...base, borderRadius: 4, maxBarThickness: s.thickness ?? 22, backgroundColor: s.colors ? s.colors.map((t) => css(tones[t])) : color };
        });
        this.instance = new Chart(this.$el, {
            type: config.type,
            data: { labels: config.labels, datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 500 },
                indexAxis: config.horizontal ? 'y' : 'x',
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: css('--ink'),
                        titleColor: css('--paper'),
                        bodyColor: css('--paper'),
                        padding: 10,
                        cornerRadius: 8,
                        displayColors: config.series.length > 1,
                        callbacks: config.suffix ? { label: (c) => ` ${c.dataset.label}: ${c.formattedValue}${config.suffix}` } : {},
                    },
                },
                scales:
                    config.type === 'doughnut'
                        ? {}
                        : {
                              x: { grid: { display: false }, border: { color: line }, ticks: { color: ink, font: { size: 11 }, maxRotation: 0, autoSkipPadding: 12 }, stacked: !!config.stacked },
                              y: {
                                  grid: { color: line, drawTicks: false },
                                  border: { display: false },
                                  ticks: { color: ink, font: { size: 11 }, padding: 8, callback: (v) => v + (config.suffix ?? '') },
                                  suggestedMin: config.min,
                                  suggestedMax: config.max,
                                  stacked: !!config.stacked,
                              },
                          },
            },
        });
    },
}));

/** Command palette (Ctrl/⌘ + K): jump anywhere. Items come from the layout. */
Alpine.data('palette', (items) => ({
    open: false,
    q: '',
    active: 0,
    get results() {
        const q = this.q.toLowerCase().trim();
        const list = q ? items.filter((i) => (i.label + ' ' + (i.group ?? '') + ' ' + (i.keywords ?? '')).toLowerCase().includes(q)) : items;
        return list.slice(0, 9);
    },
    show() {
        this.open = true;
        this.q = '';
        this.active = 0;
        this.$nextTick(() => this.$refs.q?.focus());
    },
    go(item) {
        if (item) window.location.href = item.url;
    },
    key(e) {
        if (e.key === 'ArrowDown') { this.active = Math.min(this.active + 1, this.results.length - 1); e.preventDefault(); }
        if (e.key === 'ArrowUp') { this.active = Math.max(this.active - 1, 0); e.preventDefault(); }
        if (e.key === 'Enter') { this.go(this.results[this.active]); }
    },
}));

window.Alpine = Alpine;
Alpine.start();
