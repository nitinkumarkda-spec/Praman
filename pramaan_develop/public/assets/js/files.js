/* ===== js/files.js ===== */
/* =====================================================================
   PRAMAAN — attachments (PDF / JPG / JPEG / PNG).
   Files picked in the upload widget are sent with the form (attachments[]);
   the server checks and stores them outside the web root and streams them
   back from /api/attachments/{id} to users who may see the property.
   Includes the upload widget (drag & drop, list, remove) and a preview modal.
   ===================================================================== */
(function (window, document) {
    'use strict';
    const P = window.Praman;
    const DB = P.DB;
    const e = P.e, icon = P.icon;

    const ALLOWED = { pdf: 'application/pdf', jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png' };
    const MAX_SIZE = 5 * 1024 * 1024;   // 5 MB per file
    const MAX_FILES = 10;
    P.ATTACH_RULES = 'PDF, JPG, JPEG, PNG · max 5 MB each · up to ' + MAX_FILES + ' files';

    function ext(name) { const m = /\.([a-z0-9]+)$/i.exec(name || ''); return m ? m[1].toLowerCase() : ''; }
    function fmtSize(n) { return n < 1024 ? n + ' B' : n < 1048576 ? (n / 1024).toFixed(0) + ' KB' : (n / 1048576).toFixed(1) + ' MB'; }
    P.fmtSize = fmtSize;
    function kindIcon(a) { return (a.mime || '').indexOf('pdf') !== -1 ? 'fa-file-pdf' : 'fa-file-image'; }

    /** Check a picked file. Returns an error message or null. */
    function checkFile(f) {
        const x = ext(f.name);
        if (!ALLOWED[x]) { return '"' + f.name + '": only PDF, JPG, JPEG or PNG files are allowed.'; }
        if (f.type && f.type !== ALLOWED[x] && !(x === 'jpg' && f.type === 'image/jpg')) { return '"' + f.name + '": file content does not match its extension.'; }
        if (f.size > MAX_SIZE) { return '"' + f.name + '" is ' + fmtSize(f.size) + ' — the limit is 5 MB.'; }
        if (f.size === 0) { return '"' + f.name + '" is empty.'; }
        return null;
    }

    /** File bytes from the server (session cookie; the server checks access). */
    function blobOf(a) {
        return window.fetch(P.BASE + '/api/attachments/' + Number(a.attachment_id), { credentials: 'same-origin', cache: 'no-store' }).then(function (r) {
            if (!r.ok) { throw new Error(r.status === 404 ? 'File not found.' : 'Could not load the file (' + r.status + ').'); }
            return r.blob();
        });
    }

    /* ---------------- chips + viewer ---------------- */
    P.attachmentChips = function (rows) {
        if (!rows || !rows.length) { return ''; }
        return '<div class="att-chips">' + rows.map(function (a) {
            return '<button type="button" class="att-chip ' + (a.mime === 'application/pdf' ? 'pdf' : 'img') + '" data-att-view="' + a.attachment_id + '" title="' + e('View ' + a.file_name) + '">' +
                icon(kindIcon(a)) + '<span class="att-name">' + e(a.file_name) + '</span><small>' + fmtSize(a.size) + '</small></button>';
        }).join('') + '</div>';
    };
    P.attachmentsFor = function (where) { return DB.where('attachments', where).sort(function (a, b) { return a.attachment_id - b.attachment_id; }); };

    let viewerUrl = null;
    function closeViewer() {
        const m = document.getElementById('att-viewer');
        if (m) { m.remove(); }
        if (viewerUrl) { URL.revokeObjectURL(viewerUrl); viewerUrl = null; }
        document.body.classList.remove('modal-open');
    }
    function openViewer(a) {
        closeViewer();
        const uploader = DB.find('users', a.uploaded_by);
        const m = document.createElement('div');
        m.id = 'att-viewer';
        m.className = 'att-viewer';
        m.setAttribute('role', 'dialog');
        m.setAttribute('aria-modal', 'true');
        m.innerHTML = '<div class="att-viewer-box"><div class="att-viewer-head"><div>' + icon(kindIcon(a)) + ' <strong>' + e(a.file_name) + '</strong>' +
            '<small>' + fmtSize(a.size) + ' · ' + e(uploader ? uploader.name : '') + ' · ' + e(P.fmt_datetime(a.created_at)) + '</small></div>' +
            '<div class="att-viewer-actions"><a class="btn btn-light btn-sm" data-att-download hidden>' + icon('fa-download') + ' <span>Download</span></a>' +
            '<button type="button" class="btn btn-light btn-sm btn-icon-only" data-att-close aria-label="Close">' + icon('fa-xmark') + '</button></div></div>' +
            '<div class="att-viewer-body"><div class="att-loading">' + icon('fa-spinner', 'fa-spin') + ' Loading…</div></div></div>';
        document.body.appendChild(m);
        document.body.classList.add('modal-open');
        blobOf(a).then(function (blob) {
            viewerUrl = URL.createObjectURL(blob);
            const body = m.querySelector('.att-viewer-body');
            body.innerHTML = a.mime === 'application/pdf'
                ? '<iframe src="' + viewerUrl + '" title="' + e(a.file_name) + '"></iframe>'
                : '<img src="' + viewerUrl + '" alt="' + e(a.file_name) + '">';
            const dl = m.querySelector('[data-att-download]');
            dl.href = viewerUrl; dl.download = a.file_name; dl.hidden = false;
        }).catch(function (err) {
            m.querySelector('.att-viewer-body').innerHTML = '<div class="att-loading">' + icon('fa-triangle-exclamation') + ' ' + e(err.message) + '</div>';
        });
    }
    document.addEventListener('click', function (ev) {
        const v = ev.target.closest('[data-att-view]');
        if (v) {
            ev.preventDefault();
            const a = DB.find('attachments', v.getAttribute('data-att-view'));
            if (a) { openViewer(a); } else { P.toast('error', 'Attachment not found.'); }
            return;
        }
        if (ev.target.closest('[data-att-close]') || ev.target.id === 'att-viewer') { closeViewer(); }
    });
    document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape' && document.getElementById('att-viewer')) { closeViewer(); } });
    window.addEventListener('hashchange', closeViewer);

    /* ---------------- upload widget ---------------- */
    P.uploaderHtml = function (label) {
        return '<div class="form-group"><label class="form-label">' + e(label || 'Attachments') + ' <small class="text-muted">(optional)</small></label>' +
            '<div class="upload-zone" data-upload-zone tabindex="0" role="button" aria-label="Add attachments">' +
            '<input type="file" multiple accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" data-upload-input hidden>' +
            '<span class="upload-icon">' + icon('fa-cloud-arrow-up') + '</span><div><strong>Drag &amp; drop files here</strong> or <span class="upload-link">browse</span>' +
            '<small>' + P.ATTACH_RULES + '</small></div></div><ul class="upload-list" data-upload-list></ul></div>';
    };
    /** Wire the widget inside form; files are kept on form._files. */
    P.initUploader = function (form) {
        const zone = form.querySelector('[data-upload-zone]');
        if (!zone) { return; }
        const input = zone.querySelector('[data-upload-input]');
        const list = form.querySelector('[data-upload-list]');
        form._files = [];
        function draw() {
            list.innerHTML = form._files.map(function (f, i) {
                const isImg = /^image\//.test(f.type) || /\.(jpe?g|png)$/i.test(f.name);
                return '<li class="upload-item"><span class="upload-thumb ' + (isImg ? 'img' : 'pdf') + '">' + (isImg && f._thumb ? '<img src="' + f._thumb + '" alt="">' : icon(isImg ? 'fa-file-image' : 'fa-file-pdf')) + '</span>' +
                    '<span class="upload-meta"><strong>' + e(f.name) + '</strong><small>' + fmtSize(f.size) + '</small></span>' +
                    '<button type="button" class="btn btn-light btn-sm btn-icon-only text-danger" data-upload-remove="' + i + '" title="Remove" aria-label="' + e('Remove ' + f.name) + '">' + icon('fa-trash-can') + '</button></li>';
            }).join('');
            zone.classList.toggle('has-files', form._files.length > 0);
        }
        function add(files) {
            const errs = [];
            Array.prototype.forEach.call(files, function (f) {
                const err = checkFile(f);
                if (err) { errs.push(err); return; }
                if (form._files.some(function (x) { return x.name.toLowerCase() === f.name.toLowerCase(); })) { errs.push('"' + f.name + '" is already added (a file with the same name).'); return; }
                if (form._files.length >= MAX_FILES) { errs.push('You can attach at most ' + MAX_FILES + ' files.'); return; }
                form._files.push(f);
                if (/^image\//.test(f.type)) {
                    const url = URL.createObjectURL(f);
                    f._thumb = url;
                }
            });
            if (errs.length) { P.toast('error', errs.slice(0, 3).join(' ')); }
            draw();
        }
        zone.addEventListener('click', function () { input.click(); });
        zone.addEventListener('keydown', function (ev) { if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); input.click(); } });
        input.addEventListener('change', function () { add(input.files); input.value = ''; });
        ['dragenter', 'dragover'].forEach(function (t) { zone.addEventListener(t, function (ev) { ev.preventDefault(); zone.classList.add('drag'); }); });
        ['dragleave', 'drop'].forEach(function (t) { zone.addEventListener(t, function (ev) { ev.preventDefault(); zone.classList.remove('drag'); }); });
        zone.addEventListener('drop', function (ev) { add(ev.dataTransfer.files); });
        list.addEventListener('click', function (ev) {
            const b = ev.target.closest('[data-upload-remove]');
            if (!b) { return; }
            const f = form._files.splice(Number(b.getAttribute('data-upload-remove')), 1)[0];
            if (f && f._thumb) { URL.revokeObjectURL(f._thumb); }
            draw();
        });
        P._addFiles = add; // used by automated tests
    };
})(window, document);
