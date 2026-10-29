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
            if (isNaN(v) || v <= 0) { preview.textContent = '1 sq ft = 0.092903 sq m'; return; }
            preview.textContent = unit.value === 'SQFT'
                ? '= ' + (v * 0.092903).toFixed(2) + ' sq m (stored)'
                : '= ' + (v / 0.092903).toFixed(2) + ' sq ft';
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

    // called by core.js after every page render
    Praman.initForms = function (root) {
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
