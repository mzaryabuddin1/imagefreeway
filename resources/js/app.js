import Swal from 'sweetalert2';
import {
    createIcons, LayoutDashboard, Building2, Users, Palette, ScrollText, Bell, BookOpen, LogOut, Menu, X, Plus,
    Pencil, Trash2, Archive, ArchiveRestore, Search, Check, ChevronDown, Upload, PartyPopper, UserPlus, Info,
    TriangleAlert, CircleCheck, CircleX, Pause, Play, Globe, ShieldCheck, FileText, Sparkles, Phone, Mail, Eye,
    FolderOpen, Layers, Settings, Download, ArrowLeft, ArrowRight, ChevronUp, RotateCcw, GitBranch, BarChart3, Clock,
    UserCheck, Split, Tag, Copy, PenLine, GitFork, Forward, CircleStop, MousePointerClick, ZoomIn, ZoomOut, Maximize, LayoutGrid, Save, FileSignature,
    Flag, Send, History, Circle, Camera, Type, Folder, File, Highlighter, Eraser, MessageSquare, ChevronLeft, ChevronRight, ShieldAlert, Activity, FileSpreadsheet, FileImage, Presentation, List,
} from 'lucide';
import { initFlowBuilder } from './flow-builder.js';
import { initFormBuilder } from './form-builder.js';
import { initMarkup } from './markup.js';
import { initCharts } from './charts.js';
import { initSuggest } from './suggest.js';
import { initTour } from './tour.js';
import { initSigners, initSignMethods } from './signing.js';

const icons = {
    LayoutDashboard, Building2, Users, Palette, ScrollText, Bell, BookOpen, LogOut, Menu, X, Plus, Pencil, Trash2,
    Archive, ArchiveRestore, Search, Check, ChevronDown, Upload, PartyPopper, UserPlus, Info, TriangleAlert,
    CircleCheck, CircleX, Pause, Play, Globe, ShieldCheck, FileText, Sparkles, Phone, Mail, Eye,
    FolderOpen, Layers, Settings, Download, ArrowLeft, ArrowRight, ChevronUp, GitBranch, BarChart3, Clock,
    UserCheck, Split, Tag, Copy, PenLine, GitFork, Forward, CircleStop, MousePointerClick, ZoomIn, ZoomOut, Maximize, LayoutGrid, Save, FileSignature,
    Flag, Send, History, Circle, Camera, Type, Folder, File, Highlighter, Eraser, MessageSquare, ChevronLeft, ChevronRight, ShieldAlert, Activity, FileSpreadsheet, FileImage, Presentation, List,
};

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;
const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];
const refreshIcons = () => createIcons({ icons });

const swalTheme = { customClass: { popup: 'ifw-swal', confirmButton: 'ifw-swal-btn', cancelButton: 'ifw-swal-btn ifw-swal-cancel', title: 'ifw-swal-title' }, buttonsStyling: false };

/* ---------- SweetAlert helpers (all success / failure messages go through Swal) ---------- */
const cssVar = (n) => getComputedStyle(document.documentElement).getPropertyValue(n).trim();

export const notify = {
    success: (text) => Swal.fire({ ...swalTheme, toast: true, position: 'top-end', icon: 'success', title: text, showConfirmButton: false, timer: 2600, timerProgressBar: true }),
    error: (text, html) => Swal.fire({ ...swalTheme, icon: 'error', title: 'Something went wrong', text: html ? undefined : text, html, confirmButtonText: 'OK' }),
    info: (text) => Swal.fire({ toast: true, position: 'top-end', icon: 'info', title: text, showConfirmButton: false, timer: 2600 }),
};

