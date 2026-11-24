// Public website behaviour: hero slideshow, achiever tabs, mobile menu, faculty filter.
// Plain JS (no inline handlers — the Content Security Policy blocks them).

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// ---------- Hero slideshow ----------
document.querySelectorAll('[data-hero]').forEach((hero) => {
    const slides = [...hero.querySelectorAll('.slide')];
    const dots = [...hero.querySelectorAll('button.dot')];
    if (slides.length < 2) return;
    let cur = 0;
    let timer = null;
    const show = (i) => {
        cur = (i + slides.length) % slides.length;
        slides.forEach((s, n) => {
            s.classList.toggle('on', n === cur);
            s.setAttribute('aria-hidden', String(n !== cur));
            s.querySelectorAll('a').forEach((a) => { a.tabIndex = n === cur ? 0 : -1; });
        });
        dots.forEach((d, n) => d.setAttribute('aria-current', String(n === cur)));
    };
    const start = () => { if (!reduceMotion) { clearInterval(timer); timer = setInterval(() => show(cur + 1), 6500); } };
    hero.querySelector('[data-prev]')?.addEventListener('click', () => { show(cur - 1); start(); });
    hero.querySelector('[data-next]')?.addEventListener('click', () => { show(cur + 1); start(); });
    dots.forEach((d, n) => d.addEventListener('click', () => { show(n); start(); }));
    hero.addEventListener('mouseenter', () => clearInterval(timer));
    hero.addEventListener('mouseleave', start);
    document.addEventListener('visibilitychange', () => (document.hidden ? clearInterval(timer) : start()));
    show(0);
    start();
});

// ---------- Tabs that filter cards (achievers) ----------
document.querySelectorAll('[data-filter-tabs]').forEach((tabs) => {
    const target = document.querySelector(tabs.dataset.filterTabs);
    if (!target) return;
    tabs.addEventListener('click', (e) => {
        const b = e.target.closest('button[data-group]');
        if (!b) return;
        tabs.querySelectorAll('button').forEach((x) => x.setAttribute('aria-selected', String(x === b)));
        const g = b.dataset.group;
        target.querySelectorAll('[data-group]').forEach((card) => { card.hidden = g !== 'All' && card.dataset.group !== g; });
    });
});

// ---------- Mobile menu ----------
const menu = document.getElementById('mmenu');
document.querySelectorAll('[data-menu-open]').forEach((b) => b.addEventListener('click', () => {
    menu.hidden = false;
    document.body.style.overflow = 'hidden';
    menu.querySelector('[data-menu-close]')?.focus();
}));
menu?.querySelectorAll('[data-menu-close]').forEach((b) => b.addEventListener('click', () => {
    menu.hidden = true;
    document.body.style.overflow = '';
}));
document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && menu && !menu.hidden) { menu.hidden = true; document.body.style.overflow = ''; } });

// ---------- Faculty directory filter ----------
const fq = document.getElementById('faculty-q');
const fd = document.getElementById('faculty-dept');
if (fq && fd) {
    const count = document.getElementById('faculty-count');
    const all = document.querySelectorAll('[data-person]').length;
    const apply = () => {
        const q = fq.value.trim().toLowerCase();
        const d = fd.value;
        let shown = 0;
        document.querySelectorAll('.dept').forEach((dept) => {
            let n = 0;
            dept.querySelectorAll('[data-person]').forEach((p) => {
                const ok = (!d || dept.dataset.dept === d) && (!q || p.dataset.person.includes(q));
                p.hidden = !ok;
                if (ok) n++;
            });
            dept.hidden = n === 0;
            shown += n;
        });
        count.textContent = shown === all ? `Showing all ${all} teachers, senior faculty first in each department.` : `Showing ${shown} of ${all} teachers.`;
        document.getElementById('faculty-empty').hidden = shown > 0;
    };
    fq.addEventListener('input', apply);
    fd.addEventListener('change', apply);
}
