/* The flag walkthrough: an overview tour of the system plus task guides, each shown step by step on the real pages. */
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const norm = (p) => (p || '/').replace(/\/$/, '') || '/';

export function initTour(refreshIcons) {
    const root = document.getElementById('tour-root');
    if (!root) return;
    let overview;
    let guides;
    try { overview = JSON.parse(root.dataset.steps || '[]'); } catch { overview = []; }
    try { guides = JSON.parse(root.dataset.guides || '[]'); } catch { guides = []; }
    if (!overview.length) return;

    const key = `ifw_tour_${root.dataset.key}`;
    const load = () => {
        let s = {};
        try { s = JSON.parse(localStorage.getItem(key) || '{}'); } catch { /* ignore */ }
        if (Array.isArray(s.done)) s.done = { tour: s.done }; // older saved progress
        return { mode: 'home', i: 0, open: false, ...s, done: s.done || {} };
    };
    const save = (s) => { try { localStorage.setItem(key, JSON.stringify(s)); } catch { /* storage may be blocked */ } };
    const stepsOf = (mode) => (mode === 'tour' ? overview : guides.find((g) => g.id === mode)?.steps || []);
    const guideOf = (mode) => guides.find((g) => g.id === mode);
    const doneOf = (s, mode) => s.done[mode] || (s.done[mode] = []);

    if (!root.dataset.ready) {
        root.dataset.ready = '1';
        root.innerHTML = `
            <button type="button" id="tour-flag" class="tour-flag" aria-label="Open the walkthrough" title="Walkthrough"><i data-lucide="flag"></i><span id="tour-badge" class="tour-badge"></span></button>
            <div id="tour-card" class="tour-card hidden" role="dialog" aria-label="Walkthrough"></div>`;
        document.getElementById('tour-flag').addEventListener('click', () => { const s = load(); s.open = !s.open; save(s); render(); });
        document.getElementById('tour-card').addEventListener('click', (e) => {
            const el = e.target.closest('[data-tour]');
            if (!el) return;
            const s = load();
            const steps = stepsOf(s.mode);
            const act = el.dataset.tour;
            if (act === 'close') s.open = false;
            else if (act === 'home') { s.mode = 'home'; s.i = 0; }
            else if (act === 'start') { s.mode = el.dataset.mode; s.i = el.dataset.mode === 'tour' ? Math.min(Number(el.dataset.i || 0), overview.length - 1) : 0; if (el.dataset.fresh) { s.done[s.mode] = []; s.i = 0; } }
            else if (act === 'restart') { s.done[s.mode] = []; s.i = 0; }
            else if (act === 'next') { const d = doneOf(s, s.mode); const id = steps[s.i]?.id; if (id && !d.includes(id)) d.push(id); s.i = Math.min(steps.length - 1, s.i + 1); }
            else if (act === 'finish') { const d = doneOf(s, s.mode); const id = steps[s.i]?.id; if (id && !d.includes(id)) d.push(id); s.mode = 'home'; s.i = 0; }
            else if (act === 'back') s.i = Math.max(0, s.i - 1);
            else if (act === 'goto') s.i = Number(el.dataset.i);
            save(s);
            if (el.tagName !== 'A') render();
            else setTimeout(render, 500); // after the page swap
        });
    }

    const clearHighlights = () => document.querySelectorAll('.tour-hl').forEach((e) => e.classList.remove('tour-hl'));
    const navLinks = () => [...document.querySelectorAll('#sidebar-nav a[data-nav]')];
    const visible = (el) => !!(el && (el.offsetWidth || el.offsetHeight || el.getClientRects().length));

    function link(step, act, label, cls, extra = '') {
        const same = norm(step.path) === norm(location.pathname);
        return same || !step.path
            ? `<button type="button" data-tour="${act}" ${extra} class="tour-btn ${cls}">${label}</button>`
            : `<a href="${esc(step.path)}" data-tour="${act}" ${extra} class="tour-btn ${cls}">${label}</a>`;
    }

    function renderHome(s, card) {
        const od = (s.done.tour || []).length;
        card.innerHTML = `
            <div class="tour-head"><span class="tour-step">Walkthrough</span><button type="button" data-tour="close" class="tour-x" aria-label="Close">×</button></div>
            <h3 class="tour-title">What would you like to learn?</h3>
            <button type="button" data-tour="start" data-mode="tour" data-i="${Math.min(od, overview.length - 1)}" class="tour-item">
                <b>Tour of the whole system</b><span>${overview.length} steps · ${od ? `${od} done, continue` : 'start from the Dashboard'}</span></button>
            ${guides.length ? '<p class="tour-sub">How do I…</p>' : ''}
            ${guides.map((g) => { const d = (s.done[g.id] || []).length; return `<button type="button" data-tour="start" data-mode="${esc(g.id)}" ${d >= g.steps.length ? 'data-fresh="1"' : ''} class="tour-item"><b>${esc(g.title)}</b><span>${esc(g.summary)}</span><em>${g.steps.length} steps${d ? ` · ${Math.min(d, g.steps.length)} done` : ''}</em></button>`; }).join('')}`;
    }

    function render() {
        const s = load();
        const card = document.getElementById('tour-card');
        const left = overview.length - (s.done.tour || []).length;
        const badge = document.getElementById('tour-badge');
        badge.textContent = left > 0 ? left : '✓';
        badge.classList.toggle('is-done', left <= 0);
        clearHighlights();
        card.classList.toggle('hidden', !s.open);
        if (!s.open) return;

        const steps = stepsOf(s.mode);
        if (s.mode === 'home' || !steps.length) { s.mode = 'home'; renderHome(s, card); refreshIcons?.(); return; }
        s.i = Math.min(Math.max(0, s.i), steps.length - 1);
        const st = steps[s.i];
        const guide = guideOf(s.mode);
        const here = !st.path || norm(location.pathname) === norm(st.path);

        const nav = navLinks().find((a) => st.path && norm(new URL(a.href).pathname) === norm(st.path))
            || navLinks().find((a) => st.path && new URL(a.href).pathname !== '/' && norm(st.path).startsWith(norm(new URL(a.href).pathname) + '/'));
        nav?.classList.add('tour-hl');
        let missing = false;
        if (here && st.target) {
            const t = document.querySelector(st.target);
            if (t && visible(t)) { t.classList.add('tour-hl'); t.scrollIntoView({ block: 'center', behavior: 'smooth' }); } else missing = true;
        }

        const last = s.i === steps.length - 1;
        const pct = Math.round(((s.done[s.mode] || []).length / steps.length) * 100);
        const nextStep = steps[Math.min(steps.length - 1, s.i + 1)];
        card.innerHTML = `
            <div class="tour-head"><span class="tour-step">${guide ? esc(guide.title) + ' · ' : ''}Step ${s.i + 1} of ${steps.length}</span><button type="button" data-tour="close" class="tour-x" aria-label="Close">×</button></div>
            <div class="tour-bar"><i style="width:${pct}%"></i></div>
            <h3 class="tour-title">${esc(st.title)}</h3>
            <p class="tour-text">${esc(st.text)}</p>
            ${!here ? link(st, 'here', 'Take me there →', 'tour-primary') : '<p class="tour-here">✓ You are on this page.</p>'}
            ${here && missing ? '<p class="tour-miss">The highlighted item is not visible yet. Open or scroll to it first (for example press <b>Mark up</b>).</p>' : ''}
            <div class="tour-nav">
                <button type="button" data-tour="back" class="tour-btn" ${s.i === 0 ? 'disabled' : ''}>← Back</button>
                ${last
                    ? `<button type="button" data-tour="${guide ? 'finish' : 'restart'}" class="tour-btn tour-primary">${guide ? 'Done ✓' : 'Start again'}</button>`
                    : link(nextStep, 'next', `Next: ${esc(nextStep.title)} →`, here ? 'tour-primary' : '')}
            </div>
            <div class="tour-foot"><button type="button" data-tour="home" class="tour-link">☰ All guides</button></div>
            <details class="tour-all"><summary>All steps</summary><ol>${steps.map((x, n) => `<li><a href="${esc(x.path || '#')}" data-tour="goto" data-i="${n}" class="${n === s.i ? 'is-now' : ''}">${(s.done[s.mode] || []).includes(x.id) ? '✓ ' : ''}${esc(x.title)}</a></li>`).join('')}</ol></details>`;
        refreshIcons?.();
    }

    render();
}