const confirmBox = ({ title, text, confirmText, danger }) => Swal.fire({
    ...swalTheme,
    title: title || 'Are you sure?',
    text: text || '',
    icon: danger ? 'warning' : 'question',
    showCancelButton: true,
    confirmButtonText: confirmText || 'Yes, continue',
    cancelButtonText: 'Cancel',
    reverseButtons: true,
    focusCancel: !!danger,
    customClass: { ...swalTheme.customClass, confirmButton: 'ifw-swal-btn' + (danger ? ' ifw-swal-danger' : '') },
}).then((r) => r.isConfirmed);

/* ---------- AJAX page navigation (no full reloads) ---------- */
const bar = () => $('#ajax-bar');
let barTimer;
const barStart = () => { const b = bar(); if (!b) return; clearTimeout(barTimer); b.classList.add('on'); b.style.width = '35%'; setTimeout(() => (b.style.width = '70%'), 150); };
const barDone = () => { const b = bar(); if (!b) return; b.style.width = '100%'; barTimer = setTimeout(() => { b.classList.remove('on'); b.style.width = '0'; }, 250); };

let navToken = 0;

async function navigate(url, { push = true, scroll = true } = {}) {
    const token = ++navToken;
    barStart();
    try {
        const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' }, credentials: 'same-origin', redirect: 'follow' });
        if (token !== navToken) return;
        if (res.status === 401 || res.status === 419) return (location.href = '/login');
        const html = await res.text();
        const doc = new DOMParser().parseFromString(html, 'text/html');
        // The response is a different shell (e.g. login page) or a non-HTML error: do a real load.
        if (!doc.querySelector('#page') || !$('#page')) {
            if (res.status === 403 || res.status === 404 || res.status >= 500) { barDone(); return showHttpError(res.status, doc); }
            return (location.href = res.url || url);
        }
        applyShell(doc);
        $('#page').innerHTML = doc.querySelector('#page').innerHTML;
        document.title = doc.title;
        if (push) history.pushState({}, '', res.url || url);
        if (scroll) window.scrollTo({ top: 0 });
        afterSwap();
    } catch (e) {
        console.error(e);
        notify.error(e instanceof TypeError && /fetch|network/i.test(e.message) ? 'Could not reach the server. Check your connection and try again.' : 'Something went wrong on this page: ' + (e?.message || 'unknown error') + '. Please reload and try again.');
    } finally {
        barDone();
    }
}

function showHttpError(status, doc) {
    const msg = doc.querySelector('.swal-message')?.textContent?.trim()
        || (status === 403 ? 'You do not have permission to open this page.' : status === 404 ? 'That page could not be found.' : 'The server hit a problem. Please try again.');
    notify.error(msg);
}

/** Keep sidebar (permissions, branding colours) in sync with the server-rendered shell. */
function applyShell(doc) {
    const sidebar = doc.querySelector('#sidebar-nav');
    if (sidebar && $('#sidebar-nav')) $('#sidebar-nav').innerHTML = sidebar.innerHTML;
    const style = doc.querySelector('#brand-vars');
    if (style && $('#brand-vars')) $('#brand-vars').textContent = style.textContent;
}

function initPage() {
    const helpers = { notify, confirmBox, csrf, refreshIcons };
    const fb = $('[data-flow-builder]'); if (fb) initFlowBuilder(fb, helpers);
    const ff = $('[data-form-builder]'); if (ff) initFormBuilder(ff, helpers);
    initMarkup();
    initCharts();
    initSuggest(refreshIcons);
    initSigPads();
    initSigners(refreshIcons);
    initSignMethods(refreshIcons);
    initTour(refreshIcons);
}

function afterSwap() {
    refreshIcons();
    initPage();
    setActiveNav();
    closeMenus();
    $('#sidebar')?.classList.add('-translate-x-full');
    $('#sidebar-backdrop')?.classList.add('hidden');
    $('#page [autofocus]')?.focus();
    pollNotifications();
}

function setActiveNav() {
    const path = location.pathname.replace(/\/$/, '') || '/';
    $$('#sidebar-nav a[data-nav]').forEach((a) => {
        const p = new URL(a.href).pathname.replace(/\/$/, '') || '/';
        const on = p === '/' ? path === '/' : path === p || path.startsWith(p + '/');
        a.classList.toggle('active', on);
    });
}

