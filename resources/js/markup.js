import Swal from 'sweetalert2';
import { roleColor } from './signing.js';

const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

const toast = (icon, title) => Swal.fire({ toast: true, position: 'top-end', icon, title, showConfirmButton: false, timer: 2600, timerProgressBar: true });

async function api(url, method = 'GET', body = null) {
    const res = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), ...(body ? { 'Content-Type': 'application/json' } : {}) },
        body: body ? JSON.stringify(body) : null,
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message || 'Something went wrong. Please try again.');
    return data;
}

/** Mark-up viewer: notes, highlights and redaction boxes drawn over a page picture. */
export function initMarkup() {
    const root = document.getElementById('markup-root');
    if (!root || root.dataset.ready || !document.getElementById('mk-open')) return;
    root.dataset.ready = '1';

    const plain = document.getElementById('plain-viewer');
    const panel = document.getElementById('markup-panel');
    const stage = document.getElementById('mk-stage');
    const img = document.getElementById('mk-img');
    const layer = document.getElementById('mk-layer');
    const label = document.getElementById('mk-page');
    const hint = document.getElementById('mk-hint');
    const applyBox = document.getElementById('mk-apply');
    let state = null;
    let page = 1;
    let tool = 'note';
    let marks = [];

    const hints = {
        note: 'Drag a box over the area, then write your note.',
        highlight: 'Drag over the text or area you want to highlight.',
        sign: 'Drag a box where someone must sign, then say who (a role such as Candidate or HR Manager). Each role gets its own colour. Then send for signature below.',
        redact: 'Drag over anything that must be hidden. Nothing changes until you press "Create redacted copy".',
    };

    function render() {
        label.textContent = `Page ${page} of ${state.pages}`;
        document.getElementById('mk-prev').disabled = page <= 1;
        document.getElementById('mk-next').disabled = page >= state.pages;
        layer.innerHTML = '';
        marks.filter((m) => m.page === page).forEach((m) => {
            const el = document.createElement('div');
            el.className = `mk-mark mk-${m.kind}`;
            el.style.cssText = `left:${m.x * 100}%;top:${m.y * 100}%;width:${m.w * 100}%;height:${m.h * 100}%`;
            if (m.kind === 'note') el.innerHTML = `<span class="mk-pin">${esc(m.text)}<em>${esc(m.by)} · ${esc(m.at)}</em></span>`;
            if (m.kind === 'sign') {
                el.style.borderColor = roleColor(m.text);
                el.style.background = roleColor(m.text).replace('hsl(', 'hsl(').replace(')', ' / .15)');
                el.innerHTML = `<span class="mk-tag" style="color:${roleColor(m.text)}">${esc(m.text || 'Sign here')}${m.signer ? ' · ' + esc(m.signer) : ''}</span>`;
            }
            if (m.kind === 'redact') el.innerHTML = '<span class="mk-tag">To be hidden</span>';
            if (m.mine) {
                const x = document.createElement('button');
                x.type = 'button'; x.className = 'mk-del'; x.title = 'Remove'; x.textContent = '×';
                x.addEventListener('mousedown', (e) => e.stopPropagation());
                x.addEventListener('click', async (e) => {
                    e.stopPropagation();
                    try { await api(`/annotations/${m.id}`, 'DELETE'); marks = marks.filter((k) => k.id !== m.id); render(); } catch (err) { toast('error', err.message); }
                });
                el.appendChild(x);
            }
            layer.appendChild(el);
        });
        const free = {};
        marks.filter((m) => m.kind === 'sign' && !m.signer).forEach((m) => { free[m.text] = (free[m.text] || 0) + 1; });
        const chips = document.getElementById('sign-role-chips');
        if (chips) chips.innerHTML = Object.keys(free).length ? Object.entries(free).map(([r, n]) => `<span class="rounded-full px-2 py-0.5 text-[11px] font-semibold text-white" style="background:${roleColor(r)}">${esc(r)} · ${n} box${n === 1 ? '' : 'es'}</span>`).join('') : '<span class="font-medium">No signing boxes drawn yet.</span>';
        const dl = document.getElementById('esign-roles');
        if (dl) dl.innerHTML = Object.keys(free).map((r) => `<option value="${esc(r)}">`).join('');
        const sg = marks.filter((m) => m.kind === 'sign' && !m.signer).length;
        document.querySelectorAll('[data-sign-count]').forEach((s) => { s.textContent = sg ? `${sg} signing box${sg === 1 ? '' : 'es'} placed — they go to the next signer you request.` : 'No signing boxes placed. The signer will sign in the side panel instead.'; });
        const n = marks.filter((m) => m.kind === 'redact').length;
        applyBox.classList.toggle('hidden', n === 0);
        document.getElementById('mk-redact-count').textContent = `${n} area${n === 1 ? '' : 's'} marked`;
    }

    function loadPage() {
        img.src = `/documents/${root.dataset.id}/pages/${page}`;
        render();
    }

    async function open() {
        try {
            state = await api(`/documents/${root.dataset.id}/pages`);
        } catch (e) { return toast('error', e.message); }
        if (!state.available) return Swal.fire({ icon: 'info', title: 'Mark-up is not available', text: state.message, confirmButtonText: 'OK' });
        marks = state.marks;
        plain.classList.add('hidden');
        panel.classList.remove('hidden');
        document.querySelectorAll('[data-tool="redact"],[data-tool="sign"]').forEach((b) => b.classList.toggle('hidden', !state.can_redact));
        setTool('note');
        page = 1;
        loadPage();
    }

    function close() {
        panel.classList.add('hidden');
        plain.classList.remove('hidden');
    }

    function setTool(t) {
        tool = t;
        document.querySelectorAll('[data-tool]').forEach((b) => b.classList.toggle('is-on', b.dataset.tool === t));
        hint.textContent = hints[t];
    }

    document.getElementById('mk-open').addEventListener('click', open);
    document.getElementById('mk-close').addEventListener('click', close);
    document.querySelectorAll('[data-tool]').forEach((b) => b.addEventListener('click', () => setTool(b.dataset.tool)));
    document.getElementById('mk-prev').addEventListener('click', () => { page = Math.max(1, page - 1); loadPage(); });
    document.getElementById('mk-next').addEventListener('click', () => { page = Math.min(state.pages, page + 1); loadPage(); });

    // Drawing
    let start = null;
    let ghost = null;
    const pt = (e) => {
        const r = layer.getBoundingClientRect();
        return { x: Math.min(1, Math.max(0, (e.clientX - r.left) / r.width)), y: Math.min(1, Math.max(0, (e.clientY - r.top) / r.height)) };
    };
    layer.addEventListener('mousedown', (e) => {
        if (e.target !== layer || e.button !== 0) return;
        e.preventDefault();
        start = pt(e);
        ghost = document.createElement('div');
        ghost.className = `mk-mark mk-${tool} mk-ghost`;
        layer.appendChild(ghost);
    });
    window.addEventListener('mousemove', (e) => {
        if (!start) return;
        const p = pt(e);
        ghost.style.cssText = `left:${Math.min(start.x, p.x) * 100}%;top:${Math.min(start.y, p.y) * 100}%;width:${Math.abs(p.x - start.x) * 100}%;height:${Math.abs(p.y - start.y) * 100}%`;
    });
    window.addEventListener('mouseup', async (e) => {
        if (!start) return;
        const p = pt(e);
        const box = { x: Math.min(start.x, p.x), y: Math.min(start.y, p.y), w: Math.abs(p.x - start.x), h: Math.abs(p.y - start.y) };
        start = null;
        ghost?.remove();
        ghost = null;
        if (box.w < 0.005 || box.h < 0.005) return;
        let text = null;
        if (tool === 'sign') {
            const roles = [...new Set(marks.filter((k) => k.kind === 'sign').map((k) => k.text).filter(Boolean))];
            const r = await Swal.fire({
                title: 'Who signs in this box?',
                html: `<input id="sw-role" class="swal2-input" list="sw-roles" maxlength="60" placeholder="e.g. Candidate, HR Manager" value="${esc(roles[roles.length - 1] || '')}"><datalist id="sw-roles">${roles.map((x) => `<option value="${esc(x)}">`).join('')}</datalist><p style="font-size:12px;color:#64748b;margin-top:6px">Boxes with the same role are signed by the same person.</p>`,
                showCancelButton: true, confirmButtonText: 'Place box', focusConfirm: false,
                preConfirm: () => { const v = document.getElementById('sw-role').value.trim(); if (!v) { Swal.showValidationMessage('Type a role first.'); return false; } return v; },
            });
            if (!r.isConfirmed) return;
            text = r.value;
        }
        if (tool === 'note') {
            const r = await Swal.fire({ title: 'Add a note', input: 'textarea', inputAttributes: { maxlength: 500 }, inputPlaceholder: 'Write your note…', showCancelButton: true, confirmButtonText: 'Save note', inputValidator: (v) => (!v.trim() ? 'Write the note first.' : undefined) });
            if (!r.isConfirmed) return;
            text = r.value.trim();
        }
        try {
            const d = await api(`/documents/${root.dataset.id}/annotations`, 'POST', { kind: tool, page, ...box, text });
            marks.push(d.mark);
            render();
        } catch (err) { toast('error', err.message); }
    });
}
