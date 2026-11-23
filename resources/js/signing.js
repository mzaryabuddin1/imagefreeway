/* Staff side of eSignature: signer rows, bulk mode, role colours, timeline filter. */
export const roleHue = (role) => ([...String(role || '')].reduce((a, c) => a + c.charCodeAt(0), 0) * 47) % 360;
export const roleColor = (role, l = 42) => `hsl(${roleHue(role)} 70% ${l}%)`;

export function initSigners(refreshIcons) {
    document.querySelectorAll('[data-role-chip]').forEach((c) => { c.style.background = roleColor(c.dataset.roleChip); });

    const root = document.querySelector('[data-esign]');
    if (root && !root.dataset.ready) {
        root.dataset.ready = '1';
        const rows = root.querySelector('#signer-rows');
        const tpl = root.querySelector('#signer-template');
        let next = 1;

        const wire = (row) => {
            const sel = row.querySelector('[data-signer-user]');
            sel?.addEventListener('change', () => {
                const o = sel.selectedOptions[0];
                row.querySelector('[data-signer-name]').value = o?.dataset.name || '';
                row.querySelector('[data-signer-email]').value = o?.dataset.email || '';
            });
        };
        wire(rows.querySelector('[data-signer-row]'));

        root.addEventListener('click', (e) => {
            if (e.target.closest('[data-add-signer]')) {
                if (rows.children.length >= 8) return;
                const wrap = document.createElement('div');
                wrap.innerHTML = tpl.innerHTML.replaceAll('__i__', next++);
                const row = wrap.firstElementChild;
                rows.appendChild(row);
                wire(row);
                refreshIcons?.();
            }
            const rm = e.target.closest('[data-remove-signer]');
            if (rm) {
                const row = rm.closest('[data-signer-row]');
                if (rows.children.length > 1) row.remove();
                else row.querySelectorAll('input,select').forEach((i) => { i.value = ''; });
            }
        });

        const many = root.querySelector('[data-esign-many]');
        root.querySelectorAll('[data-esign-mode]').forEach((r) => r.addEventListener('change', () => {
            const on = root.querySelector('[data-esign-mode]:checked').value === 'many';
            many.classList.toggle('hidden', !on);
            root.querySelector('[data-esign-one-label]').classList.toggle('hidden', on);
            root.querySelector('[data-esign-many-label]').classList.toggle('hidden', !on);
        }));
    }

    const tl = document.querySelector('[data-timeline-views]');
    if (tl && !tl.dataset.ready) {
        tl.dataset.ready = '1';
        tl.addEventListener('change', () => document.querySelectorAll('[data-timeline] [data-view="1"]').forEach((li) => li.classList.toggle('hidden', !tl.checked)));
    }
}

/* ---------- Signer side: draw, type, upload or take a photo of a signature ---------- */
const MAXW = 600;
const MAXH = 200;

/** Fits a picture into the signature size and turns the white paper transparent. */
function toSignaturePng(src, w, h) {
    const cv = document.createElement('canvas');
    const scale = Math.min(MAXW / w, MAXH / h, 1);
    cv.width = Math.max(1, Math.round(w * scale));
    cv.height = Math.max(1, Math.round(h * scale));
    const ctx = cv.getContext('2d');
    ctx.drawImage(src, 0, 0, cv.width, cv.height);
    const img = ctx.getImageData(0, 0, cv.width, cv.height);
    const d = img.data;
    for (let i = 0; i < d.length; i += 4) {
        const lum = (d[i] + d[i + 1] + d[i + 2]) / 3;
        if (lum > 215) d[i + 3] = 0;
        else if (lum > 150) d[i + 3] = Math.round(d[i + 3] * (215 - lum) / 65);
        d[i] = d[i + 1] = d[i + 2] = Math.min(lum, 60);
    }
    ctx.putImageData(img, 0, 0);
    let url = cv.toDataURL('image/png');
    if (url.length > 380000) { const c2 = document.createElement('canvas'); c2.width = cv.width / 2; c2.height = cv.height / 2; c2.getContext('2d').drawImage(cv, 0, 0, c2.width, c2.height); url = c2.toDataURL('image/png'); }
    return url;
}