window.addEventListener('popstate', () => navigate(location.href, { push: false, scroll: false }));

document.addEventListener('click', (e) => {
    const a = e.target.closest('a[href]');
    if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    if (a.target && a.target !== '_self' || a.hasAttribute('download') || a.hasAttribute('data-no-ajax')) return;
    const url = new URL(a.href, location.href);
    if (url.origin !== location.origin || url.hash && url.pathname === location.pathname) return;
    if (!$('#page')) return;
    e.preventDefault();
    navigate(url.href);
});

/* ---------- AJAX forms, with confirmation modal for save / delete / archive ---------- */
document.addEventListener('submit', async (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-no-ajax') || e.defaultPrevented) return;
    e.preventDefault();

    const method = (form.getAttribute('method') || 'get').toLowerCase();
    if (method === 'get') {
        const q = new URLSearchParams(new FormData(form)); [...q.keys()].forEach((k) => !q.get(k) && q.delete(k));
        return navigate(form.action.split('?')[0] + (q.toString() ? '?' + q : ''));
    }

    if (!form.hasAttribute('data-no-confirm')) {
        const danger = form.hasAttribute('data-danger');
        const ok = await confirmBox({
            title: form.dataset.confirmTitle || (danger ? 'Please confirm' : 'Save changes?'),
            text: form.dataset.confirm || (danger ? 'This action cannot be undone.' : 'Do you want to save these changes?'),
            confirmText: form.dataset.confirmButton || (danger ? 'Yes, do it' : 'Yes, save'),
            danger,
        });
        if (!ok) return;
    }

    const inline = form.hasAttribute('data-inline');
    clearErrors(form);
    const body = new FormData(form, e.submitter || undefined);   // built BEFORE the button is disabled, or its name/value (outcome) is lost
    const btn = e.submitter || $('button[type=submit]', form);
    btn && (btn.disabled = true);
    barStart();
    try {
        const res = await fetch(form.action, { method: 'POST', body, headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() }, credentials: 'same-origin' });
        const data = await res.json().catch(() => ({}));

        if (res.status === 419) { await Swal.fire({ ...swalTheme, icon: 'warning', title: 'Session expired', text: 'Please sign in again.' }); return (location.href = '/login'); }
        if (res.status === 401) { if (data.message) await notify.error(data.message); return (location.href = data.redirect || '/login'); }
        if (inline && (res.status === 422 || !res.ok || data.ok === false)) {
            if (data.errors) showFieldErrors(form, data.errors);
            else showFormError(form, data.message || 'The request could not be completed.');
            return;
        }
        if (res.status === 422 && data.errors) {
            showFieldErrors(form, data.errors);
            return notify.error('', '<ul style="text-align:left;margin:0;padding-left:1.1rem">' + Object.values(data.errors).flat().map((m) => `<li>${escapeHtml(m)}</li>`).join('') + '</ul>');
        }
        if (!res.ok || data.ok === false) return notify.error(data.message || 'The request could not be completed.');

        notify.success(data.message || 'Saved.');
        if (data.redirect) {
            const target = new URL(data.redirect, location.href);
            // Login / logout swap the whole shell: do a real page load there.
            if (/\/login$/.test(target.pathname) || /\/login$/.test(location.pathname)) return (location.href = target.href);
            return navigate(target.href);
        }
        if (data.reload !== false) navigate(location.href, { push: false, scroll: false });
    } catch (err) {
        console.error(err); notify.error(err instanceof TypeError && /fetch|network/i.test(err.message) ? 'Could not reach the server. Check your connection and try again.' : 'Something went wrong on this page: ' + (err?.message || 'unknown error') + '. Please reload and try again.');
    } finally {
        btn && (btn.disabled = false);
        barDone();
    }
});

