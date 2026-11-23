/* Search as you type: suggestions under the top-bar search box. */
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

const ICON = { pdf: ['file-text', 'text-red-600 bg-red-50'], doc: ['file-text', 'text-blue-600 bg-blue-50'], docx: ['file-text', 'text-blue-600 bg-blue-50'], xls: ['file-spreadsheet', 'text-emerald-600 bg-emerald-50'], xlsx: ['file-spreadsheet', 'text-emerald-600 bg-emerald-50'], csv: ['file-spreadsheet', 'text-emerald-600 bg-emerald-50'], ppt: ['presentation', 'text-orange-600 bg-orange-50'], pptx: ['presentation', 'text-orange-600 bg-orange-50'] };
const IMG = ['png', 'jpg', 'jpeg', 'gif', 'bmp', 'tif', 'tiff', 'webp'];
const kind = (ext) => ICON[ext] || (IMG.includes(ext) ? ['file-image', 'text-purple-600 bg-purple-50'] : ['file-text', 'text-slate-600 bg-slate-100']);

function mark(text, terms) {
    let out = esc(text);
    terms.filter(Boolean).forEach((t) => {
        out = out.replace(new RegExp('(' + t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'ig'), '<mark class="rounded bg-yellow-200/70 px-0.5 text-inherit">$1</mark>');
    });
    return out;
}

export function initSuggest(refreshIcons) {
    const form = document.querySelector('form[data-suggest]');
    if (!form || form.dataset.ready) return;
    form.dataset.ready = '1';
    const input = form.querySelector('input[name="q"]');
    const panel = document.createElement('div');
    panel.id = 'suggest-panel';
    panel.setAttribute('role', 'listbox');
    panel.className = 'absolute left-0 right-0 top-full z-40 mt-2 hidden overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl';
    form.appendChild(panel);

    let timer = null;
    let ctrl = null;
    let items = [];
    let active = -1;
    let seq = 0;

    const close = () => { panel.classList.add('hidden'); input.setAttribute('aria-expanded', 'false'); active = -1; };
    const open = () => { panel.classList.remove('hidden'); input.setAttribute('aria-expanded', 'true'); };

    function highlight(i) {
        active = i;
        items.forEach((el, n) => el.classList.toggle('bg-brand-soft', n === i));
        items[i]?.scrollIntoView({ block: 'nearest' });
    }

    function render(d, q) {
        const terms = q.split(/\s+/).filter(Boolean);
        let html = '';
        if (d.projects.length) {
            html += '<div class="px-4 pb-1 pt-3 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Projects</div>';
            html += d.projects.map((p) => `<a href="${esc(p.url)}" data-sg class="flex items-center gap-3 px-4 py-2 text-sm hover:bg-slate-50"><span class="inline-flex size-8 items-center justify-center rounded-lg bg-brand-soft text-brand"><i data-lucide="folder-open"></i></span><span class="min-w-0"><span class="block truncate font-medium">${mark(p.name, terms)}</span><span class="block truncate text-xs text-slate-400">${esc(p.dept || '')}</span></span></a>`).join('');
        }
        if (d.docs.length) {
            html += '<div class="px-4 pb-1 pt-3 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Documents</div>';
            html += d.docs.map((x) => {
                const [ic, tone] = kind(x.ext);
                return `<a href="${esc(x.url)}" data-sg class="flex items-start gap-3 px-4 py-2 text-sm hover:bg-slate-50"><span class="mt-0.5 inline-flex size-8 shrink-0 items-center justify-center rounded-lg ${tone}"><i data-lucide="${ic}"></i></span>
                    <span class="min-w-0 flex-1"><span class="block truncate font-medium">${mark(x.name, terms)}</span>
                    <span class="block truncate text-xs text-slate-400">${esc(x.project || '')}${x.type ? ' · ' + esc(x.type) : ''}${x.status === 'unindexed' ? ' · waiting to be indexed' : ''}</span>
                    ${x.snippet ? `<span class="mt-0.5 block truncate text-xs text-slate-500">${mark(x.snippet, terms)}</span>` : ''}</span></a>`;
            }).join('');
        }
        if (!d.docs.length && !d.projects.length) {
            html = `<div class="px-4 py-6 text-center text-sm text-slate-400">No match for “${esc(q)}”. Try fewer or different words.</div>`;
        } else {
            html += `<a href="${esc(d.see_all)}" data-sg class="flex items-center justify-between border-t border-slate-100 bg-slate-50 px-4 py-2.5 text-xs font-medium text-brand hover:bg-brand-soft"><span>See all ${d.total} result${d.total === 1 ? '' : 's'} for “${esc(q)}”</span><span class="text-slate-400">Enter ↵</span></a>`;
        }
        panel.innerHTML = html;
        items = [...panel.querySelectorAll('[data-sg]')];
        active = -1;
        refreshIcons();
        open();
    }

    async function run() {
        const q = input.value.trim();
        if (q.length < 2) { close(); return; }
        ctrl?.abort();
        ctrl = new AbortController();
        const mine = ++seq;
        panel.innerHTML = '<div class="px-4 py-4 text-sm text-slate-400">Searching…</div>';
        open();
        try {
            const res = await fetch(form.dataset.suggest + '?q=' + encodeURIComponent(q), { signal: ctrl.signal, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) throw new Error();
            const d = await res.json();
            if (mine === seq) render(d, q);
        } catch (e) {
            if (e.name !== 'AbortError' && mine === seq) { panel.innerHTML = '<div class="px-4 py-4 text-sm text-slate-400">Suggestions are not available right now. Press Enter to search.</div>'; }
        }
    }

    input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(run, 180); });
    input.addEventListener('focus', () => { if (input.value.trim().length >= 2 && panel.innerHTML) open(); });
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') { close(); return; }
        if (!items.length || panel.classList.contains('hidden')) return;
        if (e.key === 'ArrowDown') { e.preventDefault(); highlight((active + 1) % items.length); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); highlight((active - 1 + items.length) % items.length); }
        else if (e.key === 'Enter' && active >= 0) { e.preventDefault(); items[active].click(); }
    });
    document.addEventListener('click', (e) => { if (!form.contains(e.target)) close(); });
    panel.addEventListener('click', () => close());
}
