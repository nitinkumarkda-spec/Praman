/* ===== js/forms.js ===== */
/* =====================================================================
   PRAMAAN — page-specific form behaviour (all hooked via data-* attributes)
   Sectors come from the data the server sent (core: PramanDB).
     • data-uppercase           live upper-casing (codes, plot no.)
     • data-panel-select        User Master: show Role only for Role Users
     • data-scheme-form         sector tag input (chips + search), active count, mandatory-role check
     • data-property-form       scheme → sectors (local DB), Yes/No confirmation, area preview
     • data-property-bulk       Add Property: plot ranges / single plots → property grid (corner, edit) → save all
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
    }

    /* ---------- Sector tag input (jQuery tags-input style): chips + search in names used by other schemes ---------- */
    function initSectorTags(form) {
        const wrap = form.querySelector('[data-sector-tags]');
        if (!wrap) { return; }
        const e = Praman.e, DB = Praman.DB;
        const box = wrap.querySelector('[data-tag-box]'), chips = wrap.querySelector('[data-tag-chips]');
        const entry = wrap.querySelector('[data-sector-entry]'), list = wrap.querySelector('[data-tag-suggest]');
        const errBox = wrap.querySelector('[data-tag-error]');
        const schemeId = Number(wrap.getAttribute('data-scheme-id')) || 0;
        let active = -1;

        const areaSel = form.querySelector('#area_id');
        const ALL = '__all__';
        // sectors of the other schemes in the selected Area: name -> scheme names
        let used = {}, names = [];
        function loadArea() {
            used = {};
            const areaId = areaSel ? Number(areaSel.value) : 0;
            DB.all('sectors').forEach(function (s) {
                const sc = DB.find('schemes', s.scheme_id);
                if (!sc || s.scheme_id === schemeId) { return; }
                if (areaId && sc.area_id && sc.area_id !== areaId) { return; }
                const k = s.sector_name.trim();
                (used[k] = used[k] || []).push(sc.scheme_name);
            });
            names = Object.keys(used).sort(function (a, b) { return a.localeCompare(b, undefined, { numeric: true }); });
        }
        loadArea();

        /** Every name already in this form (existing sectors + chips), lower-cased. */
        function taken() {
            const out = {};
            form.querySelectorAll('[data-sector-name]').forEach(function (i) { const v = i.value.trim().toLowerCase(); if (v) { out[v] = true; } });
            return out;
        }
        function error(msg) {
            errBox.textContent = msg || '';
            wrap.classList.toggle('is-invalid', !!msg);
        }
        /** Add one name as a chip; returns false (with a message) when it is not allowed. */
        function add(raw) {
            const name = String(raw || '').replace(/\s+/g, ' ').trim();
            if (!name) { return true; }
            if (name.length > 80) { error('“' + name.slice(0, 30) + '…” is longer than 80 characters.'); return false; }
            if (/[<>]/.test(name)) { error('“' + name + '”: < and > are not allowed.'); return false; }
            if (taken()[name.toLowerCase()]) { error('“' + name + '” is already in this scheme.'); return false; }
            const chip = document.createElement('span');
            chip.className = 'tag-chip anim-fade-up';
            chip.innerHTML = '<span class="tag-chip-text">' + e(name) + '</span>' +
                '<input type="hidden" name="sectors_new[]" value="' + e(name) + '" data-sector-name data-sector-new>' +
                '<button type="button" class="tag-chip-x" aria-label="' + e('Remove ' + name) + '" data-tag-remove>&times;</button>';
            chips.appendChild(chip);
            error('');
            refreshSectorCount(form);
            return true;
        }
        /** Text in the box → chips (a comma-separated list adds several). */
        function commit() {
            const parts = entry.value.split(',');
            const left = [];
            parts.forEach(function (p) { if (!add(p)) { left.push(p.trim()); } });
            entry.value = left.join(', ');
            render();
        }
        /** Open / close the suggestion list (the ⌄ icon turns with it). */
        function show(open) {
            list.hidden = !open;
            wrap.classList.toggle('is-open', open);
            entry.setAttribute('aria-expanded', String(open));
        }
        function options() { return Array.prototype.slice.call(list.querySelectorAll('[data-value]')); }
        function mark(i) {
            const opts = options();
            active = opts.length ? (i + opts.length) % opts.length : -1;
            opts.forEach(function (o, j) { o.classList.toggle('is-active', j === active); o.setAttribute('aria-selected', String(j === active)); });
            if (opts[active]) { opts[active].scrollIntoView({ block: 'nearest' }); }
        }
        /** Names still free to pick in the selected Area (optionally matching q). */
        function free(q) {
            const have = taken();
            return names.filter(function (n) { return !have[n.toLowerCase()] && (!q || n.toLowerCase().indexOf(q) !== -1); });
        }
        function render() {
            const q = entry.value.trim().toLowerCase();
            const have = taken();
            const hits = free(q);
            const exact = names.some(function (n) { return n.toLowerCase() === q; });
            const areaName = areaSel && areaSel.value ? areaSel.options[areaSel.selectedIndex].text : '';
            let html = '';
            if (q && !exact && !have[q] && q.indexOf(',') === -1) {
                html += '<li class="tag-option tag-option-new" role="option" data-value="' + e(entry.value.trim()) + '"><i class="fa-solid fa-plus" aria-hidden="true"></i><span>Add “<strong>' + e(entry.value.trim()) + '</strong>” as a new sector</span></li>';
            }
            if (areaSel && !areaName) {
                html += '<li class="tag-note" role="presentation"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Select the <strong>Area</strong> above to pick from its sectors.</li>';
            } else if (!names.length) {
                html += '<li class="tag-note" role="presentation"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> No other scheme has sectors yet — type new names.</li>';
            } else if (!q && hits.length > 1) {
                html += '<li class="tag-option tag-option-all" role="option" data-value="' + ALL + '"><i class="fa-solid fa-layer-group" aria-hidden="true"></i>' +
                    '<span>Add all <strong>' + hits.length + '</strong> sectors' + (areaName ? ' of ' + e(areaName) : '') + '</span></li>';
            }
            if (names.length && !hits.length && !q) {
                html += '<li class="tag-note" role="presentation"><i class="fa-solid fa-check" aria-hidden="true"></i> All sectors' + (areaName ? ' of ' + e(areaName) : '') + ' are added.</li>';
            }
            hits.forEach(function (n) {
                const k = q ? n.toLowerCase().indexOf(q) : -1;
                const label = k === -1 ? e(n) : e(n.slice(0, k)) + '<mark>' + e(n.slice(k, k + q.length)) + '</mark>' + e(n.slice(k + q.length));
                const sc = used[n];
                html += '<li class="tag-option" role="option" data-value="' + e(n) + '"><span>' + label + '</span>' +
                    '<small>' + e(sc.length === 1 ? sc[0] : sc.length + ' schemes') + '</small></li>';
            });
            const open = !!html && document.activeElement === entry;
            list.innerHTML = html;
            show(open);
            navigated = false;
            mark(0);
        }
        /** A suggestion was picked: one sector, or every free sector of the Area. */
        function choose(value) {
            if (value === ALL) {
                free('').forEach(add);
                entry.value = '';
                render();
            } else {
                entry.value = value;
                commit();
            }
        }
        let navigated = false; // Enter on an empty box picks only what the user moved to with ↑ ↓

        entry.addEventListener('input', function () {
            if (entry.value.indexOf(',') !== -1) { commit(); } else { error(''); render(); }
        });
        entry.addEventListener('focus', render);
        entry.addEventListener('blur', function () { window.setTimeout(function () { if (document.activeElement !== entry) { show(false); } }, 150); });
        entry.addEventListener('paste', function () { window.setTimeout(commit, 0); });
        entry.addEventListener('keydown', function (ev) {
            if ((ev.key === 'ArrowDown' || ev.key === 'ArrowUp') && list.hidden) { ev.preventDefault(); render(); }
            else if (ev.key === 'ArrowDown') { ev.preventDefault(); navigated = true; mark(active + 1); }
            else if (ev.key === 'ArrowUp') { ev.preventDefault(); navigated = true; mark(active - 1); }
            else if (ev.key === 'Enter' || (ev.key === 'Tab' && entry.value.trim())) {
                ev.preventDefault(); // never submits the form from here
                // with text typed (or after ↑ ↓), Enter takes the highlighted suggestion — "Add …" is first when the name is new
                const pick = (entry.value.trim() || navigated) && !list.hidden ? options()[active] : null;
                if (pick) { choose(pick.getAttribute('data-value')); } else { commit(); }
            } else if (ev.key === 'Escape') { show(false); }
            else if (ev.key === 'Backspace' && !entry.value) {
                const last = chips.lastElementChild;
                if (last) { last.remove(); refreshSectorCount(form); render(); }
            }
        });
        list.addEventListener('mousedown', function (ev) {
            const o = ev.target.closest('[data-value]');
            if (!o) { return; }
            ev.preventDefault(); // keep focus in the box
            choose(o.getAttribute('data-value'));
        });
        // a different Area → its sectors
        if (areaSel) {
            areaSel.addEventListener('change', function () { loadArea(); if (document.activeElement === entry) { render(); } });
        }
        list.addEventListener('mousemove', function (ev) { const o = ev.target.closest('[data-value]'); if (o) { mark(options().indexOf(o)); } });
        // click anywhere on the box (a chip's name, the empty space, the ⌄ icon) opens the list — again and again;
        // a second click outside the text closes it; × on a chip only removes that chip
        box.addEventListener('mousedown', function (ev) {
            if (ev.target === entry) { return; }
            ev.preventDefault(); // keep the caret in the text box
            const x = ev.target.closest('[data-tag-remove]');
            if (x) {
                x.closest('.tag-chip').remove();
                refreshSectorCount(form);
                entry.focus();
                render(); // the removed name is offered again
                return;
            }
            entry.focus();
            if (list.hidden) { render(); } else { show(false); }
        });
        entry.addEventListener('click', function () { if (list.hidden) { render(); } });
        // text still in the box when the form is sent becomes a chip first
        form.addEventListener('submit', function () { if (entry.value.trim()) { commit(); } }, true);
    }

    function initSchemeForm(form) {
        initSectorTags(form);
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
                return null;
            });