const escapeHtml = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

function showFormError(form, msg) {
    const box = $('[data-form-error]', form);
    if (box) { box.textContent = msg; box.classList.remove('hidden'); }
    const pw = form.elements['password'];
    if (pw) { pw.classList.add('border-red-500'); pw.focus(); pw.select?.(); }
}
function clearErrors(form) { $('[data-form-error]', form)?.classList.add('hidden'); $$('.field-error', form).forEach((n) => n.remove()); $$('.border-red-500', form).forEach((n) => n.classList.remove('border-red-500')); }
function showFieldErrors(form, errors) {
    Object.entries(errors).forEach(([name, msgs]) => {
        // Laravel reports "values.amount"; the input is named "values[amount]"
        const bracket = name.replace(/\.([^.]+)/g, '[$1]');
        let field = form.elements[name] || form.elements[bracket] || form.elements[name + '[]'];
        if (field && !(field instanceof Element)) field = field[0];   // several controls share the name (e.g. Approve / Reject)
        if (!field || !field.closest) return;
        field.classList?.add('border-red-500');
        const p = document.createElement('p'); p.className = 'field-error'; p.textContent = msgs[0];
        field.closest('div')?.appendChild(p);
    });
}

/* ---------- Bulk upload: drag & drop, per-file progress, one failure never stops the rest ---------- */
const MAX_PARALLEL = 3;

function uploadFiles(zone, files) {
    const list = $('#upload-list', zone);
    const rows = [...files].map((file) => {
        const li = document.createElement('li');
        li.className = 'rounded-lg border border-slate-200 bg-white p-2.5 text-sm';
        li.innerHTML = `<div class="flex items-center justify-between gap-3"><span class="truncate font-medium"></span><span class="shrink-0 text-xs text-slate-500">Waiting…</span></div>
            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full w-0 bg-brand transition-all"></div></div>`;
        li.querySelector('span').textContent = file.name;
        list.appendChild(li);
        return { file, li, state: li.querySelector('span:nth-child(2)'), bar: li.querySelector('.bg-brand') };
    });

    let next = 0, active = 0, ok = 0, failed = 0;
    const startOne = () => {
        while (active < MAX_PARALLEL && next < rows.length) { send(rows[next++]); active++; }
    };
    const finish = () => {
        active--;
        if (next < rows.length) return startOne();
        if (active > 0) return;
        if (ok) notify.success(`${ok} ${ok === 1 ? 'file' : 'files'} uploaded` + (failed ? `, ${failed} failed` : '.'));
        if (failed && !ok) notify.error(`${failed} ${failed === 1 ? 'file' : 'files'} could not be uploaded.`);
        if (ok) setTimeout(() => navigate(location.href, { push: false, scroll: false }), failed ? 3500 : 900);
    };

    function send(r) {
        const fd = new FormData(); fd.append('file', r.file);
        const xhr = new XMLHttpRequest();
        xhr.open('POST', zone.dataset.url);
        xhr.setRequestHeader('X-CSRF-TOKEN', csrf()); xhr.setRequestHeader('Accept', 'application/json'); xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.upload.onprogress = (e) => { if (e.lengthComputable) { const p = Math.round((e.loaded / e.total) * 100); r.bar.style.width = p + '%'; r.state.textContent = p + '%'; } };
        xhr.onload = () => {
            let d = {}; try { d = JSON.parse(xhr.responseText); } catch (e) {}
            if (xhr.status >= 200 && xhr.status < 300 && d.ok) { ok++; r.bar.style.width = '100%'; r.state.textContent = 'Done'; r.state.className = 'shrink-0 text-xs font-semibold text-emerald-600'; }
            else { failed++; r.bar.classList.replace('bg-brand', 'bg-red-500'); r.bar.style.width = '100%'; r.state.textContent = d.errors?.file?.[0] || d.message || 'Failed'; r.state.className = 'shrink-0 text-xs font-semibold text-red-600'; }
            finish();
        };
        xhr.onerror = () => { failed++; r.state.textContent = 'Network error'; r.state.className = 'shrink-0 text-xs font-semibold text-red-600'; finish(); };
        xhr.send(fd);
    }
    startOne();
}