export function initSignMethods(refreshIcons) {
    const root = document.querySelector('[data-sigmethods]');
    if (!root || root.dataset.ready) return;
    root.dataset.ready = '1';
    const out = root.querySelector('[data-sig-value]');
    const methodInput = root.querySelector('[data-sig-method]');
    const vals = { draw: '', type: '', upload: '', camera: '' };
    let active = 'draw';

    const publish = () => {
        out.value = vals[active];
        root.dispatchEvent(new CustomEvent('sigchange', { bubbles: true, detail: vals[active] }));
    };
    // The draw pad reports into vals.draw; the page-wide listener then sees only the active method's value
    root.querySelector('[data-sigpad]').addEventListener('sigchange', (e) => { e.stopPropagation(); vals.draw = e.detail; if (active === 'draw') publish(); });

    const stopCam = () => { const v = root.querySelector('[data-cam-video]'); v.srcObject?.getTracks().forEach((t) => t.stop()); v.srcObject = null; v.classList.add('hidden'); root.querySelector('[data-cam-snap]').classList.add('hidden'); };

    root.querySelectorAll('[data-sm]').forEach((b) => b.addEventListener('click', () => {
        active = b.dataset.sm;
        methodInput.value = active;
        root.querySelectorAll('[data-sm]').forEach((x) => x.classList.toggle('is-on', x === b));
        root.querySelectorAll('[data-pane]').forEach((p) => p.classList.toggle('hidden', p.dataset.pane !== active));
        if (active !== 'camera') stopCam();
        publish();
    }));

    // Type
    const typeIn = root.querySelector('[data-type-input]');
    const typeCv = root.querySelector('[data-type-preview]');
    typeIn.addEventListener('input', () => {
        const ctx = typeCv.getContext('2d');
        ctx.clearRect(0, 0, typeCv.width, typeCv.height);
        const t = typeIn.value.trim();
        if (t) {
            let size = 90;
            ctx.fillStyle = '#0f172a';
            ctx.textBaseline = 'middle';
            do { ctx.font = `${size}px "Segoe Script","Brush Script MT","Lucida Handwriting","Snell Roundhand",cursive`; size -= 6; } while (ctx.measureText(t).width > typeCv.width - 40 && size > 24);
            ctx.fillText(t, 20, typeCv.height / 2);
            vals.type = typeCv.toDataURL('image/png');
        } else vals.type = '';
        publish();
    });

    // Upload
    const up = root.querySelector('[data-upload-input]');
    const upPrev = root.querySelector('[data-upload-preview]');
    up.addEventListener('change', () => {
        const f = up.files[0];
        if (!f) { vals.upload = ''; upPrev.classList.add('hidden'); return publish(); }
        const im = new Image();
        im.onload = () => { vals.upload = toSignaturePng(im, im.naturalWidth, im.naturalHeight); upPrev.src = vals.upload; upPrev.classList.remove('hidden'); URL.revokeObjectURL(im.src); publish(); };
        im.onerror = () => { vals.upload = ''; publish(); };
        im.src = URL.createObjectURL(f);
    });

    // Camera
    const vid = root.querySelector('[data-cam-video]');
    const snap = root.querySelector('[data-cam-snap]');
    const msg = root.querySelector('[data-cam-msg]');
    const camPrev = root.querySelector('[data-cam-preview]');
    root.querySelector('[data-cam-start]').addEventListener('click', async () => {
        try {
            vid.srcObject = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
            vid.classList.remove('hidden'); snap.classList.remove('hidden'); msg.textContent = 'Hold your signature on white paper in view, then press Capture.';
        } catch { msg.textContent = 'The camera could not be opened. Allow camera access in your browser, or use Upload instead.'; }
    });
    snap.addEventListener('click', () => {
        if (!vid.videoWidth) return;
        vals.camera = toSignaturePng(vid, vid.videoWidth, vid.videoHeight);
        camPrev.src = vals.camera; camPrev.classList.remove('hidden');
        stopCam();
        publish();
    });
    refreshIcons?.();
}