// Role-user mapping is bound when sending properties for verification
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
        function sectorText() { return sector && sector.value ? sector.options[sector.selectedIndex].text : 'No sector'; }

        function updateConfirm() {
            box.querySelector('[data-confirm-scheme]').textContent = '[' + schemeText() + ']';
            box.querySelector('[data-confirm-sector]').textContent = '[' + sectorText() + ']';
            const choice = form.querySelector('input[name="confirm_location"]:checked');
            const yes = choice && choice.value === 'YES';
            const ready = !scheme || scheme.tagName !== 'SELECT' || !!scheme.value; // sector is optional
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
                const u = unit.value && Number(unit.value) !== base ? Number(unit.value) : null;
                preview.textContent = u ? '1 ' + L.unitSymbol(u) + ' = ' + L.unitFactor(u) + ' ' + L.unitSymbol(base) : 'Every area is also stored in ' + L.unitSymbol(base) + '.';
                return;
            }
            preview.textContent = unit.value && Number(unit.value) !== base
                ? '= ' + L.toSqm(v, Number(unit.value)).toFixed(2) + ' ' + L.unitSymbol(base) + ' (stored)'
                : Object.keys(L.AREA_UNITS).map(Number).filter(function (c) { return c !== base; }).map(function (c) { return '= ' + L.convert(v, base, c).toFixed(2) + ' ' + L.unitSymbol(c); }).join(' · ');
        }

        if (scheme && scheme.tagName === 'SELECT') {
            scheme.addEventListener('change', function () { resetConfirm(); loadSectors(false); });
            if (scheme.value) { loadSectors(true); }
        }
        if (sector) { sector.addEventListener('change', resetConfirm); }
        form.addEventListener('change', function (e) { if (e.target.name === 'confirm_location') { updateConfirm(); } });
        const lenEl = form.querySelector('#area_length');
        const widEl = form.querySelector('#area_width');
        const calcHint = form.querySelector('[data-area-calc-hint]');

        function syncEditArea() {
            if (!lenEl || !widEl || !area) return;
            const lStr = lenEl.value.trim().replace(/,/g, '');
            const wStr = widEl.value.trim().replace(/,/g, '');
            if (lStr !== '' && wStr !== '') {
                const l = parseFloat(lStr);
                const w = parseFloat(wStr);
                if (!isNaN(l) && l > 0 && !isNaN(w) && w > 0) {
                    const val = Math.round(l * w * 100) / 100;
                    area.value = val.toFixed(2);
                    if (calcHint) {
                        calcHint.innerHTML = '<span class="text-success"><i class="fa-solid fa-check" aria-hidden="true"></i> Calculated: <strong>' + l + '</strong> × <strong>' + w + '</strong> = <strong>' + val.toFixed(2) + '</strong></span> (Read-only)';
                    }
                } else {
                    area.value = '';
                }
            } else if (lStr === '' && wStr === '') {
                // Keep existing or empty
            } else {
                area.value = '';
                if (calcHint) calcHint.textContent = 'Enter both Length and Width to calculate Area.';
            }
            updateArea();
        }

        if (lenEl && widEl) {
            lenEl.addEventListener('input', syncEditArea);
            widEl.addEventListener('input', syncEditArea);
        }

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

    /* ---------- Add Property: plot numbers → property grid ---------- */
    const PB_MAX = 500; // same limit as the server
    function initPropertyBulk(form) {
        const L = Praman.Lookups, esc = Praman.e;
        const scheme = form.querySelector('#scheme_id');
        const sector = form.querySelector('#sector_id');
        const out = form.querySelector('[data-pb-plots]');
        const card = form.querySelector('[data-pb-grid-card]');
        const grid = form.querySelector('[data-pb-grid]');
        const count = form.querySelector('[data-pb-count]');
        const saveBtn = form.querySelector('[data-pb-save]');
        const pbLen = form.querySelector('[data-pb-length]');
        const pbWid = form.querySelector('[data-pb-width]');
        const pbArea = form.querySelector('[data-pb-default="area_value"]');
        const pbHint = form.querySelector('[data-pb-area-calc-hint]');
        const KEYS = ['plot_type_id', 'area_value', 'unit_id'];
        // each plot: {plot_no, corner, own: {key: value}} — keys not in own follow the defaults above
        let plots = [];

        const mode = function () { const r = form.querySelector('input[name="pb_range"]:checked'); return r ? r.value : 'YES'; };
        const modeBox = function (m) { return form.querySelector('[data-pb-mode="' + m + '"]'); };
        const defaults = function () {
            const d = {};
            form.querySelectorAll('[data-pb-default]').forEach(function (el) { d[el.getAttribute('data-pb-default')] = el.value.trim(); });
            return d;
        };
        const value = function (p, k) { return Object.prototype.hasOwnProperty.call(p.own, k) ? p.own[k] : defaults()[k]; };

        /* --- plot number rows --- */
        function rowHtml(m) {
            const del = '<button type="button" class="btn-icon pb-del" data-pb-del aria-label="Remove row" title="Remove row"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button>';
            if (m === 'YES') {
                return '<div class="pb-row" data-pb-row><input type="text" class="form-control" data-pb-from inputmode="numeric" maxlength="6" placeholder="From e.g. 1" aria-label="From plot no." data-digits-only>' +
                    '<span class="pb-dash" aria-hidden="true">–</span><input type="text" class="form-control" data-pb-to inputmode="numeric" maxlength="6" placeholder="To e.g. 50" aria-label="To plot no." data-digits-only>' +
                    '<span class="pb-row-note" data-pb-note></span>' + del + '</div>';
            }
            return '<div class="pb-row is-single" data-pb-row><input type="text" class="form-control" data-pb-prefix maxlength="10" placeholder="Prefix (A-)" aria-label="Prefix" data-uppercase>' +
                '<input type="text" class="form-control" data-pb-no maxlength="20" placeholder="Plot no. e.g. 12" aria-label="Plot no." data-uppercase>' +
                '<input type="text" class="form-control" data-pb-postfix maxlength="10" placeholder="Postfix (/B)" aria-label="Postfix" data-uppercase>' +
                '<input type="text" class="form-control" data-pb-desc maxlength="500" placeholder="Description (optional)" aria-label="Description">' +
                '<span class="pb-row-note" data-pb-note></span>' + del + '</div>';
        }
        function addRow(m) {
            const rows = modeBox(m).querySelector('[data-pb-rows]');
            rows.insertAdjacentHTML('beforeend', rowHtml(m));
            syncDel(rows);
            return rows.lastElementChild;
        }
        function syncDel(rows) {
            const all = rows.querySelectorAll('[data-pb-row]');
            all.forEach(function (r) { r.querySelector('[data-pb-del]').disabled = all.length === 1; });
        }
        function noteRow(row) {
            const n = row.querySelector('[data-pb-note]');
            const r = readRow(row);
            n.classList.toggle('is-error', !!r.error);
            n.textContent = r.error || (r.list.length ? (row.querySelector('[data-pb-from]') ? r.list.length + ' plot(s)' : '→ ' + r.list[0]) : '');
        }
        /** Plot numbers one row stands for, or an error. */
        function readRow(row) {
            const from = row.querySelector('[data-pb-from]');
            if (from) {
                const a = from.value.trim(), b = row.querySelector('[data-pb-to]').value.trim();
                if (a === '' && b === '') { return { list: [] }; }
                if (a === '' || b === '') { return { list: [], error: 'Enter both From and To' }; }
                const x = parseInt(a, 10), y = parseInt(b, 10);
                if (x > y) { return { list: [], error: 'From is more than To' }; }
                if (y - x + 1 > PB_MAX) { return { list: [], error: 'At most ' + PB_MAX + ' plots' }; }
                const width = /^0/.test(a) && a.length > 1 ? a.length : 0; // 001 → keep three digits
                const list = [];
                for (let i = x; i <= y; i++) { list.push(width ? String(i).padStart(width, '0') : String(i)); }
                return { list: list };
            }
            const no = row.querySelector('[data-pb-no]').value.trim();
            if (no === '') { return { list: [] }; }
            const plot = (row.querySelector('[data-pb-prefix]').value.trimStart() + no + row.querySelector('[data-pb-postfix]').value.trimEnd()).toUpperCase(); // a space typed next to the plot no. is kept
            if (!/^[A-Za-z0-9][A-Za-z0-9\/_ -]{0,29}$/.test(plot)) { return { list: [], error: 'Only letters, digits, space, / _ - (max 30)' }; }
            const desc = row.querySelector('[data-pb-desc]').value.trim();
            if (/[<>]/.test(desc)) { return { list: [], error: 'Description may not contain < or >' }; }
            return { list: [plot], desc: desc };
        }

        function generate() {
            if (pbLen && pbWid) {
                const lStr = pbLen.value.trim();
                const wStr = pbWid.value.trim();
                if ((lStr !== '' && wStr === '') || (lStr === '' && wStr !== '')) {
                    Praman.toast('error', 'Please enter both Length and Width to calculate Area (or leave both blank).');
                    (lStr === '' ? pbLen : pbWid).focus();
                    return;
                }
            }
            const rows = modeBox(mode()).querySelectorAll('[data-pb-row]');
            const list = [];
            const descs = {};
            let bad = null;
            rows.forEach(function (row) {
                noteRow(row);
                const r = readRow(row);
                if (r.error && !bad) { bad = row; }
                Array.prototype.push.apply(list, r.list);
                if (r.list.length && r.desc !== undefined) { descs[r.list[0]] = r.desc; }
            });
            if (bad) {
                Praman.toast('error', 'Please fix the highlighted plot number row.');
                bad.querySelector('input').focus();
                return;
            }
            if (!list.length) {
                Praman.toast('error', mode() === 'YES' ? 'Enter at least one From – To range.' : 'Enter at least one plot no.');
                rows[0].querySelector('input').focus();
                return;
            }
            const dup = list.filter(function (p, i) { return list.indexOf(p) !== i; });
            if (dup.length) {
                Praman.toast('error', 'Plot no. ' + dup.slice(0, 5).join(', ') + (dup.length > 5 ? ' …' : '') + ' is repeated — the rows overlap.');
                return;
            }
            if (list.length > PB_MAX) {
                Praman.toast('error', 'At most ' + PB_MAX + ' properties can be added at once (you entered ' + list.length + ').');
                return;
            }
            // keep corner marks and edits of plots that are generated again
            const old = {};
            plots.forEach(function (p) { old[p.plot_no] = p; });
            plots = list.map(function (no) {
                const p = old[no] || { plot_no: no, corner: false, own: {}, remarks: '' };
                if (Object.prototype.hasOwnProperty.call(descs, no)) { p.remarks = descs[no]; } // the row's description wins over an older edit
                return p;
            });
            render();
            card.hidden = false;
            card.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        /* --- grid --- */
        function render() {
            const types = L.PROPERTY_TYPES;
            grid.innerHTML = plots.map(function (p, i) {
                const area = value(p, 'area_value');
                return '<div class="pb-card' + (p.corner ? ' is-corner' : '') + '" data-pb-i="' + i + '">' +
                    '<div class="pb-no">' + esc(p.plot_no) + '</div><div class="pb-kind">' + (p.corner ? 'Corner' : 'Normal') + '</div>' +
                    '<div class="pb-meta">' + esc(types[value(p, 'plot_type_id')] || 'No plot type') + (area ? ' · ' + esc(area + ' ' + L.unitSymbol(value(p, 'unit_id'))) : '') + '</div>' +
                    (p.remarks ? '<div class="pb-desc" title="' + esc(p.remarks) + '">' + esc(p.remarks) + '</div>' : '') +
                    '<div class="pb-card-actions"><button type="button" class="btn btn-light btn-sm" data-pb-edit>Edit</button>' +
                    '<button type="button" class="btn btn-light btn-sm" data-pb-corner aria-pressed="' + p.corner + '">' + (p.corner ? 'Unmark Corner' : 'Mark Corner') + '</button></div></div>';
            }).join('');
            const corners = plots.filter(function (p) { return p.corner; }).length;
            count.innerHTML = '<span><strong>' + plots.length + '</strong> propert' + (plots.length === 1 ? 'y' : 'ies') + ' ready to save' + (corners ? ' · ' + corners + ' corner' : '') + '</span>';
            card.hidden = !plots.length;
            sync();
        }
        function sync() {
            out.value = JSON.stringify(plots.map(function (p) {
                return { plot_no: p.plot_no, plot_type_id: value(p, 'plot_type_id'), area_value: value(p, 'area_value'), unit_id: value(p, 'unit_id'),
                    remarks: p.remarks || '', corner: p.corner ? 1 : 0 };
            }));
            saveBtn.disabled = !plots.length;
            const where = (scheme.value ? scheme.options[scheme.selectedIndex].text : 'the scheme') + (sector.value ? ' · Sector ' + sector.options[sector.selectedIndex].text : '');
            form.setAttribute('data-confirm', 'Save ' + plots.length + ' propert' + (plots.length === 1 ? 'y' : 'ies') + ' in ' + where + '? They will be created with status Listed.');
        }

        /* --- edit one property --- */
        function edit(i) {
            const p = plots[i];
            const old = document.getElementById('pb-modal');
            if (old) { old.remove(); }
            const m = document.createElement('div');
            m.id = 'pb-modal';
            m.className = 'modal-backdrop pb-modal';
            m.innerHTML = '<div class="modal" role="dialog" aria-modal="true" aria-labelledby="pb-modal-title"><div class="modal-header">' +
                '<span class="modal-icon"><i class="fa-solid fa-pen" aria-hidden="true"></i></span><div><h2 class="modal-title" id="pb-modal-title">Edit plot ' + esc(p.plot_no) + '</h2>' +
                '<p>Changes here apply to this property only.</p></div></div><div class="modal-body"><div class="form-grid">' +
                '<div class="form-group"><label class="form-label" for="pbm_no">Plot no. <span class="required">*</span></label><input type="text" id="pbm_no" class="form-control" maxlength="30" value="' + esc(p.plot_no) + '" data-uppercase><div class="invalid-feedback"></div></div>' +
                '<div class="form-group"><label class="form-label" for="pbm_type">Plot type</label><select id="pbm_type" class="form-select">' + L.options(L.activeMap('plot_types', 'plot_type_id', 'name', value(p, 'plot_type_id')), value(p, 'plot_type_id'), 'Select plot type') + '</select></div>' +
                '<div class="form-group span-2"><label class="form-label">Area (optional)</label>' +
                '<div class="dim-row">' +
                '<div class="form-group"><label class="form-label text-muted" for="pbm_length">Length</label><input type="text" id="pbm_length" class="form-control" inputmode="decimal" maxlength="10" placeholder="e.g. 30.00" data-decimals="2"></div>' +
                '<span class="dim-x" aria-hidden="true">×</span>' +
                '<div class="form-group"><label class="form-label text-muted" for="pbm_width">Width</label><input type="text" id="pbm_width" class="form-control" inputmode="decimal" maxlength="10" placeholder="e.g. 40.50" data-decimals="2"></div>' +
                '<div class="form-group"><label class="form-label text-muted" for="pbm_area">Calculated Area</label><div class="pb-area"><input type="text" id="pbm_area" class="form-control" readonly placeholder="Auto (L × W)" data-decimals="2" value="' + esc(value(p, 'area_value')) + '" tabindex="-1" style="cursor: not-allowed;">' +
                '<select id="pbm_unit" class="form-select" aria-label="Area unit">' + L.options(L.activeMap('units', 'unit_id', 'name', value(p, 'unit_id')), value(p, 'unit_id')) + '</select></div></div></div>' +
                '<div class="form-hint" id="pbm_hint">Enter Length and Width — Area is auto-calculated and non-editable.</div></div>' +
                '<div class="form-group span-2"><label class="form-label" for="pbm_desc">Description</label><textarea id="pbm_desc" class="form-control" maxlength="500" rows="2" placeholder="Optional notes about this plot">' + esc(p.remarks || '') + '</textarea><div class="invalid-feedback"></div></div>' +
                '<div class="form-group span-2"><label class="check"><input type="checkbox" id="pbm_corner"' + (p.corner ? ' checked' : '') + '> <span><strong>Corner plot</strong></span></label></div>' +
                '</div></div><div class="modal-footer"><button type="button" class="btn btn-light pb-remove" data-pbm-remove><i class="fa-solid fa-trash-can" aria-hidden="true"></i> <span>Remove</span></button>' +
                '<button type="button" class="btn btn-light" data-pbm-cancel>Cancel</button><button type="button" class="btn btn-primary" data-pbm-ok><i class="fa-solid fa-check" aria-hidden="true"></i> <span>Apply</span></button></div></div>';
            document.body.appendChild(m);
            const $ = function (s) { return m.querySelector(s); };
            const lastFocus = document.activeElement;

            function syncPbmArea() {
                const lStr = $('#pbm_length').value.trim().replace(/,/g, '');
                const wStr = $('#pbm_width').value.trim().replace(/,/g, '');
                if (lStr !== '' && wStr !== '') {
                    const l = parseFloat(lStr);
                    const w = parseFloat(wStr);
                    if (!isNaN(l) && l > 0 && !isNaN(w) && w > 0) {
                        const val = Math.round(l * w * 100) / 100;
                        $('#pbm_area').value = val.toFixed(2);
                        const hint = $('#pbm_hint');
                        if (hint) { hint.innerHTML = '<span class="text-success"><i class="fa-solid fa-check" aria-hidden="true"></i> Calculated: <strong>' + l + '</strong> × <strong>' + w + '</strong> = <strong>' + val.toFixed(2) + '</strong></span> (Read-only)'; }
                    } else {
                        $('#pbm_area').value = '';
                    }
                } else if (lStr === '' && wStr === '') {
                    $('#pbm_area').value = '';
                    const hint = $('#pbm_hint');
                    if (hint) { hint.textContent = 'Enter Length and Width — Area is auto-calculated and non-editable.'; }
                } else {
                    $('#pbm_area').value = '';
                    const hint = $('#pbm_hint');
                    if (hint) { hint.textContent = 'Enter both Length and Width to calculate Area.'; }
                }
            }
            $('#pbm_length').addEventListener('input', syncPbmArea);
            $('#pbm_width').addEventListener('input', syncPbmArea);
            function close() {
                m.classList.remove('is-open');
                document.body.classList.remove('modal-open');
                document.removeEventListener('keydown', onKey);
                window.setTimeout(function () { m.remove(); }, 180);
                if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
            }
            function onKey(ev) { if (ev.key === 'Escape') { close(); } }
            function fieldError(el, msg) {
                el.classList.toggle('is-invalid', !!msg);
                const fb = el.closest('.form-group').querySelector('.invalid-feedback');
                if (fb) { fb.textContent = msg || ''; }
            }
            $('[data-pbm-ok]').addEventListener('click', function () {
                const no = $('#pbm_no').value.trim().toUpperCase();
                let ok = true;
                const noErr = !/^[A-Za-z0-9][A-Za-z0-9\/_ -]{0,29}$/.test(no) ? 'Only letters, digits, space, / _ - (max 30).'
                    : plots.some(function (x, j) { return j !== i && x.plot_no === no; }) ? 'Plot no. ' + no + ' is already in the grid.' : null;
                fieldError($('#pbm_no'), noErr);
                if (noErr) { ok = false; }
                const desc = $('#pbm_desc').value.trim();
                const descErr = /[<>]/.test(desc) ? 'Description may not contain < or > characters.' : null;
                fieldError($('#pbm_desc'), descErr);
                if (descErr) { ok = false; }
                if (!ok) { return; }
                const d = defaults();
                const next = { plot_type_id: $('#pbm_type').value, area_value: $('#pbm_area').value.trim(), unit_id: $('#pbm_unit').value };
                p.plot_no = no;
                p.corner = $('#pbm_corner').checked;
                p.remarks = desc;
                KEYS.forEach(function (k) { if (next[k] !== d[k]) { p.own[k] = next[k]; } else { delete p.own[k]; } });
                close();
                render();
            });
            $('[data-pbm-remove]').addEventListener('click', function () {
                plots.splice(i, 1);
                close();
                render();
            });
            $('[data-pbm-cancel]').addEventListener('click', close);
            document.addEventListener('keydown', onKey);
            document.body.classList.add('modal-open');
            window.requestAnimationFrame(function () { m.classList.add('is-open'); $('#pbm_no').focus(); });
        }

        /* --- sectors of the scheme (optional) --- */
        function loadSectors() {
            const list = scheme.value ? Praman.DB.Scheme.sectors(scheme.value, true) : [];
            sector.innerHTML = '<option value="">' + (!scheme.value ? 'Select scheme first' : list.length ? 'No sector' : 'No active sector') + '</option>' +
                list.map(function (s) { return '<option value="' + s.sector_id + '">' + esc(s.sector_name) + '</option>'; }).join('');
            sector.disabled = !list.length;
            sync();
        }

        /* --- area calculation from length × width --- */
        function syncBulkArea() {
            if (!pbLen || !pbWid || !pbArea) return;
            const lStr = pbLen.value.trim().replace(/,/g, '');
            const wStr = pbWid.value.trim().replace(/,/g, '');
            const unitSel = form.querySelector('[name="unit_id"]');
            const unitText = unitSel && unitSel.selectedIndex >= 0 ? unitSel.options[unitSel.selectedIndex].text : '';

            if (lStr === '' || wStr === '') {
                pbArea.value = '';
                if (pbHint) {
                    if (lStr !== '' && wStr === '') {
                        pbHint.innerHTML = 'Enter <strong>Width</strong> to calculate Area.';
                    } else if (lStr === '' && wStr !== '') {
                        pbHint.innerHTML = 'Enter <strong>Length</strong> to calculate Area.';
                    } else {
                        pbHint.textContent = 'Enter Length and Width — Area is automatically calculated (Length × Width) and is not editable.';
                    }
                }
            } else {
                const l = parseFloat(lStr);
                const w = parseFloat(wStr);
                if (!isNaN(l) && l > 0 && !isNaN(w) && w > 0) {
                    const areaVal = Math.round(l * w * 100) / 100;
                    pbArea.value = areaVal.toFixed(2);
                    if (pbHint) {
                        pbHint.innerHTML = '<span class="text-success"><i class="fa-solid fa-check" aria-hidden="true"></i> Calculated: <strong>' + l + '</strong> × <strong>' + w + '</strong> = <strong>' + areaVal.toFixed(2) + '</strong> ' + esc(unitText) + '</span> (Read-only)';
                    }
                } else {
                    pbArea.value = '';
                    if (pbHint) {
                        pbHint.innerHTML = '<span class="text-danger"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i> Please enter positive numbers for Length and Width.</span>';
                    }
                }
            }
            render();
        }

        if (pbLen && pbWid) {
            pbLen.addEventListener('input', syncBulkArea);
            pbWid.addEventListener('input', syncBulkArea);
            const unitSel = form.querySelector('[name="unit_id"]');
            if (unitSel) { unitSel.addEventListener('change', syncBulkArea); }
        }

        scheme.addEventListener('change', loadSectors);
        sector.addEventListener('change', sync);
        form.addEventListener('change', function (ev) {
            if (ev.target.name === 'pb_range') {
                ['YES', 'NO'].forEach(function (m) { modeBox(m).hidden = m !== mode(); });
                modeBox(mode()).querySelector('[data-pb-row] input').focus();
            }
        });
        form.addEventListener('input', function (ev) {
            const row = ev.target.closest('[data-pb-row]');
            if (row) { noteRow(row); }
            if (ev.target.hasAttribute('data-pb-default')) { render(); }
        });
        form.querySelectorAll('select[data-pb-default]').forEach(function (s) { s.addEventListener('change', render); });
        form.addEventListener('keydown', function (ev) {
            // Enter in a plot row adds the next row instead of submitting the form
            const row = ev.target.closest && ev.target.closest('[data-pb-row]');
            if (row && ev.key === 'Enter') {
                ev.preventDefault();
                const next = row.nextElementSibling || addRow(mode());
                next.querySelector('input').focus();
            }
        });
        form.addEventListener('click', function (ev) {
            const t = ev.target.closest('button');
            if (!t) { return; }
            if (t.hasAttribute('data-pb-add')) { addRow(mode()).querySelector('input').focus(); }
            else if (t.hasAttribute('data-pb-del')) {
                const rows = t.closest('[data-pb-rows]');
                t.closest('[data-pb-row]').remove();
                syncDel(rows);
            }
            else if (t.hasAttribute('data-pb-generate')) { generate(); }
            else if (t.hasAttribute('data-pb-edit')) { edit(+t.closest('[data-pb-i]').getAttribute('data-pb-i')); }
            else if (t.hasAttribute('data-pb-corner')) {
                const p = plots[+t.closest('[data-pb-i]').getAttribute('data-pb-i')];
                p.corner = !p.corner;
                render();
            }
        });
        if (V()) {
            V().addCheck(form, function () {
                if (!plots.length) { return { field: null, message: 'Generate the property grid first.' }; }
                return null;
            });
        }
        addRow('YES');
        addRow('NO');
        sync();
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
            const needs = form.querySelector('input[name="ids[]"][data-status="' + Praman.ST.NOT_READY + '"]:checked');
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
        root.querySelectorAll('form[data-property-bulk]').forEach(initPropertyBulk);
        root.querySelectorAll('form[data-bulk-form]').forEach(initBulkForm);
        root.querySelectorAll('form[data-sector-form]').forEach(initSectorForm);
    };
})(window, document);