document.addEventListener('change', (e) => {
    const inp = e.target.closest('input[data-upload]');
    if (inp?.files.length) { uploadFiles(inp.closest('[data-dropzone]'), inp.files); inp.value = ''; }
});
['dragover', 'dragenter'].forEach((ev) => document.addEventListener(ev, (e) => { const z = e.target.closest?.('[data-dropzone]'); if (z) { e.preventDefault(); z.classList.add('drag'); } }));
['dragleave', 'drop'].forEach((ev) => document.addEventListener(ev, (e) => { const z = e.target.closest?.('[data-dropzone]'); if (z) { e.preventDefault(); z.classList.remove('drag'); if (ev === 'drop' && e.dataTransfer.files.length) uploadFiles(z, e.dataTransfer.files); } }));

/* ---------- Small UI behaviours ---------- */
function closeMenus() { $$('[data-menu-panel]').forEach((p) => p.classList.add('hidden')); }
document.addEventListener('click', (e) => {
    const t = e.target.closest('[data-menu]');
    if (t) { const p = $(`[data-menu-panel="${t.dataset.menu}"]`); const open = p.classList.contains('hidden'); closeMenus(); p.classList.toggle('hidden', !open); if (open && t.dataset.menu === 'notif') pollNotifications(); return; }
    if (!e.target.closest('[data-menu-panel]')) closeMenus();
    if (e.target.closest('[data-sidebar-toggle]')) { $('#sidebar').classList.toggle('-translate-x-full'); $('#sidebar-backdrop').classList.toggle('hidden'); }
    if (e.target.closest('#sidebar-backdrop')) { $('#sidebar').classList.add('-translate-x-full'); $('#sidebar-backdrop').classList.add('hidden'); }
});

// Live colour preview + hex sync for colour pickers
document.addEventListener('input', (e) => {
    const el = e.target;
    if (el.matches('input[type=color][data-hex]')) { const t = $(el.dataset.hex); if (t) t.value = el.value; }
    if (el.matches('input[data-color]') && /^#[0-9a-f]{6}$/i.test(el.value)) { const p = $(el.dataset.color); if (p) p.value = el.value; }
    if (el.matches('input[name=name][data-slug]')) { const s = $(el.dataset.slug); if (s && !s.dataset.touched) s.value = el.value.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''); }
    if (el.matches('input[name=slug]')) el.dataset.touched = '1';
    if (el.matches('input[type=file][data-preview]')) { const f = el.files[0]; const img = $(el.dataset.preview); if (f && img) img.src = URL.createObjectURL(f); }
});

