/* eForm builder: a simple list of fields (name, type, required, choices) with up/down/remove. */
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

export function initFormBuilder(root, { notify, confirmBox, csrf, refreshIcons }) {
    if (root.dataset.ready) return;
    root.dataset.ready = '1';
    const P = JSON.parse(root.dataset.payload);
    let fields = structuredClone(P.fields || []);
    const list = root.querySelector('#ff-list');

    const render = () => {
        list.innerHTML = fields.length ? fields.map((f, i) => `
            <div class="rounded-lg border border-slate-200 bg-white p-3" data-i="${i}">
                <div class="grid gap-3 sm:grid-cols-[1fr_11rem_auto_auto]">
                    <div><label class="label">Question</label><input class="input" data-k="label" value="${esc(f.label)}" maxlength="100" placeholder="e.g. Customer name"></div>
                    <div><label class="label">Answer type</label><select class="input" data-k="type">${Object.entries(P.types).map(([k, v]) => `<option value="${k}" ${f.type === k ? 'selected' : ''}>${esc(v)}</option>`).join('')}</select></div>
                    <label class="flex items-center gap-2 pt-6 text-sm"><input type="checkbox" data-k="required" ${f.required ? 'checked' : ''}> Required</label>
                    <div class="flex items-center gap-1 pt-5">
                        <button type="button" class="icon-btn" data-act="up" title="Move up" ${i === 0 ? 'disabled' : ''}>↑</button>
                        <button type="button" class="icon-btn" data-act="down" title="Move down" ${i === fields.length - 1 ? 'disabled' : ''}>↓</button>
                        <button type="button" class="icon-btn hover:!text-red-600" data-act="del" title="Remove">×</button>
                    </div>
                </div>
                ${f.type === 'select' ? `<div class="mt-3"><label class="label">Choices (one per line)</label><textarea class="input" rows="3" data-k="options">${esc((f.options || []).join('\n'))}</textarea></div>` : ''}
            </div>`).join('') : '<p class="py-6 text-center text-sm text-slate-400">No questions yet. Add the first one.</p>';
        refreshIcons();
    };

    list.addEventListener('input', (e) => {
        const box = e.target.closest('[data-i]'); if (!box) return; const f = fields[+box.dataset.i]; const k = e.target.dataset.k;
        if (k === 'required') f.required = e.target.checked;
        else if (k === 'options') f.options = e.target.value.split('\n').map((s) => s.trim()).filter(Boolean);
        else if (k) f[k] = e.target.value;
        if (k === 'type') render();
    });
    list.addEventListener('click', (e) => {
        const b = e.target.closest('[data-act]'); if (!b) return; const i = +b.closest('[data-i]').dataset.i;
        if (b.dataset.act === 'del') fields.splice(i, 1);
        else { const j = b.dataset.act === 'up' ? i - 1 : i + 1; [fields[i], fields[j]] = [fields[j], fields[i]]; }
        render();
    });
    root.querySelector('#ff-add').addEventListener('click', () => { fields.push({ label: '', type: 'text', required: false, options: [] }); render(); list.lastElementChild?.querySelector('input')?.focus(); });

    root.querySelector('#ff-save').addEventListener('click', async (e) => {
        if (!await confirmBox({ title: 'Save this form?', text: 'Existing submissions are kept as they were.', confirmText: 'Yes, save' })) return;
        const btn = e.currentTarget; btn.disabled = true;
        try {
            const res = await fetch(P.saveUrl, {
                method: 'PUT', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ name: root.querySelector('#ff-name').value, description: root.querySelector('#ff-desc').value, active: root.querySelector('#ff-active').checked ? 1 : 0, fields }),
            });
            const data = await res.json().catch(() => ({}));
            if (res.status === 422 && data.errors) return notify.error('', '<ul style="text-align:left;margin:0;padding-left:1.1rem">' + Object.values(data.errors).flat().map((m) => `<li>${esc(m)}</li>`).join('') + '</ul>');
            if (!res.ok || data.ok === false) return notify.error(data.message || 'Could not save the form.');
            fields = data.fields; render(); notify.success(data.message);
        } catch { notify.error('Could not reach the server. Try again.'); } finally { btn.disabled = false; }
    });
    render();
}
