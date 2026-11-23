/* Visual workflow builder: drag steps on a canvas, connect exits to the next step, edit a step in the side panel. */

const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const NODE_W = 240;

export function initFlowBuilder(root, { notify, confirmBox, csrf, refreshIcons }) {
    if (root.dataset.ready) return;
    root.dataset.ready = '1';
    const P = JSON.parse(root.dataset.payload);
    const types = Object.fromEntries(P.types.map((t) => [t.key, t]));
    let nodes = structuredClone(P.graph.nodes || []);
    let edges = structuredClone(P.graph.edges || []);
    let selected = null;       // node id
    let selectedEdge = null;   // index into edges
    let dirty = false;
    const view = { x: 40, y: 40, k: 1 };
    const busy = new Set(P.busy || []);

    const $ = (s, r = root) => r.querySelector(s);
    const $d = (s) => document.querySelector(s);   // header controls live outside the canvas
    const world = $('#fb-world'), svg = $('#fb-edges'), layer = $('#fb-nodes'), inspector = $('#fb-inspector'), vp = $('#fb-viewport');
    const quick = $('#fb-quick'), issuesBox = $('#fb-issues'), saveBtn = $d('#fb-save'), dirtyBadge = $d('#fb-dirty');

    const uid = () => 'n' + Math.random().toString(36).slice(2, 8);
    const byId = (id) => nodes.find((n) => n.id === id);
    const ports = (n) => {
        const c = n.config || {};
        switch (n.type) {
            case 'task': return c.options?.length ? c.options : ['Done'];
            case 'signature': return ['Signed', 'Declined'];
            case 'condition': return [...(c.rules || []).map((r) => r.label || 'Rule'), 'Otherwise'];
            case 'duplicate': return ['Duplicate', 'Not a duplicate'];
            case 'fork': return Array.from({ length: Math.max(2, Math.min(5, +c.branches || 2)) }, (_, i) => 'Branch ' + (i + 1));
            case 'start': case 'email': case 'set_index': return ['Next'];
            default: return [];
        }
    };
    const defaults = (type) => ({
        task: { options: ['Done'], assignees: [], requirements: [] },
        email: { to: '', to_sender: false, to_field: '', to_map: '', subject: '', body: '', secure: false },
        condition: { rules: [{ label: 'Rule 1', conds: [{ field: '', op: 'eq', value: '' }] }] },
        set_index: { sets: [{ field: '', value: '' }] },
        duplicate: { field: '' }, signature: { days: 14, remind_days: 2 }, fork: { branches: 2 }, goto: { workflow_id: '' }, end: { label: '', archive: false },
    }[type] || {});
    const touch = () => { dirty = true; dirtyBadge.classList.remove('hidden'); };
    const userName = (id) => P.users.find((u) => u.id === +id)?.name;
    const fieldLabel = (k) => P.fields.find((f) => f.key === k)?.label || k;

    function summary(n) {
        const c = n.config || {};
        switch (n.type) {
            case 'task': return (c.assignees?.length ? c.assignees.map(userName).filter(Boolean).join(', ') : 'Client Admins') + (c.overdue_hours ? ` · ${c.overdue_hours}h` : '');
            case 'email': return c.subject || 'No subject yet';
            case 'condition': return (c.rules || []).length + ' rule(s)';
            case 'set_index': return (c.sets || []).map((s) => fieldLabel(s.field)).filter(Boolean).join(', ') || 'Nothing set yet';
            case 'duplicate': return c.field ? 'By ' + fieldLabel(c.field) : 'Choose a field';
            case 'signature': return c.signer_name || c.signer_email || (c.email_field ? 'Email from ' + fieldLabel(c.email_field) : 'Who signs?');
            case 'goto': return P.workflows.find((w) => w.id === +c.workflow_id)?.name || 'Choose a workflow';
            case 'end': return c.label || 'Finish';
            default: return types[n.type].help;
        }
    }

    /* ---------------- Rendering ---------------- */
    function renderNodes() {
        layer.innerHTML = '';
        nodes.forEach((n) => {
            const t = types[n.type];
            const el = document.createElement('div');
            el.className = 'fb-node' + (n.id === selected ? ' sel' : '') + (busy.has(n.id) ? ' busy' : '');
            el.dataset.id = n.id;
            el.style.cssText = `left:${n.x}px;top:${n.y}px;width:${NODE_W}px`;
            const outs = ports(n);
            el.innerHTML = (n.type !== 'start' ? '<i class="fb-in" data-in title="Drop a connection here"></i>' : '')
                + `<div class="fb-head" style="background:${t.color}"><i data-lucide="${t.icon}"></i><span>${esc(n.name)}</span></div>`
                + `<div class="fb-sub">${esc(summary(n))}</div>`
                + (outs.length ? '<div class="fb-ports">' + outs.map((p) => `<div class="fb-port"><span>${esc(p)}</span><i class="fb-out ${edges.some((e) => e.from === n.id && e.port === p) ? 'used' : ''}" data-port="${esc(p)}" title="Drag to connect"></i></div>`).join('') + '</div>' : '');
            layer.appendChild(el);
        });
        refreshIcons();
        drawEdges();
        checkIssues();
    }

    const nodeEl = (id) => layer.querySelector(`.fb-node[data-id="${CSS.escape(id)}"]`);
    function center(el) {
        const r = el.getBoundingClientRect(), w = world.getBoundingClientRect();
        return { x: (r.left + r.width / 2 - w.left) / view.k, y: (r.top + r.height / 2 - w.top) / view.k };
    }
    const curve = (a, b) => { const d = Math.max(50, Math.abs(b.x - a.x) / 2); return `M${a.x},${a.y} C${a.x + d},${a.y} ${b.x - d},${b.y} ${b.x},${b.y}`; };

    function drawEdges() {
        let html = '<defs><marker id="fb-arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto"><path d="M0,0 L10,5 L0,10 z" fill="#94a3b8"/></marker><marker id="fb-arrow-sel" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto"><path d="M0,0 L10,5 L0,10 z" fill="var(--brand)"/></marker></defs>';
        edges.forEach((e, i) => {
            const fe = nodeEl(e.from), te = nodeEl(e.to);
            const out = fe?.querySelector(`.fb-out[data-port="${CSS.escape(e.port)}"]`), inn = te?.querySelector('.fb-in');
            if (!out || !inn) return;
            const d = curve(center(out), center(inn));
            html += `<path class="fb-edge-hit" data-edge="${i}" d="${d}"/><path class="fb-edge ${i === selectedEdge ? 'sel' : ''}" d="${d}" marker-end="url(#fb-arrow${i === selectedEdge ? '-sel' : ''})"/>`;
        });
        svg.innerHTML = html;
        const del = $('#fb-edge-del');
        if (selectedEdge !== null && edges[selectedEdge]) {
            const e = edges[selectedEdge], fe = nodeEl(e.from)?.querySelector(`.fb-out[data-port="${CSS.escape(e.port)}"]`), te = nodeEl(e.to)?.querySelector('.fb-in');
            if (fe && te) { const a = center(fe), b = center(te); del.style.left = (a.x + b.x) / 2 + 'px'; del.style.top = (a.y + b.y) / 2 + 'px'; del.classList.remove('hidden'); return; }
        }
        del.classList.add('hidden');
    }

    function applyView() {
        world.style.transform = `translate(${view.x}px,${view.y}px) scale(${view.k})`;
        vp.style.backgroundPosition = `${view.x}px ${view.y}px`;
        vp.style.backgroundSize = `${24 * view.k}px ${24 * view.k}px`;
    }

    function checkIssues() {
        const out = [];
        if (nodes.filter((n) => n.type === 'start').length !== 1) out.push('A workflow needs exactly one Start step.');
        nodes.forEach((n) => {
            ports(n).forEach((p) => { if (!edges.some((e) => e.from === n.id && e.port === p)) out.push(`“${n.name}”: the “${p}” exit is not connected, so the workflow ends there.`); });
            if (n.type === 'task' && !(n.config.assignees || []).length) out.push(`“${n.name}” has nobody assigned, so Client Admins will get it.`);
            if (n.type !== 'start' && !edges.some((e) => e.to === n.id)) out.push(`“${n.name}” has no incoming connection, so nothing reaches it.`);
        });
        issuesBox.innerHTML = out.length ? out.map((m) => `<li>${esc(m)}</li>`).join('') : '<li class="ok">Everything is connected.</li>';
        const b = $d('#fb-issues-btn');
        b.textContent = out.length ? `${out.length} to check` : 'All good';
        b.className = 'btn btn-sm ' + (out.length ? 'btn-ghost fb-warn' : 'btn-ghost');
    }

    /* ---------------- Selecting, adding, deleting ---------------- */
    function select(id, edge = null) {
        selected = id; selectedEdge = edge;
        layer.querySelectorAll('.fb-node').forEach((el) => el.classList.toggle('sel', el.dataset.id === id));
        drawEdges();
        renderInspector();
    }

    function addNode(type, x, y, connectFrom = null) {
        const t = types[type];
        const n = { id: uid(), type, name: t.label, x: Math.round(x), y: Math.round(y), config: defaults(type) };
        nodes.push(n);
        if (connectFrom) edges = edges.filter((e) => !(e.from === connectFrom.id && e.port === connectFrom.port)).concat({ from: connectFrom.id, port: connectFrom.port, to: n.id });
        touch(); renderNodes(); select(n.id);
        return n;
    }

    function removeNode(id) {
        const n = byId(id);
        if (busy.has(id)) return notify.error('Documents are waiting at this step. Finish them first.');
        if (n.type === 'start' && nodes.filter((x) => x.type === 'start').length === 1 && false) return;
        nodes = nodes.filter((x) => x.id !== id);
        edges = edges.filter((e) => e.from !== id && e.to !== id);
        selected = null; selectedEdge = null; touch(); renderNodes(); renderInspector();
    }

    const toWorld = (clientX, clientY) => { const w = world.getBoundingClientRect(); return { x: (clientX - w.left) / view.k, y: (clientY - w.top) / view.k }; };

    /* ---------------- Pointer interactions ---------------- */
    let drag = null;
    root.addEventListener('pointerdown', (e) => {
        if (e.button !== 0) return;
        quick.classList.add('hidden');
        const out = e.target.closest('.fb-out');
        const node = e.target.closest('.fb-node');
        const hit = e.target.closest('.fb-edge-hit');
        if (out && node) {
            e.preventDefault();
            drag = { kind: 'connect', from: node.dataset.id, port: out.dataset.port, start: center(out), pointer: e.pointerId };
            const tmp = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            tmp.setAttribute('class', 'fb-edge tmp'); svg.appendChild(tmp); drag.tmp = tmp;
        } else if (e.target.closest('.fb-head') && node) {
            const n = byId(node.dataset.id);
            drag = { kind: 'move', n, el: node, sx: e.clientX, sy: e.clientY, ox: n.x, oy: n.y, moved: false };
            select(n.id);
        } else if (node) {
            select(node.dataset.id);
        } else if (hit) {
            select(null, +hit.dataset.edge);
        } else if (e.target.closest('#fb-viewport') && !e.target.closest('[data-ui]')) {
            drag = { kind: 'pan', sx: e.clientX, sy: e.clientY, ox: view.x, oy: view.y };
            if (selected || selectedEdge !== null) select(null);
            vp.classList.add('grabbing');
        }
    });
    window.addEventListener('pointermove', (e) => {
        if (!drag) return;
        if (drag.kind === 'move') {
            const dx = (e.clientX - drag.sx) / view.k, dy = (e.clientY - drag.sy) / view.k;
            if (Math.abs(dx) + Math.abs(dy) > 2) drag.moved = true;
            drag.n.x = Math.round(drag.ox + dx); drag.n.y = Math.round(drag.oy + dy);
            drag.el.style.left = drag.n.x + 'px'; drag.el.style.top = drag.n.y + 'px';
            drawEdges();
        } else if (drag.kind === 'pan') {
            view.x = drag.ox + e.clientX - drag.sx; view.y = drag.oy + e.clientY - drag.sy; applyView();
        } else if (drag.kind === 'connect') {
            drag.tmp.setAttribute('d', curve(drag.start, toWorld(e.clientX, e.clientY)));
            const over = document.elementFromPoint(e.clientX, e.clientY)?.closest('.fb-node');
            layer.querySelectorAll('.fb-node').forEach((el) => el.classList.toggle('drop', el === over && over.dataset.id !== drag.from && !!over.querySelector('.fb-in')));
        }
    });
    window.addEventListener('pointerup', (e) => {
        if (!drag) return;
        const d = drag; drag = null; vp.classList.remove('grabbing');
        if (d.kind === 'move' && d.moved) touch();
        if (d.kind === 'connect') {
            d.tmp.remove();
            layer.querySelectorAll('.fb-node.drop').forEach((el) => el.classList.remove('drop'));
            const over = document.elementFromPoint(e.clientX, e.clientY)?.closest('.fb-node');
            if (over && over.dataset.id !== d.from && over.querySelector('.fb-in')) {
                edges = edges.filter((x) => !(x.from === d.from && x.port === d.port)).concat({ from: d.from, port: d.port, to: over.dataset.id });
                touch(); renderNodes();
            } else if (!over && root.contains(document.elementFromPoint(e.clientX, e.clientY))) {
                openQuick(e.clientX, e.clientY, { id: d.from, port: d.port });
            }
        }
    });

    root.addEventListener('wheel', (e) => {
        if (!e.target.closest('#fb-viewport') || e.target.closest('[data-ui]')) return;
        e.preventDefault();
        if (e.ctrlKey || e.metaKey) {
            const k = Math.max(0.3, Math.min(1.6, view.k * (e.deltaY < 0 ? 1.1 : 0.9)));
            const r = vp.getBoundingClientRect(), px = e.clientX - r.left, py = e.clientY - r.top;
            view.x = px - (px - view.x) * (k / view.k); view.y = py - (py - view.y) * (k / view.k); view.k = k;
        } else { view.x -= e.deltaX; view.y -= e.deltaY; }
        applyView();
    }, { passive: false });

    document.addEventListener('keydown', (e) => {
        if (!root.isConnected || e.target.closest('input,textarea,select')) return;
        if (e.key === 'Delete' || e.key === 'Backspace') {
            if (selectedEdge !== null) { edges.splice(selectedEdge, 1); selectedEdge = null; touch(); renderNodes(); }
            else if (selected) removeNode(selected);
        }
    });

    /* ---------------- Quick-add menu (drop a connection on empty space) ---------------- */
    function openQuick(cx, cy, from) {
        const r = root.getBoundingClientRect();
        quick.style.left = Math.min(cx - r.left, r.width - 230) + 'px'; quick.style.top = Math.min(cy - r.top, r.height - 330) + 'px';
        quick.innerHTML = '<div class="fb-quick-t">Add the next step</div>' + P.types.filter((t) => t.key !== 'start').map((t) => `<button type="button" data-q="${t.key}"><i data-lucide="${t.icon}" style="color:${t.color}"></i>${esc(t.label)}</button>`).join('');
        quick.classList.remove('hidden'); refreshIcons();
        quick._from = from; quick._at = toWorld(cx, cy);
    }
    quick.addEventListener('click', (e) => {
        const b = e.target.closest('[data-q]'); if (!b) return;
        quick.classList.add('hidden');
        addNode(b.dataset.q, quick._at.x + 20, quick._at.y - 30, quick._from);
    });

    /* ---------------- Toolbar ---------------- */
    root.addEventListener('click', (e) => {
        const add = e.target.closest('[data-add]');
        if (add) {
            const r = vp.getBoundingClientRect(); const p = toWorld(r.left + r.width / 2 - NODE_W / 2 + (nodes.length % 5) * 16, r.top + r.height / 3 + (nodes.length % 5) * 16);
            return addNode(add.dataset.add, p.x, p.y);
        }
        const z = e.target.closest('[data-zoom]');
        if (z) { const k = Math.max(0.3, Math.min(1.6, view.k * (z.dataset.zoom === 'in' ? 1.15 : 0.87))); const r = vp.getBoundingClientRect(); const px = r.width / 2, py = r.height / 2; view.x = px - (px - view.x) * (k / view.k); view.y = py - (py - view.y) * (k / view.k); view.k = k; applyView(); }
        if (e.target.closest('[data-fit]')) fit();
        if (e.target.closest('[data-tidy]')) { tidy(); touch(); renderNodes(); fit(); }
        if (e.target.closest('#fb-edge-del')) { edges.splice(selectedEdge, 1); selectedEdge = null; touch(); renderNodes(); }
    });

    $d('#fb-issues-btn').addEventListener('click', () => issuesBox.parentElement.classList.toggle('hidden'));

    function fit() {
        if (!nodes.length) return;
        const xs = nodes.map((n) => n.x), ys = nodes.map((n) => n.y);
        const minX = Math.min(...xs), maxX = Math.max(...xs) + NODE_W, minY = Math.min(...ys), maxY = Math.max(...ys) + 160;
        const r = vp.getBoundingClientRect();
        const k = Math.max(0.3, Math.min(1, Math.min((r.width - 360) / (maxX - minX), (r.height - 60) / (maxY - minY))));
        view.k = k; view.x = 70 - minX * k; view.y = 30 - minY * k; applyView(); drawEdges();
    }

    /** Layered left-to-right layout from the Start step. */
    function tidy() {
        const depth = {}; const start = nodes.find((n) => n.type === 'start') || nodes[0];
        const q = [start.id]; depth[start.id] = 0;
        while (q.length) {
            const id = q.shift();
            edges.filter((e) => e.from === id).forEach((e) => { if (!(e.to in depth)) { depth[e.to] = depth[id] + 1; q.push(e.to); } });
        }
        let extra = Math.max(0, ...Object.values(depth)) + 1;
        nodes.forEach((n) => { if (!(n.id in depth)) depth[n.id] = extra; });
        const cols = {};
        nodes.forEach((n) => (cols[depth[n.id]] ||= []).push(n));
        Object.entries(cols).forEach(([d, list]) => {
            let y = 40;
            list.forEach((n) => { n.x = 40 + d * (NODE_W + 90); n.y = y; y += 110 + ports(n).length * 28 + (n.type === 'task' ? 20 : 0); });
        });
    }

    /* ---------------- Inspector ---------------- */
    const opt = (list, sel, blank) => (blank ? `<option value="">${esc(blank)}</option>` : '') + list.map(([v, l]) => `<option value="${esc(v)}" ${String(v) === String(sel) ? 'selected' : ''}>${esc(l)}</option>`).join('');
    const fieldOpts = (sel, blank = '— choose a field —') => opt(P.fields.map((f) => [f.key, f.label]), sel, blank);
    const row = (label, html, hint) => `<div class="fb-row"><label class="label">${label}</label>${html}${hint ? `<p class="fb-hint">${hint}</p>` : ''}</div>`;

    function renderInspector() {
        const n = byId(selected);
        if (!n) { inspector.innerHTML = '<div class="fb-empty"><i data-lucide="mouse-pointer-click"></i><p><strong>Click a step</strong> to edit it.</p><p>Drag from a round <em>exit</em> on the right of a step to the next step. Drop it on empty space to add one.</p></div>'; refreshIcons(); return; }
        const c = n.config, t = types[n.type];
        let h = `<div class="fb-ins-head" style="border-color:${t.color}"><i data-lucide="${t.icon}" style="color:${t.color}"></i><div><strong>${esc(t.label)}</strong><p>${esc(t.help)}</p></div></div>`;
        h += row('Name', `<input class="input" data-k="name" value="${esc(n.name)}" maxlength="120">`);

        if (n.type === 'task') {
            h += row('Instructions', `<textarea class="input" rows="3" data-c="instructions" placeholder="What should the person do?">${esc(c.instructions)}</textarea>`);
            h += row('Options (one per line)', `<textarea class="input" rows="4" data-opts>${esc((c.options || []).join('\n'))}</textarea>`, 'Each option becomes its own exit on the step.');
            h += row('Who acts here', `<div class="fb-checks">${P.users.map((u) => `<label><input type="checkbox" data-assignee="${u.id}" ${(c.assignees || []).includes(u.id) ? 'checked' : ''}> ${esc(u.name)}</label>`).join('')}</div>`, 'Nobody ticked = Client Admins.');
            h += `<div class="fb-2">${row('Overdue after (hours)', `<input type="number" class="input" min="1" max="2000" data-c="overdue_hours" data-num value="${esc(c.overdue_hours ?? '')}" placeholder="never">`)}`
                + row('Then tell', `<select class="input" data-c="escalate_to">${opt(P.users.map((u) => [u.id, u.name]), c.escalate_to, 'Client Admins')}${`<option value="previous" ${c.escalate_to === 'previous' ? 'selected' : ''}>The previous person</option>`}</select>`) + '</div>';
            h += row('Flag while here', `<select class="input" data-c="flag_field">${fieldOpts(c.flag_field, 'No flag')}</select>`, 'Sets the field to Yes on entering this step and clears it on leaving. Use it for management reports.');
            h += row('Requirements', `<div id="fb-reqs">${(c.requirements || []).map((r, i) => reqRow(r, i, c.options || [])).join('')}</div><button type="button" class="btn btn-ghost btn-sm" data-addreq>+ Add requirement</button>`, 'Block an option until a field is filled, a document type is on file, or a form is completed.');
        } else if (n.type === 'email') {
            h += row('To (addresses, comma separated)', `<input class="input" data-c="to" value="${esc(c.to)}">`);
            h += `<label class="fb-check"><input type="checkbox" data-cb="to_sender" ${c.to_sender ? 'checked' : ''}> Also the person who emailed the document in</label>`;
            h += row('Pick the address by', `<select class="input" data-c="to_field">${fieldOpts(c.to_field, 'Not used')}</select>`);
            h += row('Address for each value (Value=email)', `<textarea class="input" rows="3" data-c="to_map" placeholder="Replacement=orders@installer.com&#10;Covering=covers@installer.com">${esc(c.to_map)}</textarea>`);
            h += row('Subject', `<input class="input" data-c="subject" value="${esc(c.subject)}">`);
            h += row('Message', `<textarea class="input" rows="5" data-c="body">${esc(c.body)}</textarea>`, 'Use {document}, {link}, {today} and any index field like {contract_number}.');
            h += `<label class="fb-check"><input type="checkbox" data-cb="secure" ${c.secure ? 'checked' : ''}> Send a secure link (expires in 7 days) instead of the file</label>`;
        } else if (n.type === 'condition') {
            h += (c.rules || []).map((r, i) => `<div class="fb-rule"><div class="fb-rule-h"><input class="input" data-rule-label="${i}" value="${esc(r.label)}" placeholder="Exit name"><button type="button" class="icon-btn" data-delrule="${i}" title="Remove rule">×</button></div>`
                + (r.conds || []).map((k, j) => `<div class="fb-cond"><select class="input" data-rc="${i}.${j}.field">${fieldOpts(k.field, 'field')}</select><select class="input" data-rc="${i}.${j}.op">${opt(Object.entries(P.operators), k.op)}</select><input class="input" data-rc="${i}.${j}.value" value="${esc(k.value)}" placeholder="value"><button type="button" class="icon-btn" data-delcond="${i}.${j}">×</button></div>`).join('')
                + `<button type="button" class="btn btn-ghost btn-sm" data-addcond="${i}">+ Condition</button></div>`).join('');
            h += '<button type="button" class="btn btn-ghost btn-sm" data-addrule>+ Add rule</button><p class="fb-hint">The first rule whose conditions are all true wins. “Otherwise” catches the rest.</p>';
        } else if (n.type === 'set_index') {
            h += (c.sets || []).map((s, i) => `<div class="fb-cond fb-cond2"><select class="input" data-set="${i}.field">${fieldOpts(s.field, 'field')}</select><input class="input" data-set="${i}.value" value="${esc(s.value)}" placeholder="value, or {today}"><button type="button" class="icon-btn" data-delset="${i}">×</button></div>`).join('');
            h += '<button type="button" class="btn btn-ghost btn-sm" data-addset>+ Add value</button>';
        } else if (n.type === 'duplicate') {
            h += row('Same value in', `<select class="input" data-c="field">${fieldOpts(c.field)}</select>`, 'Another document in this project with the same value is a duplicate.');
        } else if (n.type === 'signature') {
            h += row('Signer name', `<input class="input" data-c="signer_name" value="${esc(c.signer_name)}">`);
            h += row('Signer email', `<input class="input" data-c="signer_email" value="${esc(c.signer_email)}">`);
            h += row('…or take the email from', `<select class="input" data-c="email_field">${fieldOpts(c.email_field, 'Not used')}</select>`);
            h += row('Signs in boxes with role', `<input class="input" data-c="role" value="${esc(c.role)}" placeholder="e.g. Candidate">`, 'Draw "Sign here" boxes on the document (Mark up) with this role. Leave empty to use every free box.');
            h += row('Valid for (days)', `<input type="number" class="input" min="1" max="60" data-c="days" data-num value="${esc(c.days ?? 14)}">`);
            h += row('Remind every (days)', `<input type="number" class="input" min="1" max="30" data-c="remind_days" data-num value="${esc(c.remind_days ?? 2)}">`, 'If they have not signed, they are reminded automatically (up to 3 times).');
            h += row('Message', `<textarea class="input" rows="3" data-c="message">${esc(c.message)}</textarea>`);
        } else if (n.type === 'fork') {
            h += row('Branches', `<input type="number" class="input" min="2" max="5" data-c="branches" data-num value="${esc(c.branches ?? 2)}">`, 'The first branch carries on in the same item; the others start their own items at the same time.');
        } else if (n.type === 'goto') {
            h += row('Send to', `<select class="input" data-c="workflow_id" data-num>${opt(P.workflows.map((w) => [w.id, w.name]), c.workflow_id, '— choose a workflow —')}</select>`, 'The document starts that workflow and this one finishes.');
        } else if (n.type === 'end') {
            h += row('Result shown in reports', `<input class="input" data-c="label" value="${esc(c.label)}" placeholder="Archived, Cancelled…">`);
            h += `<label class="fb-check"><input type="checkbox" data-cb="archive" ${c.archive ? 'checked' : ''}> Record as archived</label>`;
        }
        h += `<div class="fb-ins-foot"><button type="button" class="btn btn-ghost btn-sm" data-dup>Duplicate</button><button type="button" class="btn btn-ghost btn-sm text-red-600" data-del>Delete step</button></div>`;
        inspector.innerHTML = h; refreshIcons();
    }

    function reqRow(r, i, options) {
        const valueSel = r.kind === 'field' ? `<select class="input" data-req="${i}.value">${fieldOpts(r.value, 'field')}</select>`
            : r.kind === 'doc' ? `<select class="input" data-req="${i}.value">${opt(P.docTypes.map((d) => [d, d]), r.value, 'document type')}</select>`
            : `<select class="input" data-req="${i}.value">${opt(P.forms.map((f) => [f.id, f.name]), r.value, 'form')}</select>`;
        return `<div class="fb-req"><div class="fb-cond fb-cond2"><select class="input" data-req="${i}.kind">${opt([['field', 'Field filled'], ['doc', 'Document type on file'], ['form', 'Form completed']], r.kind)}</select>${valueSel}<button type="button" class="icon-btn" data-delreq="${i}">×</button></div>`
            + `<div class="fb-on">for: ${options.map((o) => `<label><input type="checkbox" data-reqon="${i}" value="${esc(o)}" ${(r.on || []).includes(o) ? 'checked' : ''}> ${esc(o)}</label>`).join('')}<small>(none ticked = every option)</small></div></div>`;
    }

    // Live edits (no re-render of the panel, so typing keeps focus)
    inspector.addEventListener('input', (e) => {
        const n = byId(selected); if (!n) return; const t = e.target; const c = n.config;
        if (t.dataset.k === 'name') n.name = t.value;
        else if (t.dataset.c) c[t.dataset.c] = t.dataset.num !== undefined ? (t.value === '' ? '' : +t.value) : t.value;
        else if (t.dataset.opts !== undefined) { c.options = t.value.split('\n').map((s) => s.trim()).filter(Boolean); pruneEdges(n); }
        else if (t.dataset.assignee) { const id = +t.dataset.assignee; c.assignees = (c.assignees || []).filter((x) => x !== id); if (t.checked) c.assignees.push(id); }
        else if (t.dataset.cb) c[t.dataset.cb] = t.checked;
        else if (t.dataset.ruleLabel !== undefined) { c.rules[+t.dataset.ruleLabel].label = t.value; pruneEdges(n); }
        else if (t.dataset.rc) { const [i, j, k] = t.dataset.rc.split('.'); c.rules[+i].conds[+j][k] = t.value; }
        else if (t.dataset.set) { const [i, k] = t.dataset.set.split('.'); c.sets[+i][k] = t.value; }
        else if (t.dataset.req) { const [i, k] = t.dataset.req.split('.'); c.requirements[+i][k] = t.value; if (k === 'kind') { c.requirements[+i].value = ''; touch(); renderNodes(); return renderInspector(); } }
        else if (t.dataset.reqon !== undefined) { const r = c.requirements[+t.dataset.reqon]; r.on = (r.on || []).filter((x) => x !== t.value); if (t.checked) r.on.push(t.value); }
        if (n.type === 'fork') pruneEdges(n);
        touch(); refreshNode(n);
    });
    inspector.addEventListener('change', (e) => { if (e.target.matches('select,[type=checkbox]')) { const n = byId(selected); n && refreshNode(n); } });

    function pruneEdges(n) { const p = ports(n); edges = edges.filter((e) => e.from !== n.id || p.includes(e.port)); }
    function refreshNode(n) {
        const el = nodeEl(n.id); if (!el) return;
        const ox = el.style.left, oy = el.style.top;
        renderNodes(); nodeEl(n.id)?.classList.add('sel'); void ox; void oy;
    }

    inspector.addEventListener('click', (e) => {
        const n = byId(selected); if (!n) return; const c = n.config; const t = e.target.closest('button'); if (!t) return;
        const d = t.dataset;
        if (d.addreq !== undefined) (c.requirements ||= []).push({ kind: 'field', value: '', on: [] });
        else if (d.delreq !== undefined) c.requirements.splice(+d.delreq, 1);
        else if (d.addrule !== undefined) c.rules.push({ label: 'Rule ' + (c.rules.length + 1), conds: [{ field: '', op: 'eq', value: '' }] });
        else if (d.delrule !== undefined) { c.rules.splice(+d.delrule, 1); pruneEdges(n); }
        else if (d.addcond !== undefined) c.rules[+d.addcond].conds.push({ field: '', op: 'eq', value: '' });
        else if (d.delcond !== undefined) { const [i, j] = d.delcond.split('.'); c.rules[+i].conds.splice(+j, 1); }
        else if (d.addset !== undefined) c.sets.push({ field: '', value: '' });
        else if (d.delset !== undefined) c.sets.splice(+d.delset, 1);
        else if (d.dup !== undefined) { const copy = addNode(n.type, n.x + 40, n.y + 60); copy.name = n.name + ' copy'; copy.config = structuredClone(c); return renderNodes(); }
        else if (d.del !== undefined) return confirmBox({ title: `Delete “${n.name}”?`, text: 'Its connections are removed too.', confirmText: 'Yes, delete', danger: true }).then((ok) => ok && removeNode(n.id));
        else return;
        touch(); renderNodes(); renderInspector();
    });

    /* ---------------- Save ---------------- */
    saveBtn.addEventListener('click', async () => {
        if (!await confirmBox({ title: 'Save this workflow?', text: 'New documents will follow the saved version. Documents already in progress keep going from where they are.', confirmText: 'Yes, save' })) return;
        saveBtn.disabled = true;
        try {
            const res = await fetch(P.saveUrl, {
                method: 'PUT', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({
                    name: $d('#fb-name').value, link_field: $d('#fb-link').value, active: $d('#fb-active').checked ? 1 : 0, auto_start: $d('#fb-auto').checked ? 1 : 0,
                    graph: { nodes, edges },
                }),
            });
            const data = await res.json().catch(() => ({}));
            if (res.status === 422 && data.errors) return notify.error('', '<ul style="text-align:left;margin:0;padding-left:1.1rem">' + Object.values(data.errors).flat().map((m) => `<li>${esc(m)}</li>`).join('') + '</ul>');
            if (!res.ok || data.ok === false) return notify.error(data.message || 'Could not save the workflow.');
            nodes = data.graph.nodes; edges = data.graph.edges; dirty = false; dirtyBadge.classList.add('hidden');
            notify.success(data.message); renderNodes(); renderInspector();
        } catch { notify.error('Could not reach the server. Try again.'); } finally { saveBtn.disabled = false; }
    });

    /* ---------------- Boot ---------------- */
    if (nodes.length > 1 && nodes.every((n) => !n.x && !n.y)) { renderNodes(); tidy(); }
    renderNodes(); applyView(); renderInspector();
    requestAnimationFrame(() => { fit(); });
}