/* ---------- Notifications bell ---------- */
async function pollNotifications() {
    if (!$('#notif-badge')) return;
    try {
        const res = await fetch('/notifications/feed', { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
        if (!res.ok) return;
        const d = await res.json();
        const badge = $('#notif-badge');
        badge.textContent = d.unread > 9 ? '9+' : d.unread;
        badge.classList.toggle('hidden', d.unread === 0);
        const tone = { success: 'text-emerald-600 bg-emerald-50', warning: 'text-amber-600 bg-amber-50', danger: 'text-red-600 bg-red-50', info: 'text-brand bg-brand-soft' };
        $('#notif-list').innerHTML = d.items.length ? d.items.map((n) => `
            <a href="${escapeHtml(n.url || '/notifications')}" data-notif="${n.id}" class="flex gap-3 px-4 py-3 hover:bg-slate-50 ${n.read ? '' : 'bg-brand-soft/40'}">
                <span class="mt-0.5 inline-flex size-8 shrink-0 items-center justify-center rounded-full ${tone[n.level] || tone.info}"><i data-lucide="${escapeHtml(n.icon)}"></i></span>
                <span class="min-w-0"><span class="block text-sm font-semibold text-slate-800">${escapeHtml(n.title)}</span>
                <span class="block truncate text-xs text-slate-500">${escapeHtml(n.message)}</span>
                <span class="text-[11px] text-slate-400">${escapeHtml(n.time)}</span></span>
            </a>`).join('') : '<p class="px-4 py-8 text-center text-sm text-slate-400">No notifications yet</p>';
        refreshIcons();
    } catch (e) { /* silent: polling only */ }
}
document.addEventListener('click', (e) => {
    const n = e.target.closest('[data-notif]');
    if (n) fetch(`/notifications/${n.dataset.notif}/read`, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' }, credentials: 'same-origin' }).then(pollNotifications);
});
setInterval(() => document.visibilityState === 'visible' && pollNotifications(), 30000);

/* ---------- Copy a link to the clipboard ---------- */
document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-copy]');
    if (!b) return;
    const done = () => notify.success('Link copied.');
    if (navigator.clipboard?.writeText) navigator.clipboard.writeText(b.dataset.copy).then(done, () => window.prompt('Copy this link:', b.dataset.copy));
    else window.prompt('Copy this link:', b.dataset.copy);
});

/* ---------- Signature pad (draw with mouse, finger or pen) ---------- */
function initSigPads() {
    $$('[data-sigpad]').forEach((box) => {
        if (box.dataset.ready) return;
        box.dataset.ready = '1';
        const cv = $('canvas', box), out = $('input[type=hidden]', box), ctx = cv.getContext('2d');
        let sized = false;
        const size = () => {   // the pad may start hidden (inside a closed <details>), so size it on first use
            const r0 = cv.getBoundingClientRect(), ratio = window.devicePixelRatio || 1;
            if (!r0.width) return false;
            cv.width = r0.width * ratio; cv.height = r0.height * ratio;
            ctx.scale(ratio, ratio); ctx.lineWidth = 2.2; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#0f172a';
            return (sized = true);
        };
        size();
        let down = false, drew = false;
        const pos = (e) => { const r = cv.getBoundingClientRect(); return [e.clientX - r.left, e.clientY - r.top]; };
        cv.addEventListener('pointerdown', (e) => { if (!sized && !size()) return; down = true; cv.setPointerCapture(e.pointerId); ctx.beginPath(); ctx.moveTo(...pos(e)); e.preventDefault(); });
        cv.addEventListener('pointermove', (e) => { if (!down) return; ctx.lineTo(...pos(e)); ctx.stroke(); drew = true; });
        const end = () => { if (!down) return; down = false; if (drew) { out.value = cv.toDataURL('image/png'); box.dispatchEvent(new CustomEvent('sigchange', { bubbles: true, detail: out.value })); } };
        cv.addEventListener('pointerup', end);
        cv.addEventListener('pointercancel', end);
        $('[data-sig-clear]', box)?.addEventListener('click', () => { ctx.save(); ctx.setTransform(1, 0, 0, 1, 0, 0); ctx.clearRect(0, 0, cv.width, cv.height); ctx.restore(); out.value = ''; drew = false; box.dispatchEvent(new CustomEvent('sigchange', { bubbles: true, detail: '' })); });
    });
}

/* ---------- Show the drawn signature inside the signing boxes ---------- */
document.addEventListener('sigchange', (e) => {
    document.querySelectorAll('[data-sigbox]').forEach((b) => {
        b.classList.toggle('is-filled', !!e.detail);
        b.style.backgroundImage = e.detail ? `url(${e.detail})` : '';
    });
});

/* ---------- Boot ---------- */
document.addEventListener('DOMContentLoaded', () => {
    refreshIcons();
    initPage();
    setActiveNav();
    pollNotifications();
});

window.IFW = { navigate, notify };
