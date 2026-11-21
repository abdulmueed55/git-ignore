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

// Alerts bell: polls the feed every minute; new class reminders pop up as a toast and,
// when allowed, as a desktop notification (even while another tab is open).
Alpine.data('bell', (feedUrl, readUrl) => ({
    open: false, items: [], unread: 0, toasts: [],
    perm: 'Notification' in window ? Notification.permission : 'unsupported',
    init() {
        this.load();
        setInterval(() => this.load(), 60000);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) this.load(); });
    },
    seen() { try { return new Set(JSON.parse(sessionStorage.getItem('bell-seen') || '[]')); } catch (e) { return new Set(); } },
    remember(ids) { try { sessionStorage.setItem('bell-seen', JSON.stringify([...ids].slice(-60))); } catch (e) {} },
    async load() {
        try {
            const res = await fetch(feedUrl, { headers: { Accept: 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            this.items = data.items; this.unread = data.unread;
            const seen = this.seen();
            data.items.filter((n) => !n.read && n.fresh && !seen.has(n.id)).reverse().forEach((n) => {
                seen.add(n.id);
                this.toasts.push(n);
                setTimeout(() => this.dismiss(n.id), 20000);
                if (this.perm === 'granted' && document.hidden) {
                    try { const d = new Notification(n.title, { body: n.body, tag: n.id }); d.onclick = () => { window.focus(); if (n.url) location.href = n.url; }; } catch (e) {}
                }
            });
            this.remember(seen);
        } catch (e) { /* offline: try again next minute */ }
    },
    toggle() { this.open = !this.open; if (this.open) this.load(); },
    dismiss(id) { this.toasts = this.toasts.filter((t) => t.id !== id); },
    async post(body) {
        const res = await fetch(readUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify(body) });
        if (res.ok) this.unread = (await res.json()).unread;
    },
    async markRead(id) { this.items.forEach((n) => { if (n.id === id) n.read = true; }); try { await this.post({ id }); } catch (e) {} },
    async follow(n) { await this.markRead(n.id); if (n.url) location.href = n.url; },
    async readAll() { this.items.forEach((n) => { n.read = true; }); try { await this.post({}); } catch (e) {} },
    askPermission() { Notification.requestPermission().then((p) => { this.perm = p; }); },
}));

// Whitelist sanitizer for editor previews (x-html would otherwise run pasted <script>,
// onerror= or Alpine x-init attributes in the editor's session). Mirrors the server-side
// HtmlSanitizer: only plain formatting tags, links and images survive.
const SAFE_TAGS = new Set(['P', 'BR', 'STRONG', 'B', 'EM', 'I', 'U', 'S', 'H2', 'H3', 'H4', 'UL', 'OL', 'LI', 'A', 'BLOCKQUOTE',
    'TABLE', 'THEAD', 'TBODY', 'TR', 'TH', 'TD', 'IMG', 'HR', 'FIGURE', 'FIGCAPTION', 'SPAN', 'CODE', 'PRE', 'SUB', 'SUP']);
const DROP_TAGS = new Set(['SCRIPT', 'STYLE', 'IFRAME', 'OBJECT', 'EMBED', 'FORM', 'SVG', 'MATH', 'TEMPLATE', 'NOSCRIPT',
    'TEXTAREA', 'SELECT', 'INPUT', 'BUTTON', 'LINK', 'META', 'BASE', 'FRAME', 'FRAMESET', 'XMP', 'NOEMBED', 'TITLE']);
const SAFE_ATTRS = { A: ['href', 'title'], IMG: ['src', 'alt', 'title', 'width', 'height'], TD: ['colspan', 'rowspan'], TH: ['colspan', 'rowspan'] };
const safeUrl = (v, img) => {
    const u = String(v || '').replace(/[\u0000-\u0020\u007f-\u00a0\u200b-\u200f\u2028\u2029\ufeff]/g, '');
    return /^(https?:|mailto:|tel:|\/(?!\/)|#)/i.test(u) || (img && /^data:image\/(png|jpe?g|gif|webp);/i.test(u));
};
window.safeHtml = (html) => {
    const doc = new DOMParser().parseFromString(`<body>${html || ''}</body>`, 'text/html');
    const clean = (node) => {
        [...node.childNodes].forEach((child) => {
            if (child.nodeType === Node.COMMENT_NODE) { child.remove(); return; }
            if (child.nodeType !== Node.ELEMENT_NODE) return;
            const tag = child.tagName.toUpperCase();
            if (DROP_TAGS.has(tag)) { child.remove(); return; }
            clean(child);
            if (!SAFE_TAGS.has(tag)) { child.replaceWith(...child.childNodes); return; }
            [...child.attributes].forEach((a) => {
                const ok = (SAFE_ATTRS[tag] || []).includes(a.name.toLowerCase())
                    && (!['href', 'src'].includes(a.name.toLowerCase()) || safeUrl(a.value, tag === 'IMG'));
                if (!ok) child.removeAttribute(a.name);
            });
            if (tag === 'A') { child.setAttribute('rel', 'noopener nofollow'); child.setAttribute('target', '_blank'); }
        });
    };
    clean(doc.body);
    return doc.body.innerHTML;
};

// CSP-safe replacements for inline handlers (the Content Security Policy blocks onclick= etc.):
//   data-print            → print the page
//   data-autosubmit       → submit the form when the field changes
//   data-confirm="…"      → ask before a click / form submit goes ahead
document.addEventListener('click', (e) => {
    const confirmEl = e.target.closest('[data-confirm]:not(form)');
    if (confirmEl && !window.confirm(confirmEl.dataset.confirm)) { e.preventDefault(); e.stopImmediatePropagation(); return; }
    if (e.target.closest('[data-print]')) window.print();
}, true);
document.addEventListener('submit', (e) => {
    const form = e.target;
    if (form.matches('[data-confirm]') && !window.confirm(form.dataset.confirm)) e.preventDefault();
}, true);
document.addEventListener('change', (e) => {
    if (e.target.matches('[data-autosubmit]')) e.target.form?.requestSubmit();
});

window.Alpine = Alpine;
Alpine.start();
