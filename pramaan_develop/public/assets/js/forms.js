/* ===== js/forms.js ===== */
/* =====================================================================
   PRAMAAN — page-specific form behaviour (all hooked via data-* attributes)
   Sectors come from the data the server sent (core: PramanDB).
     • data-uppercase           live upper-casing (codes, plot no.)
     • data-panel-select        User Master: show Role only for Role Users
     • data-scheme-form         sector repeater, active count, mandatory-role check
     • data-property-form       scheme → sectors (local DB), Yes/No confirmation, area preview
     • data-bulk-form           OIC bulk send: reason required for Not Ready rows
     • data-simulate-form       Reject needs a remark
   ===================================================================== */
(function (window, document) {
    'use strict';

    const Praman = window.Praman = window.Praman || {};
    const V = function () { return Praman.Validation; };

    /* ---------- uppercase inputs ---------- */
    document.addEventListener('input', function (e) {
        const el = e.target;
        if (el.matches && el.matches('input[data-uppercase]:not([readonly])')) {
            const pos = el.selectionStart;
            el.value = el.value.toUpperCase();
            try { el.setSelectionRange(pos, pos); } catch (err) { /* number inputs */ }
        }
        // names of persons: letters and single spaces only; data-digits-only: whole numbers;
        // data-decimals="n": digits with at most n decimals (data-signed allows a leading minus) — anything else is dropped as typed
        const filter = !el.matches ? null
            : el.matches('input[data-letters-only]') ? function (v) { return v.replace(/[^\p{L}\p{M} ]/gu, '').replace(/ {2,}/g, ' ').replace(/^ /, ''); }
            : el.matches('input[data-digits-only]') ? function (v) { return v.split(/[.,]/)[0].replace(/\D/g, ''); } // "40.5" -> "40", never "405"
            : el.matches('input[data-decimals]') ? function (v) {
                const n = Number(el.getAttribute('data-decimals'));
                const neg = el.hasAttribute('data-signed') && /^\s*-/.test(v);
                const parts = v.replace(/[^\d.]/g, '').split('.');
                return (neg ? '-' : '') + parts[0] + (parts.length > 1 ? '.' + parts.slice(1).join('').slice(0, n) : '');
            } : null;
        if (filter) {
            const clean = filter(el.value);
            if (clean !== el.value) {
                const pos = Math.max(0, el.selectionStart - (el.value.length - clean.length));
                el.value = clean;
                try { el.setSelectionRange(pos, pos); } catch (err) { /* ignore */ }
            }
        }
    });

    /* ---------- User Master: role only for ROLE_USER ---------- */
    function syncPanel(select) {
        const form = select.form;
        const group = form.querySelector('[data-role-group]');
        const role = form.querySelector('#role_id');
        if (!group || !role) { return; }
        const isRoleUser = select.value === 'ROLE_USER';
        group.hidden = !isRoleUser;
        if (isRoleUser) { role.setAttribute('data-rules', 'required'); }
        else { role.removeAttribute('data-rules'); role.value = ''; V() && V().setError(role, null); }
    }

    /* ---------- Scheme form ---------- */
    function sectorCount(form) {
        let n = 0;
        form.querySelectorAll('[data-sector-name]').forEach(function (input) {
            if (input.value.trim() === '') { return; }
            if (input.hasAttribute('data-sector-new')) { n++; return; }
            const row = input.closest('.sector-row');
            const act = row ? row.querySelector('[data-sector-active]') : null;
            if (act && act.checked) { n++; }
        });
        return n;
    }
    function refreshSectorCount(form) {
        const out = form.querySelector('[data-sector-count]');
        if (out) { out.textContent = String(sectorCount(form)); }
        const items = form.querySelectorAll('[data-repeater-item]');
        items.forEach(function (it) {
            const btn = it.querySelector('[data-repeater-remove]');
            if (btn) { btn.disabled = items.length === 1 && !form.querySelector('[data-sector-active]'); }
        });
    }
    function initSchemeForm(form) {
        const list = form.querySelector('[data-repeater]');
        const tpl = form.querySelector('template[data-repeater-template]');

        form.addEventListener('click', function (e) {
            if (e.target.closest('[data-repeater-add]')) {
                const node = tpl.content.firstElementChild.cloneNode(true);
                list.appendChild(node);
                node.querySelector('input').focus();
                refreshSectorCount(form);
            }
            const rm = e.target.closest('[data-repeater-remove]');
            if (rm) {
                const item = rm.closest('[data-repeater-item]');
                if (list.querySelectorAll('[data-repeater-item]').length > 1) { item.remove(); }
                else { item.querySelector('input').value = ''; }
                refreshSectorCount(form);
            }
        });
        form.addEventListener('input', function (e) { if (e.target.matches('[data-sector-name]')) { refreshSectorCount(form); } });
        form.addEventListener('change', function (e) {
            if (e.target.matches('[data-sector-active]')) { refreshSectorCount(form); }
            const card = e.target.closest('[data-mapping-role]');
            if (card && card.querySelector('input:checked')) {
                card.classList.remove('has-error');
                const err = card.querySelector('[data-mapping-error]');
                if (err) { err.textContent = ''; }
            }
        });
        // Enter inside a sector box adds a new row instead of submitting
        form.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && e.target.matches('[data-sector-new]')) {
                e.preventDefault();
                form.querySelector('[data-repeater-add]').click();
            }
        });

        if (V()) {
            V().addCheck(form, function () {
                // duplicate names
                const seen = {};
                let dup = null;
                form.querySelectorAll('[data-sector-name]').forEach(function (i) {
                    const k = i.value.trim().toLowerCase();
                    if (!k) { return; }
                    if (seen[k] && !dup) { dup = i; }
                    seen[k] = true;
                });
                if (dup) { return { field: dup, message: 'Sector name "' + dup.value.trim() + '" is repeated. Each sector must be unique.' }; }
                if (sectorCount(form) < 1) {
                    return { field: form.querySelector('[data-sector-new]') || form.querySelector('[data-sector-name]'), message: 'Add at least one sector. A scheme cannot be saved without sectors.' };
                }
                return null;
            });
            V().addCheck(form, function () {
                let first = null;
                form.querySelectorAll('[data-mapping-role][data-mandatory="1"]').forEach(function (card) {
                    if (card.querySelector('input:checked')) { return; }
                    card.classList.add('has-error');
                    const err = card.querySelector('[data-mapping-error]');
                    const msg = 'Select at least one user for "' + card.getAttribute('data-role-name') + '" (mandatory role).';
                    if (err) { err.textContent = msg; }
                    if (!first) { first = { field: null, message: msg }; card.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
                });
                return first;
            });
        }
        refreshSectorCount(form);
    }

    /* ---------- Property form ---------- */
    function initPropertyForm(form) {
        const scheme = form.querySelector('#scheme_id');
        const sector = form.querySelector('#sector_id');
        const hint = form.querySelector('[data-sector-hint]');
        const unit = form.querySelector('[data-area-unit]');
        const area = form.querySelector('[data-area-value]');
        const preview = form.querySelector('[data-area-preview]');
        const box = form.querySelector('[data-confirm-box]');
        const noHint = form.querySelector('[data-confirm-no]');

        function schemeText() {
            if (!scheme) { return '—'; }
            if (scheme.tagName !== 'SELECT') { return scheme.getAttribute('data-scheme-label') || scheme.value; }
            return scheme.value ? scheme.options[scheme.selectedIndex].text : '—';
        }
        function sectorText() { return sector && sector.value ? sector.options[sector.selectedIndex].text : '—'; }

        function updateConfirm() {
            box.querySelector('[data-confirm-scheme]').textContent = '[' + schemeText() + ']';
            box.querySelector('[data-confirm-sector]').textContent = '[' + sectorText() + ']';
            const choice = form.querySelector('input[name="confirm_location"]:checked');
            const yes = choice && choice.value === 'YES';
            const ready = !!(sector && sector.value) && (!scheme || scheme.tagName !== 'SELECT' || !!scheme.value);
            form.querySelectorAll('[data-requires-yes]').forEach(function (b) { b.disabled = !(yes && ready); });
            if (noHint) { noHint.hidden = !(choice && choice.value === 'NO'); }
        }
        function resetConfirm() {
            form.querySelectorAll('input[name="confirm_location"]').forEach(function (r) { r.checked = false; });
            updateConfirm();
        }

        async function loadSectors(keepSelected) {
            const id = scheme.value;
            sector.innerHTML = '';
            const ph = document.createElement('option');
            ph.value = '';
            if (!id) {
                ph.textContent = 'Select scheme first';
                sector.appendChild(ph);
                sector.disabled = true;
                updateConfirm();
                return;
            }
            ph.textContent = 'Loading sectors…';
            sector.appendChild(ph);
            sector.disabled = true;
            if (hint) { hint.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Loading sectors…'; }
            try {
                await new Promise(function (r) { window.setTimeout(r, 150); });
                const eligible = Praman.DB.Scheme.eligible().some(function (s) { return String(s.scheme_id) === String(id); });
                if (!eligible) { throw new Error('This scheme is not available for property entry (inactive or no sectors).'); }
                const res = { sectors: Praman.DB.Scheme.sectors(id, true).map(function (s) { return { id: s.sector_id, name: s.sector_name }; }) };
                ph.textContent = res.sectors.length ? 'Select sector' : 'No active sector';
                res.sectors.forEach(function (s) {
                    const o = document.createElement('option');
                    o.value = String(s.id);
                    o.textContent = s.name;
                    if (keepSelected && String(s.id) === sector.getAttribute('data-selected')) { o.selected = true; }
                    sector.appendChild(o);
                });
                sector.disabled = res.sectors.length === 0;
                if (hint) { hint.textContent = res.sectors.length + ' sector(s) in this scheme.'; }
            } catch (err) {
                ph.textContent = 'Could not load sectors';
                if (hint) { hint.textContent = err.message; }
                Praman.toast('error', err.message);
            }
            updateConfirm();
        }

        function updateArea() {
            if (!preview) { return; }
            const v = parseFloat(area.value);
            const L = Praman.Lookups, base = L.BASE_UNIT;
            if (isNaN(v) || v <= 0) {
                const u = unit.value && unit.value !== base ? unit.value : null;
                preview.textContent = u ? '1 ' + L.unitSymbol(u) + ' = ' + L.unitFactor(u) + ' ' + L.unitSymbol(base) : 'Every area is also stored in ' + L.unitSymbol(base) + '.';
                return;
            }
            preview.textContent = unit.value && unit.value !== base
                ? '= ' + L.toSqm(v, unit.value).toFixed(2) + ' ' + L.unitSymbol(base) + ' (stored)'
                : Object.keys(L.AREA_UNITS).filter(function (c) { return c !== base; }).map(function (c) { return '= ' + L.convert(v, base, c).toFixed(2) + ' ' + L.unitSymbol(c); }).join(' · ');
        }

        if (scheme && scheme.tagName === 'SELECT') {
            scheme.addEventListener('change', function () { resetConfirm(); loadSectors(false); });
            if (scheme.value) { loadSectors(true); }
        }
        if (sector) { sector.addEventListener('change', resetConfirm); }
        form.addEventListener('change', function (e) { if (e.target.name === 'confirm_location') { updateConfirm(); } });
        if (area && unit) {
            area.addEventListener('input', updateArea);
            unit.addEventListener('change', updateArea);
            updateArea();
        }
        if (V()) {
            V().addCheck(form, function () {
                const choice = form.querySelector('input[name="confirm_location"]:checked');
                if (choice && choice.value !== 'YES') {
                    return { field: null, message: 'You answered "No" — change the scheme / sector, then answer "Yes" to save.' };
                }
                return null;
            });
        }
        updateConfirm();
    }

    /* ---------- submit pre-checks (called by app.js) ---------- */
    Praman.beforeSubmit = function (form, submitter) {
        if (form.hasAttribute('data-simulate-form') && submitter && submitter.value === 'REJECTED') {
            const rm = form.querySelector('input[name="remarks"]');
            if (rm && rm.value.trim() === '') {
                rm.classList.add('is-invalid');
                rm.focus();
                Praman.toast('error', 'Rejection reason (remark) is mandatory.');
                return false;
            }
        }
        return true;
    };

    function initBulkForm(form) {
        if (!V()) { return; }
        V().addCheck(form, function () {
            const reason = form.querySelector('[data-bulk-reason]');
            const needs = form.querySelector('input[name="ids[]"][data-status="NOT_READY"]:checked');
            if (needs && reason && reason.value.trim().length < 5) {
                return { field: reason, message: 'Enter a re-verification reason (min 5 characters) — Not Ready properties are selected.' };
            }
            if (!form.querySelector('input[name="ids[]"]:checked')) {
                return { field: null, message: 'Select at least one property.' };
            }
            return null;
        });
    }

    /* ---------- Role User decision: Reject needs a remark ---------- */
    function initResponseForm(form) {
        const remark = form.querySelector('textarea[name="remarks"]');
        const star = form.querySelector('[data-remark-required]');
        function sync() {
            const d = form.querySelector('input[name="decision"]:checked');
            const reject = !!d && d.value === 'REJECTED';
            if (star) { star.hidden = !reject; }
            remark.placeholder = reject ? 'Reason for objection (required) — e.g. encroachment, litigation, already allotted…' : 'Optional remark — e.g. Record clear, file no. 123';
            form.querySelectorAll('.decision').forEach(function (l) { l.classList.toggle('selected', !!l.querySelector('input:checked')); });
        }
        form.addEventListener('change', function (e) { if (e.target.name === 'decision') { sync(); } });
        if (V()) {
            V().addCheck(form, function () {
                const d = form.querySelector('input[name="decision"]:checked');
                if (d && d.value === 'REJECTED' && remark.value.trim().length < 5) {
                    return { field: remark, message: 'Objection reason is mandatory (at least 5 characters).' };
                }
                return null;
            });
        }
        sync();
    }

    /* ---------- Sector Master: "Sector-5, Sector-6" — each name 1–80 chars, no repeats, not already in the scheme ---------- */
    function initSectorForm(form) {
        const input = form.querySelector('#sector_name');
        const scheme = form.querySelector('#scheme_id');
        if (!V() || !input || !scheme) { return; } // edit form has a single name
        V().addCheck(form, function () {
            const names = input.value.split(',').map(function (x) { return x.trim(); }).filter(Boolean);
            if (!names.length) { return { field: input, message: 'Enter at least one sector name.' }; }
            const seen = {};
            const existing = Praman.DB.Scheme.sectors(scheme.value).map(function (s) { return s.sector_name.toLowerCase(); });
            for (let i = 0; i < names.length; i++) {
                const k = names[i].toLowerCase();
                if (names[i].length > 80) { return { field: input, message: 'Sector "' + names[i].slice(0, 30) + '…" is longer than 80 characters.' }; }
                if (seen[k] || existing.indexOf(k) !== -1) { return { field: input, message: 'Sector "' + names[i] + '" already exists in this scheme.' }; }
                seen[k] = true;
            }
            return null;
        });
    }

    /* ---------- Yes / No segmented radio ---------- */
    /** o: {name, label, value ('YES'|'NO'|''), yes, no, required} */
    Praman.yesNoField = function (o) {
        const esc = Praman.e;
        const opt = function (val, text, ic) {
            return '<label class="yn-opt yn-' + val.toLowerCase() + '"><input type="radio" name="' + esc(o.name) + '" value="' + val + '"' + (o.value === val ? ' checked' : '') +
                (val === 'YES' ? ' data-rules="required" data-label="' + esc('Yes or No for “' + o.label + '”') + '"' : '') + '>' +
                '<span><i class="fa-solid ' + ic + '" aria-hidden="true"></i> ' + esc(text) + '</span></label>';
        };
        return '<div class="form-group yn-group"><p class="form-label" id="' + esc(o.name) + '_lbl">' + esc(o.label) + ' <span class="required">*</span></p>' +
            '<div class="yn" role="radiogroup" aria-labelledby="' + esc(o.name) + '_lbl">' + opt('YES', o.yes || 'Yes', 'fa-check') + opt('NO', o.no || 'No', 'fa-xmark') + '</div>' +
            '<div class="invalid-feedback"></div></div>';
    };

    /* ---------- conditional blocks:  <div data-reveal="field=VALUE">,  "field!=VALUE"  or  "field=*" (any value) ----------
       Shown only while that radio / checkbox value is (not) checked, and every outer block is shown;
       fields of a hidden block are disabled, so they are neither validated nor sent. */
    function syncReveal(form) {
        form.querySelectorAll('[data-reveal]').forEach(function (box) { // document order: outer blocks first
            const m = /^([^!=]+)(!?=)(.*)$/.exec(box.getAttribute('data-reveal'));
            const c = form.querySelector('[name="' + CSS.escape(m[1]) + '"]:checked:not(:disabled)');
            const match = !!c && (m[3] === '*' || c.value === m[3]); // "field=*": any value chosen
            const on = (m[2] === '=' ? match : !match) && !(box.parentElement && box.parentElement.closest('[data-reveal][hidden]'));
            if (on && box.hidden) { box.classList.remove('anim-fade-up'); void box.offsetWidth; box.classList.add('anim-fade-up'); }
            box.hidden = !on;
        });
        form.querySelectorAll('[data-reveal] input, [data-reveal] select, [data-reveal] textarea').forEach(function (el) {
            const off = !!el.closest('[data-reveal][hidden]');
            if (off && !el.disabled && V()) { V().setError(el, null); }
            el.disabled = off;
        });
    }
    function initReveal(form) {
        if (!form.querySelector('[data-reveal]')) { return; }
        form.addEventListener('change', function () { syncReveal(form); });
        syncReveal(form);
    }

    /* ---------- dd/mm/yyyy date with a calendar button ---------- */
    /** o: {id, name, label, value (Y-m-d), required, max (Y-m-d)} */
    Praman.dateField = function (o) {
        const esc = Praman.e;
        const v = o.value ? String(o.value).slice(0, 10).split('-').reverse().join('/') : '';
        return '<div class="form-group"><label class="form-label" for="' + esc(o.id) + '">' + esc(o.label) + (o.required ? ' <span class="required">*</span>' : '') + '</label>' +
            '<div class="date-field" data-date-field><input type="text" id="' + esc(o.id) + '" name="' + esc(o.name) + '" class="form-control" value="' + esc(v) + '" placeholder="dd/mm/yyyy" ' +
            'inputmode="numeric" maxlength="10" autocomplete="off" data-rules="' + (o.required ? 'required|' : '') + 'dmy' + (o.max ? '|notfuture' : '') + '" data-label="' + esc(o.label) + '" data-date-text>' +
            '<button type="button" class="date-btn" data-date-open aria-label="' + esc('Open calendar for ' + o.label) + '" title="Pick a date"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i></button>' +
            '<input type="date" class="date-native" tabindex="-1" aria-hidden="true" min="1950-01-01"' + (o.max ? ' max="' + esc(o.max) + '"' : '') + ' data-date-native></div>' +
            '<div class="form-hint">Format dd/mm/yyyy — type it or use the calendar.</div><div class="invalid-feedback"></div></div>';
    };
    function toIso(dmy) {
        const m = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(dmy || '');
        return m ? m[3] + '-' + m[2] + '-' + m[1] : '';
    }
    function initDateField(box) {
        const text = box.querySelector('[data-date-text]');
        const native = box.querySelector('[data-date-native]');
        const btn = box.querySelector('[data-date-open]');
        text.addEventListener('input', function (ev) {
            if (ev.inputType && ev.inputType.indexOf('delete') === 0) { return; } // let backspace remove the slash
            const d = text.value.replace(/\D/g, '').slice(0, 8);
            text.value = d.length > 4 ? d.slice(0, 2) + '/' + d.slice(2, 4) + '/' + d.slice(4) : d.length > 2 ? d.slice(0, 2) + '/' + d.slice(2) : d;
        });
        btn.addEventListener('click', function () {
            if (text.disabled) { return; }
            native.value = toIso(text.value);
            try { native.showPicker(); } catch (err) { native.focus(); native.click(); }
        });
        native.addEventListener('change', function () {
            if (!native.value) { return; }
            text.value = native.value.split('-').reverse().join('/');
            text.dispatchEvent(new Event('change', { bubbles: true }));
            text.focus();
        });
    }

    /* ---------- reserve price: ₹ preview in the Indian format ---------- */
    function initRupee(input) {
        const out = input.closest('.form-group').querySelector('[data-rupee-preview]');
        function show() {
            const n = parseFloat(input.value.replace(/,/g, ''));
            if (isNaN(n) || n <= 0) { out.textContent = 'In rupees, e.g. 1250000 = ₹12,50,000.00'; return; }
            const words = n >= 1e7 ? ' (' + (n / 1e7).toFixed(2).replace(/\.?0+$/, '') + ' crore)' : n >= 1e5 ? ' (' + (n / 1e5).toFixed(2).replace(/\.?0+$/, '') + ' lakh)' : '';
            out.textContent = '₹' + n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + words;
        }
        input.addEventListener('input', show);
        show();
    }

    // called by core.js after every page render
    Praman.initForms = function (root) {
        root.querySelectorAll('form').forEach(initReveal);
        root.querySelectorAll('[data-date-field]').forEach(initDateField);
        root.querySelectorAll('[data-rupee]').forEach(initRupee);
        if (Praman.initPdfFields) { Praman.initPdfFields(root); }
        root.querySelectorAll('form[data-response-form]').forEach(initResponseForm);
        root.querySelectorAll('[data-panel-select]').forEach(function (s) {
            s.addEventListener('change', function () { syncPanel(s); });
            syncPanel(s);
        });
        root.querySelectorAll('form[data-scheme-form]').forEach(initSchemeForm);
        root.querySelectorAll('form[data-property-form]').forEach(initPropertyForm);
        root.querySelectorAll('form[data-bulk-form]').forEach(initBulkForm);
        root.querySelectorAll('form[data-action="sector-save"]').forEach(initSectorForm);
    };
})(window, document);
