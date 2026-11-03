/* ===== js/pages/role.js ===== */
/* PRAMAAN — Role User panel (app/Views/roleuser/*) */
(function (window) {
    'use strict';
    const P = window.Praman;
    const e = P.e, icon = P.icon, DB = P.DB, Auth = P.Auth, L = P.Lookups;
    const PANEL = 'ROLE_USER';

    /**
     * Tasks the user is assigned to (mirrors App\Models\Task).
     * view_status = NOT_REQUIRED when the task is still pending but the round was already closed.
     * A task answered by another assignee of the same section is hidden.
     */
    function tasksFor(userId) {
        const mine = DB.where('task_assignees', { user_id: userId }).map(function (a) { return a.task_id; });
        return DB.where('tasks', function (t) {
            return mine.indexOf(t.task_id) !== -1 && (t.task_status === 'PENDING' || t.responded_by === userId);
        }).map(enrich);
    }
    function enrich(t) {
        const vr = DB.find('rounds', t.round_id);
        const p = DB.find('properties', vr.property_id);
        const sc = DB.find('schemes', p.scheme_id);
        const se = DB.find('sectors', p.sector_id);
        const r = DB.find('roles', t.role_id);
        const ru = t.responded_by ? DB.find('users', t.responded_by) : null;
        const iu = DB.find('users', vr.initiated_by);
        return Object.assign({}, t, {
            view_status: t.task_status === 'PENDING' && vr.round_status !== 'OPEN' ? 'NOT_REQUIRED' : t.task_status,
            round_no: vr.round_no, round_status: vr.round_status, initiated_at: vr.initiated_at, resend_reason: vr.resend_reason,
            property_id: p.property_id, property_code: p.property_code, plot_no: p.plot_no, property_type: p.property_type,
            area_value: p.area_value, area_unit: p.area_unit, area_sqm: p.area_sqm, property_remarks: p.remarks,
            scheme_name: sc.scheme_name, scheme_code: sc.scheme_code, scheme_type: sc.scheme_type, sector_name: se ? se.sector_name : '—',
            area: sc ? DB.areaName(sc.area_id) : '—',
            role_name: r.role_name, role_code: r.role_code, responded_by_name: ru ? ru.name : null,
            initiated_by_name: iu ? iu.name : '', initiated_by_designation: iu ? iu.designation : ''
        });
    }
    const isPendingOpen = function (t) { return t.view_status === 'PENDING'; };

    /* ================= Engineering Cell: physical site check (filled before the decision) =================
       1 area as length × width (unit from the Unit Master) · 2 encroachment (partial / complete + details)
       3 road size + corner plot · 4 site photos · 5 geo coordinates. Server: TaskController::siteCheck(). */
    const SITE_ROLE = 'ENGINEERING_CELL';
    const ENCROACH_MAX = 300;
    const ENCROACH_TYPES = { PARTIAL: 'Partial encroachment', COMPLETE: 'Complete encroachment' };
    const ROAD_UNITS = { FT: 'Feet (ft)', M: 'Meter (m)' };
    function inUnit(v, from, to) { return L.convert(v, from, to); }
    /** Actual vs recorded area, both expressed in the recorded unit. */
    function variance(value, unit, recValue, recUnit) {
        if (!(recValue > 0) || !recUnit) { return { variance: null }; } // no area in the register to compare with
        const actual = Math.round(inUnit(value, unit, recUnit) * 100) / 100;
        const diff = Math.round((actual - recValue) * 100) / 100;
        return {
            actual: actual, diff: Math.abs(diff), unit: recUnit,
            pct: recValue ? Math.round(Math.abs(diff) / recValue * 10000) / 100 : 0,
            variance: Math.abs(diff) < 0.01 ? 'SAME' : (diff < 0 ? 'LESS' : 'MORE')
        };
    }
    function varianceHtml(r) {
        if (!r.variance) { return icon('fa-circle-info') + ' <span>No area is recorded in the property register, so there is nothing to compare.</span>'; }
        if (r.variance === 'SAME') { return icon('fa-circle-check') + ' <span>Actual area <strong>matches</strong> the recorded area.</span>'; }
        return icon(r.variance === 'LESS' ? 'fa-arrow-trend-down' : 'fa-arrow-trend-up') + ' <span>Actual area is <strong>' + P.fmt_num(r.diff, 2) + ' ' +
            e(L.unitSymbol(r.unit)) + ' (' + P.fmt_num(r.pct, 2) + '%) ' + (r.variance === 'LESS' ? 'less' : 'more') + '</strong> than recorded.</span>';
    }
    /** Length unit of an area unit: "sq ft" → "ft". */
    function lengthSymbol(code) { return L.unitSymbol(code).replace(/^sq\.?\s+/i, ''); }
    function choiceField(name, label, map) {
        return '<div class="form-group yn-group"><p class="form-label" id="' + name + '_lbl">' + e(label) + ' <span class="required">*</span></p>' +
            '<div class="yn yn-choice" role="radiogroup" aria-labelledby="' + name + '_lbl">' + Object.keys(map).map(function (k, i) {
                return '<label class="yn-opt yn-yes"><input type="radio" name="' + name + '" value="' + k + '"' + (i === 0 ? ' data-rules="required" data-label="' + e(label) + '"' : '') + '><span>' + e(map[k]) + '</span></label>';
            }).join('') + '</div><div class="invalid-feedback"></div></div>';
    }
    function siteCheckHtml(task) {
        const sym = lengthSymbol(task.area_unit);
        const dim = function (id, label, ph) {
            return '<div class="form-group"><label class="form-label" for="' + id + '">' + label + ' <span class="required">*</span></label>' +
                '<div class="input-suffix"><input type="text" id="' + id + '" name="' + id + '" class="form-control" inputmode="decimal" maxlength="10" placeholder="' + ph + '" autocomplete="off" ' +
                'data-decimals="2" data-rules="required|decimal:2|positive" data-label="' + label + '" data-site-dim><span class="suffix" data-len-sym>' + e(sym) + '</span></div><div class="invalid-feedback"></div></div>';
        };
        return '<div class="site-check" data-site-check data-recorded-value="' + (task.area_value == null ? '' : task.area_value) + '" data-recorded-unit="' + e(task.area_unit || '') + '">' +
            stepHtml(1, 'fa-ruler-combined', 'Area', 'as per the physical location') +
            '<div class="site-recorded">' + icon('fa-file-lines') + ' <span>Recorded area (property register): <strong>' + e(task.area_value == null ? 'Not entered' : L.area(task.area_value, task.area_unit)) + '</strong></span></div>' +
            '<div class="dim-row">' + dim('site_length', 'Length', 'e.g. 30.00') + '<span class="dim-x" aria-hidden="true">×</span>' + dim('site_width', 'Width', 'e.g. 40.50') +
            '<div class="form-group"><label class="form-label" for="site_area_unit">Unit <span class="required">*</span></label>' +
            '<select id="site_area_unit" name="site_area_unit" class="form-select" data-rules="required" data-label="Unit" data-site-unit>' +
            Object.keys(L.activeMap('units', 'code', 'name', task.area_unit)).map(function (u) {
                return '<option value="' + e(u) + '"' + (u === task.area_unit ? ' selected' : '') + '>' + e(L.unitName(u)) + ' (' + e(L.unitSymbol(u)) + ')</option>';
            }).join('') + '</select><div class="form-hint">From the Unit Master.</div><div class="invalid-feedback"></div></div></div>' +
            '<div class="site-variance" data-site-variance aria-live="polite"></div>' +

            stepHtml(2, 'fa-triangle-exclamation', 'Encroachment') +
            P.yesNoField({ name: 'encroachment', label: 'Is there any encroachment on the plot?' }) +
            '<div class="reveal-box" data-reveal="encroachment=YES"><div class="sale-result is-allotted">' + icon('fa-triangle-exclamation') + ' <span>With encroachment, this report goes as an <strong>Objection</strong> and the property becomes Not Ready.</span></div>' + choiceField('encroachment_type', 'Type of encroachment', ENCROACH_TYPES) +
            '<div class="reveal-box reveal-plain" data-reveal="encroachment_type=*"><div class="form-group"><label class="form-label" for="encroachment_details">Details of encroachment <span class="required">*</span></label>' +
            '<textarea id="encroachment_details" name="encroachment_details" class="form-control" rows="3" maxlength="' + ENCROACH_MAX + '" placeholder="e.g. Temporary tin shed of about 8 sq m on the front side; boundary wall extended 2 ft into the road" ' +
            'data-rules="required|min:5|max:' + ENCROACH_MAX + '|text" data-label="Details of encroachment" data-encroach-details></textarea>' +
            '<div class="char-count"><span data-char-count>0</span> / ' + ENCROACH_MAX + ' characters</div><div class="invalid-feedback"></div></div></div></div>' +

            stepHtml(3, 'fa-road', 'Road &amp; corner') +
            '<div class="form-grid"><div class="form-group"><label class="form-label" for="road_width">Road size <span class="required">*</span></label>' +
            '<div class="input-combo"><input type="text" id="road_width" name="road_width" class="form-control" inputmode="numeric" maxlength="3" placeholder="e.g. 40" autocomplete="off" ' +
            'data-digits-only data-rules="required|integer|between:1,999" data-label="Road size">' +
            '<select name="road_width_unit" class="form-select" aria-label="Road size unit" data-rules="required" data-label="Road size unit">' + L.options(ROAD_UNITS, 'FT') + '</select></div>' +
            '<div class="form-hint">Whole number only (no decimals), in feet or meter.</div><div class="invalid-feedback"></div></div>' +
            P.yesNoField({ name: 'is_corner', label: 'Is it a corner plot?' }) + '</div>' +

            stepHtml(4, 'fa-camera', 'Site photos') +
            P.uploaderHtml('Site photos', { name: 'site_photos', images: true, required: true, hint: 'Take clear photos of the plot from different sides.' }) +

            stepHtml(5, 'fa-location-crosshairs', 'Geo coordinates', 'of the property') +
            '<div class="form-grid"><div class="form-group"><label class="form-label" for="latitude">Latitude <span class="required">*</span></label>' +
            '<input type="text" id="latitude" name="latitude" class="form-control mono" inputmode="decimal" maxlength="12" placeholder="e.g. 25.18254610" autocomplete="off" data-decimals="8" data-signed data-rules="required|latitude" data-label="Latitude"><div class="invalid-feedback"></div></div>' +
            '<div class="form-group"><label class="form-label" for="longitude">Longitude <span class="required">*</span></label>' +
            '<input type="text" id="longitude" name="longitude" class="form-control mono" inputmode="decimal" maxlength="13" placeholder="e.g. 75.83273620" autocomplete="off" data-decimals="8" data-signed data-rules="required|longitude" data-label="Longitude"><div class="invalid-feedback"></div></div></div>' +
            '<div class="geo-row"><button type="button" class="btn btn-outline btn-sm" data-geo-locate>' + icon('fa-location-crosshairs') + ' <span>Use my current location</span></button>' +
            '<small class="text-muted" data-geo-status>Up to 8 digits after the decimal point. Use the button when you are at the plot.</small></div>' +
            '</div>';
    }
    function initSiteCheck(form) {
        const box = form.querySelector('[data-site-check]');
        const len = box.querySelector('#site_length'), wid = box.querySelector('#site_width'), unit = box.querySelector('[data-site-unit]'), out = box.querySelector('[data-site-variance]');
        const details = box.querySelector('[data-encroach-details]'), counter = box.querySelector('[data-char-count]');
        const recValue = Number(box.getAttribute('data-recorded-value')), recUnit = box.getAttribute('data-recorded-unit');
        function syncArea() {
            const sym = lengthSymbol(unit.value);
            box.querySelectorAll('[data-len-sym]').forEach(function (s) { s.textContent = sym; });
            const l = Number(len.value), w = Number(wid.value);
            out.className = 'site-variance';
            if (!(l > 0) || !(w > 0)) { out.innerHTML = icon('fa-circle-info') + ' <span>Enter length and width — the area is worked out and compared with the recorded area.</span>'; return; }
            const area = Math.round(l * w * 100) / 100;
            const r = variance(area, unit.value, recValue, recUnit);
            out.classList.add('is-' + (r.variance || 'none').toLowerCase());
            out.innerHTML = '<span class="site-area-calc">' + P.fmt_num(l, 2) + ' ' + e(sym) + ' × ' + P.fmt_num(w, 2) + ' ' + e(sym) + ' = <strong>' + e(L.area(area, unit.value, L.toSqm(area, unit.value))) + '</strong></span>' + varianceHtml(r);
        }
        function syncCount() {
            counter.textContent = details.value.length;
            counter.parentNode.classList.toggle('is-over', details.value.length > ENCROACH_MAX);
        }
        [len, wid].forEach(function (i) { i.addEventListener('input', syncArea); });
        unit.addEventListener('change', syncArea);
        details.addEventListener('input', syncCount);

        // geo location of the device (needs https or localhost and the user's permission)
        const geoBtn = box.querySelector('[data-geo-locate]'), geoStatus = box.querySelector('[data-geo-status]');
        geoBtn.addEventListener('click', function () {
            if (!navigator.geolocation) { geoStatus.textContent = 'This browser cannot read the location — enter the coordinates by hand.'; return; }
            geoBtn.disabled = true;
            geoStatus.textContent = 'Reading location…';
            navigator.geolocation.getCurrentPosition(function (pos) {
                [['latitude', pos.coords.latitude], ['longitude', pos.coords.longitude]].forEach(function (x) {
                    const el = box.querySelector('#' + x[0]);
                    el.value = x[1].toFixed(8);
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                });
                geoStatus.textContent = 'Location filled (accuracy about ' + Math.round(pos.coords.accuracy) + ' m). Check it before submitting.';
                geoBtn.disabled = false;
            }, function (err) {
                geoStatus.textContent = (err.code === 1 ? 'Location permission was denied.' : 'Could not read the location.') + ' Enter the coordinates by hand.';
                geoBtn.disabled = false;
            }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
        });
        syncArea();
        syncCount();
    }
    /** On top of the Engineering task: what the Sale Cell found in this round. */
    function saleResultAlert(task) {
        const role = DB.first('roles', { role_code: SALE_ROLE });
        const sale = role ? DB.first('tasks', { round_id: task.round_id, role_id: role.role_id }) : null;
        if (!sale) { return ''; }
        const by = sale.responded_by ? DB.find('users', sale.responded_by) : null;
        if (sale.task_status === 'APPROVED') {
            return '<div class="alert alert-success">' + icon('fa-circle-check') + '<span><strong>Verified by Sale Cell — property is vacant.</strong> ' +
                e((by ? by.name + ', ' : '') + P.fmt_date(sale.responded_at)) + '</span></div>';
        }
        return '<div class="alert alert-warning">' + icon('fa-hourglass-half') + '<span><strong>Sale Cell has not verified this property yet.</strong></span></div>';
    }
    /** Read-only rows shown on the task after the Engineering response. */
    function siteSummaryRows(si) {
        const r = { variance: si.variance, diff: si.difference, pct: si.difference_pct, unit: si.recorded_unit };
        const sym = lengthSymbol(si.area_unit);
        const yes = function (v) { return v ? '<span class="badge badge-danger">Yes</span>' : '<span class="badge badge-success">No</span>'; };
        const photos = P.attachmentsFor({ task_id: si.task_id, context: 'SITE_PHOTO' });
        return '<dt>Actual area</dt><dd>' + (si.length_value ? P.fmt_num(si.length_value, 2) + ' ' + e(sym) + ' × ' + P.fmt_num(si.width_value, 2) + ' ' + e(sym) + ' = ' : '') +
            '<strong>' + e(L.area(si.area_value, si.area_unit)) + '</strong><span class="site-variance-inline is-' + (si.variance || 'none').toLowerCase() + '">' + varianceHtml(r) + '</span></dd>' +
            '<dt>Encroachment</dt><dd>' + (si.encroachment === 'YES' ? '<span class="badge badge-danger">Yes</span> ' + (si.encroachment_type ? '<strong>' + e(ENCROACH_TYPES[si.encroachment_type]) + '</strong> — ' : '') + e(si.encroachment_details || '')
                : '<span class="badge badge-success">No</span>') + '</dd>' +
            (si.road_width ? '<dt>Road size</dt><dd>' + si.road_width + ' ' + (si.road_width_unit === 'M' ? 'm' : 'ft') + '</dd>' : '') +
            (si.is_corner != null ? '<dt>Corner plot</dt><dd>' + yes(si.is_corner) + '</dd>' : '') +
            (si.latitude != null ? '<dt>Geo coordinates</dt><dd class="mono">' + Number(si.latitude).toFixed(8) + ', ' + Number(si.longitude).toFixed(8) +
                ' <a href="https://www.google.com/maps?q=' + Number(si.latitude).toFixed(8) + ',' + Number(si.longitude).toFixed(8) + '" target="_blank" rel="noopener noreferrer" class="small">' + icon('fa-map-location-dot') + ' Open map</a></dd>' : '') +
            (photos.length ? '<dt>Site photos</dt><dd>' + P.attachmentChips(photos) + '</dd>' : '');
    }

    /* ================= Department-wise section: Sale (Sale Cell), Auction (Auction Cell), General (every other cell) =================
       Engineering has its site inspection instead. Any "Yes" means the property is not available (auto-recorded as Objection). */
    const SALE_ROLE = 'SALE_CELL', AUCTION_ROLE = 'AUCTION_CELL';
    const OWN_SECTION_ROLES = [SALE_ROLE, AUCTION_ROLE, SITE_ROLE]; // every other cell answers the General section
    function stepHtml(n, ic, title, sub) {
        return '<p class="site-step"><span class="step-no">' + n + '</span> ' + icon(ic) + ' ' + title + (sub ? ' <small class="text-muted step-sub">' + sub + '</small>' : '') + '</p>';
    }
    function personInput(id, label, ph, hint) {
        return '<div class="form-group"><label class="form-label" for="' + id + '">' + label + ' <span class="required">*</span></label>' +
            '<input type="text" id="' + id + '" name="' + id + '" class="form-control" maxlength="100" placeholder="' + ph + '" autocomplete="off" data-rules="required|min:3|max:100|alpha_space" data-label="' + label + '" data-letters-only>' +
            '<div class="form-hint">' + e(hint) + '</div><div class="invalid-feedback"></div></div>';
    }
    function orgInput(id) {
        return '<div class="form-group"><label class="form-label" for="' + id + '">Name of organisation / institution <span class="required">*</span></label>' +
            '<input type="text" id="' + id + '" name="' + id + '" class="form-control" maxlength="150" placeholder="e.g. Rajasthan Housing Board" autocomplete="off" data-rules="required|min:3|max:150|orgname" data-label="Name of organisation / institution"><div class="invalid-feedback"></div></div>';
    }
    /** n = number of steps already shown above (Engineering site check). */
    function sectionChecksHtml(task, n) {
        const today = DB.today();
        if (task.role_code === SITE_ROLE) { return ''; }
        let h = '<div class="site-check section-check" data-section-check>';
        if (task.role_code === SALE_ROLE) {
            h += stepHtml(++n, 'fa-file-signature', 'Sale', 'allotment to a person, or mutation if sold on') +
                P.yesNoField({ name: 'is_sale_allotted', label: 'Allotted?' }) +
                '<div class="reveal-box" data-reveal="is_sale_allotted=YES"><div class="form-grid">' +
                personInput('allottee_name', 'Name of allottee / mutation', 'e.g. Ramesh Kumar Sharma', 'If sold to someone by the original allottee, enter the mutation name. Letters and spaces only.') +
                P.dateField({ id: 'allotment_date', name: 'allotment_date', label: 'Date of allotment / mutation', required: true, max: today }) +
                '<div class="span-2">' + P.pdfField({ name: 'allotment_letter', label: 'Allotment letter / mutation letter (PDF)', required: true }) + '</div></div></div>';
        }
        if (task.role_code === AUCTION_ROLE) {
            h += stepHtml(++n, 'fa-gavel', 'Auction', 'whether the property is already auctioned') +
                P.yesNoField({ name: 'is_auctioned', label: 'Auctioned?' }) +
                '<div class="reveal-box" data-reveal="is_auctioned=YES"><div class="form-grid">' +
                P.dateField({ id: 'auction_date', name: 'auction_date', label: 'Date on which the property was auctioned', required: true, max: today }) + '</div></div>';
        }
        if (OWN_SECTION_ROLES.indexOf(task.role_code) === -1) {
            h += stepHtml(++n, 'fa-building-columns', 'General', 'allotment to an organisation / institution') +
                P.yesNoField({ name: 'is_inst_allotted', label: 'Allotment (to an organisation / institution)?' }) +
                // Yes = occupied. "In progress" has no exact allotment date yet, so the date is asked only when it is not ticked.
                '<div class="reveal-box" data-reveal="is_inst_allotted=YES"><label class="check check-card"><input type="checkbox" name="is_inst_in_process" value="1">' +
                '<span><strong>In progress of allotment</strong><br><small class="text-muted">Tick if the allotment has started but is not complete yet — the property is still treated as occupied. No allotment date is needed.</small></span></label>' +
                '<div class="form-grid">' + orgInput('inst_name') +
                '<div class="reveal-box reveal-plain" data-reveal="is_inst_in_process!=1">' +
                P.dateField({ id: 'inst_allotment_date', name: 'inst_allotment_date', label: 'Date of allotment', required: true, max: today }) + '</div></div></div>';
        }
        h += '<div class="sale-result" data-section-result role="status" aria-live="polite" hidden></div></div>';
        return h;
    }
    /** What the answers say, as one sentence (auto remark + OIC summary). get(key) reads an answer. */
    function sectionText(get) {
        const parts = [];
        if (get('is_sale_allotted')) {
            parts.push('Allotted' + (get('allottee_name') ? ' to ' + get('allottee_name') : '') + (get('allotment_date') ? ' on ' + get('allotment_date') : ''));
        }
        if (get('is_auctioned')) { parts.push('Already auctioned' + (get('auction_date') ? ' on ' + get('auction_date') : '')); }
        if (get('is_inst_allotted')) {
            const org = get('inst_name') || 'an organisation / institution';
            parts.push(get('is_inst_in_process') ? 'Allotment in progress to ' + org : 'Allotted to ' + org + (get('inst_allotment_date') ? ' on ' + get('inst_allotment_date') : ''));
        }
        return parts.length ? 'Not available — ' + parts.join('; ') + '.' : '';
    }
    function initSectionChecks(form) {
        const box = form.querySelector('[data-section-check]');
        const result = box.querySelector('[data-section-result]'), remark = form.querySelector('textarea[name="remarks"]');
        let autoText = '';
        const on = function (n) { const c = form.querySelector('[name="' + n + '"]:checked:not(:disabled)'); return !!c && (c.value === 'YES' || c.value === '1'); };
        const val = function (n) { const el = form.querySelector('[name="' + n + '"]:not(:disabled)'); return el ? el.value.trim() : ''; };
        const get = function (k) {
            if (k.indexOf('is_') === 0) { return on(k); }
            const v = val(k);
            if (/date/.test(k)) { return /^\d{2}\/\d{2}\/\d{4}$/.test(v) ? v.replace(/\//g, '-') : ''; }
            return v;
        };
        const taken = function () { return on('is_sale_allotted') || on('is_auctioned') || on('is_inst_allotted'); };
        function sync(fromUser) {
            const t = taken();
            const answered = Array.prototype.every.call(box.querySelectorAll('.yn'), function (g) { return g.querySelector('input:disabled') || g.querySelector('input:checked'); });
            result.hidden = !t && !answered;
            result.className = 'sale-result ' + (t ? 'is-allotted' : 'is-ready');
            result.innerHTML = t ? icon('fa-circle-xmark') + ' <span><strong>Not available</strong> — recorded as Objection.</span>'
                : icon('fa-circle-check') + ' <span><strong>No allotment / auction found</strong> by your section (No Objection).</span>';
            const cur = remark.value.trim(), text = sectionText(get);
            if (text && (!cur || cur === autoText)) { remark.value = autoText = text; }
            else if (!text && cur && cur === autoText) { remark.value = autoText = ''; }
        }
        form.addEventListener('change', function (ev) { if (ev.target.name !== 'decision' && ev.target.name !== 'remarks') { sync(true); } });
        box.addEventListener('input', function () { sync(false); });
        if (P.Validation) {
            P.Validation.addCheck(form, function () {
                const d = form.querySelector('input[name="decision"]:checked');
                return taken() && d && d.value === 'APPROVED' ? { field: d, message: NOT_AVAILABLE_MSG } : null;
            });
        }
        sync(false);
    }
    /** Read-only rows of the section answers (task page). */
    P.sectionCheckRows = function (sc) {
        const yes = '<span class="badge badge-danger">Yes</span> ', no = '<span class="badge badge-success">No</span>';
        const d = function (v) { return e(P.fmt_date(v)); };
        let h = '';
        if (sc.is_sale_allotted != null) {
            h += '<dt>Allotted / mutation</dt><dd>' + (sc.is_sale_allotted ? yes + e(sc.allottee_name) + ' · ' + d(sc.allotment_date) : no) + '</dd>';
        }
        if (sc.is_auctioned != null) { h += '<dt>Auctioned</dt><dd>' + (sc.is_auctioned ? yes + d(sc.auction_date) : no) + '</dd>'; }
        if (sc.is_inst_allotted != null) {
            h += '<dt>Organisation / institution</dt><dd>' + (!sc.is_inst_allotted ? no
                : sc.is_inst_in_process ? yes + '<span class="badge badge-warning">In progress</span> ' + e(sc.inst_name)
                : yes + e(sc.inst_name) + ' · ' + d(sc.inst_allotment_date)) + '</dd>';
        }
        return h;
    };
    /** One line for the OIC rounds table. */
    P.sectionCheckText = function (sc) {
        return sectionText(function (k) {
            const v = sc[k];
            if (k.indexOf('is_') === 0) { return !!v; }
            return /date/.test(k) ? (v ? P.fmt_date(v) : '') : v;
        }) || 'No allotment / auction found';
    };

    /* ================= My Tasks ================= */
    const TABS = [['', 'All', 'fa-list'], ['pending', 'Pending', 'fa-hourglass-half'], ['overdue', 'Overdue', 'fa-stopwatch'],
        ['approved', 'Approved', 'fa-check'], ['rejected', 'Rejected', 'fa-xmark'], ['closed', 'Not required', 'fa-minus']];

    P.route('/role/tasks', PANEL, function (ctx) {
        const q = ctx.query;
        const today = DB.today();
        const cur = q.status || '';
        let rows = tasksFor(ctx.user.user_id);
        const f = {
            pending: isPendingOpen,
            overdue: function (t) { return isPendingOpen(t) && t.sla_due_date < today; },
            approved: function (t) { return t.task_status === 'APPROVED'; },
            rejected: function (t) { return t.task_status === 'REJECTED'; },
            closed: function (t) { return t.view_status === 'NOT_REQUIRED'; }
        };
        if (f[cur]) { rows = rows.filter(f[cur]); }
        if (q.scheme_id) { rows = rows.filter(function (t) { return String(DB.find('properties', t.property_id).scheme_id) === q.scheme_id; }); }
        const p = P.paginate(rows, ctx, {
            sort: 'due', dir: 'asc', filters: ['status', 'scheme_id'],
            search: function (t) { return [t.plot_no, t.property_code, t.scheme_name, t.sector_name]; },
            sortable: {
                due: function (t) { return t.sla_due_date; }, sent: function (t) { return t.initiated_at; }, plot: function (t) { return t.plot_no; },
                scheme: function (t) { return t.scheme_name; }, status: function (t) { return t.view_status; }
            }
        });
        const schemes = {};
        DB.all('schemes').forEach(function (s) { schemes[s.scheme_id] = s.scheme_name + ' (' + s.scheme_code + ')'; });
        const filters = '<select name="scheme_id" class="form-select" aria-label="Scheme" data-auto-submit>' + L.options(schemes, q.scheme_id, 'All schemes') + '</select>' +
            (cur ? '<input type="hidden" name="status" value="' + e(cur) + '">' : '');
        return {
            title: 'My Tasks',
            html: P.pageHeader(icon('fa-clipboard-list') + ' My Tasks', 'Properties assigned to you for verification. Open a task to review records and submit your report.') +
                '<nav class="tabs" aria-label="Task status">' + TABS.map(function (t) {
                    return '<a href="' + P.url('/role/tasks', { status: t[0], scheme_id: q.scheme_id || '' }) + '" class="tab' + (cur === t[0] ? ' active' : '') + '"' + (cur === t[0] ? ' aria-current="page"' : '') + '>' +
                        icon(t[2]) + ' ' + t[1] + '</a>';
                }).join('') + '</nav>' +
                '<div class="card">' + P.list_toolbar(p, 'Search plot no., property code, scheme, sector…', filters) +
                '<div class="table-wrap"><table class="table table-stack"><thead><tr><th>Property</th>' + P.th_sort(p, 'scheme', 'Scheme / Sector') + P.th_sort(p, 'plot', 'Plot') +
                '<th>Area</th><th>Round</th>' + P.th_sort(p, 'sent', 'Received') + P.th_sort(p, 'due', 'Due (SLA)') + P.th_sort(p, 'status', 'Status') + '<th class="text-right">Action</th></tr></thead><tbody>' +
                (p.items.length ? p.items.map(function (t) {
                    const pending = isPendingOpen(t);
                    const late = pending && t.sla_due_date < today;
                    return '<tr class="' + (t.view_status === 'APPROVED' ? 'row-success' : (t.view_status === 'REJECTED' ? 'row-danger' : '')) + '">' +
                        '<td data-label="Property"><span class="mono">' + e(t.property_code) + '</span></td>' +
                        '<td data-label="Scheme / Sector">' + e(t.scheme_name) + '<span class="cell-sub">' + e(t.sector_name) + '</span></td>' +
                        '<td data-label="Plot"><strong>' + e(t.plot_no) + '</strong><span class="cell-sub">' + e(L.PROPERTY_TYPES[t.property_type]) + '</span></td>' +
                        '<td data-label="Area" class="nowrap">' + (t.area_sqm == null ? '—' : P.fmt_num(t.area_sqm, 2) + ' sq m') + '</td><td data-label="Round">R' + t.round_no + '</td>' +
                        '<td data-label="Received">' + e(P.fmt_date(t.initiated_at)) + '</td>' +
                        '<td data-label="Due"><span class="' + (late ? 'text-danger' : '') + '">' + e(P.fmt_date(t.sla_due_date)) + '</span>' +
                        (late ? ' <span class="badge badge-danger">' + icon('fa-stopwatch') + ' Overdue</span>' : '') + '</td>' +
                        '<td data-label="Status">' + P.status_badge(t.view_status) +
                        (!pending && t.responded_at ? '<span class="cell-sub">' + e((t.responded_by_name || '') + ' · ' + P.fmt_date(t.responded_at)) + '</span>' : '') + '</td>' +
                        '<td class="actions">' + (pending && Auth.can('task.respond') ? '<a href="' + P.BASE + '/role/tasks/' + t.task_id + '" class="btn btn-primary btn-sm">' + icon('fa-clipboard-check') + ' <span>Verify</span></a>'
                            : '<a href="' + P.BASE + '/role/tasks/' + t.task_id + '" class="btn btn-light btn-sm">' + icon('fa-eye') + ' <span>View</span></a>') + '</td></tr>';
                }).join('') : P.empty_state(9, p.hasFilters(), 'No task assigned to you yet')) +
                '</tbody></table></div>' + P.pagination(p) + '</div>'
        };
    }, 'task.view');

    /* ================= Task detail + decision ================= */
    P.route('/role/tasks/{id}', PANEL, function (ctx) {
        const me = ctx.user.user_id;
        const raw = DB.find('tasks', ctx.params.id);
        if (!raw || !DB.exists('task_assignees', { task_id: raw.task_id, user_id: me })) {
            return P.notFound('Task not found or not assigned to you.');
        }
        const task = enrich(raw);
        if (task.task_status !== 'PENDING' && task.responded_by !== me) {
            P.flash('info', 'Plot ' + task.plot_no + ' (' + task.scheme_name + ') was already ' + P.label(task.task_status).toLowerCase() + ' by ' + task.responded_by_name + ' of your section.');
            P.go('/role/tasks');
            return false;
        }
        task.assignees = DB.where('task_assignees', { task_id: task.task_id }).map(function (a) {
            const u = DB.find('users', a.user_id); return u.name + ' (' + u.designation + ')';
        }).sort().join(', ');
        const canRespond = task.view_status === 'PENDING' && Auth.can('task.respond');
        const late = canRespond && task.sla_due_date < DB.today();
        const history = DB.where('rounds', function (r) { return r.property_id === task.property_id && r.round_id !== task.round_id; })
            .sort(function (a, b) { return b.round_no - a.round_no; })
            .map(function (r) {
                const t = DB.first('tasks', { round_id: r.round_id, role_id: task.role_id });
                if (!t) { return null; }
                const ru = t.responded_by ? DB.find('users', t.responded_by) : null;
                return { task_id: t.task_id, round_no: r.round_no, task_status: t.task_status, remarks: t.remarks, responded_by_name: ru ? ru.name : null, responded_at: t.responded_at, result: r.result };
            }).filter(Boolean);

        let html = P.pageHeader(icon('fa-clipboard-check') + ' Plot ' + e(task.plot_no) + ' ' + P.status_badge(task.view_status),
            e(task.property_code + ' · ' + task.scheme_name + ' · ' + task.sector_name + ' · Round R' + task.round_no + ' · ' + task.role_name),
            '<a href="' + P.url('/role/tasks', { status: 'pending' }) + '" class="btn btn-light">' + icon('fa-arrow-left') + ' <span>Back to my tasks</span></a>');
        if (task.view_status === 'NOT_REQUIRED') {
            html += '<div class="alert alert-info">' + icon('fa-circle-info') + '<span>This round was already closed (another section rejected the property), so your response is no longer required. If the OIC re-sends it, you will get a new task.</span></div>';
        } else if (late) {
            html += '<div class="alert alert-warning">' + icon('fa-stopwatch', 'fa-shake') + '<span>SLA due date ' + e(P.fmt_date(task.sla_due_date)) + ' has passed. Please respond as soon as possible.</span></div>';
        }
        if (task.role_code === SITE_ROLE) { html += saleResultAlert(task); }
        html += '<div class="grid-2"><div class="card"><div class="card-header"><h3 class="card-title">' + icon('fa-building') + ' Property details</h3></div><div class="card-body"><dl class="detail-list">' +
            '<dt>Property code</dt><dd class="mono">' + e(task.property_code) + '</dd><dt>Scheme</dt><dd>' + e(task.scheme_name + ' (' + task.scheme_code + ')') + '</dd>' +
            '<dt>Area</dt><dd>' + e(task.area) + '</dd>' +
            '<dt>Scheme type</dt><dd>' + e(L.SCHEME_TYPES[task.scheme_type]) + '</dd><dt>Sector</dt><dd>' + e(task.sector_name) + '</dd>' +
            '<dt>Plot no.</dt><dd><strong>' + e(task.plot_no) + '</strong></dd><dt>Plot type</dt><dd>' + e(L.PROPERTY_TYPES[task.property_type]) + '</dd>' +
            '<dt>Plot area</dt><dd>' + e(L.area(task.area_value, task.area_unit, task.area_sqm)) + '</dd>' +
            P.propertyPriceRows(DB.find('properties', task.property_id)) +
            (task.property_remarks ? '<dt>Remarks</dt><dd>' + e(task.property_remarks) + '</dd>' : '') + '</dl></div></div>' +
            '<div class="card"><div class="card-header"><h3 class="card-title">' + icon('fa-layer-group') + ' Verification task</h3></div><div class="card-body"><dl class="detail-list">' +
            '<dt>Section</dt><dd>' + e(task.role_name) + '</dd><dt>Round</dt><dd>R' + task.round_no + ' ' + P.status_badge(task.round_status) + '</dd>' +
            '<dt>Sent by</dt><dd>' + e(task.initiated_by_name + ' (' + task.initiated_by_designation + ')') + '</dd><dt>Received on</dt><dd>' + e(P.fmt_datetime(task.initiated_at)) + '</dd>' +
            '<dt>Due (SLA)</dt><dd class="' + (late ? 'text-danger' : '') + '">' + e(P.fmt_date(task.sla_due_date)) + '</dd>' +
            '<dt>Assigned to</dt><dd>' + e(task.assignees || '—') + ' <small class="text-muted">(any one may respond)</small></dd>' +
            (task.resend_reason ? '<dt>Re-verification reason</dt><dd>' + e(task.resend_reason) + '</dd>' : '') +
            (!canRespond && task.responded_at ? '<dt>Response</dt><dd>' + P.status_badge(task.task_status) + '</dd><dt>Responded by</dt><dd>' + e((task.responded_by_name || '') + ' · ' + P.fmt_datetime(task.responded_at)) + '</dd>' +
                (task.site_inspection ? siteSummaryRows(task.site_inspection) : '') + (task.section_check ? P.sectionCheckRows(task.section_check) : '') +
                '<dt>Remark</dt><dd>' + e(task.remarks || '—') + '</dd>' +
                '<dt>Attachments</dt><dd>' + (P.attachmentsFor({ task_id: task.task_id }).length ? P.attachmentChips(P.attachmentsFor({ task_id: task.task_id })) : '—') + '</dd>' : '') + '</dl></div></div></div>';
        if (canRespond) {
            const isSite = task.role_code === SITE_ROLE, isSale = task.role_code === SALE_ROLE, isAuction = task.role_code === AUCTION_ROLE;
            html += '<div class="card response-card" id="respond"><div class="card-header"><h3 class="card-title">' +
                (isSite ? icon('fa-ruler-combined') + ' Site inspection'
                    : isSale ? icon('fa-file-signature') + ' Sale check' : isAuction ? icon('fa-gavel') + ' Auction check' : icon('fa-building-columns') + ' General check') +
                '</h3><span class="text-muted small">Once submitted, the response cannot be changed.</span></div><div class="card-body">' +
                '<form method="post" data-action="task-respond" novalidate data-validate data-response-form data-confirm="' + (isSite ? 'Submit the site report? It cannot be changed afterwards.' : 'Submit your report? It cannot be changed afterwards.') + '" data-confirm-ok="Yes, submit">' +
                '<input type="hidden" name="id" value="' + task.task_id + '">' + (isSite ? siteCheckHtml(task) : '') + sectionChecksHtml(task, 0) +
                '<div class="form-group"><label class="form-label" for="remarks">Remark <small class="text-muted">(optional)</small></label>' +
                '<textarea id="remarks" name="remarks" class="form-control" rows="3" maxlength="1000" placeholder="Optional remark — e.g. Record clear, file no. 123" data-rules="max:1000|text" data-label="Remark"></textarea><div class="invalid-feedback"></div></div>' +
                P.uploaderHtml('Supporting documents / photos') +
                '<button type="submit" class="btn btn-primary btn-lg" data-loading-text="Submitting…">' + icon('fa-paper-plane') + ' <span>' + (isSite ? 'Submit site report' : 'Submit report') + '</span></button></form></div></div>';
        }
        html += '<div class="card"><div class="card-header"><h3 class="card-title">' + icon('fa-clock-rotate-left') + ' ' + e(task.role_name) + ' — earlier rounds of this property</h3></div>' +
            '<div class="table-wrap"><table class="table table-stack"><thead><tr><th>Round</th><th>Response</th><th>Remark</th><th>By / Date</th><th>Round result</th></tr></thead><tbody>' +
            (history.length ? history.map(function (h) {
                return '<tr><td data-label="Round">R' + h.round_no + '</td><td data-label="Response">' + P.status_badge(h.task_status) + '</td><td data-label="Remark">' + e(h.remarks || '—') + P.attachmentChips(P.attachmentsFor({ task_id: h.task_id })) + '</td>' +
                    '<td data-label="By / Date">' + e((h.responded_by_name || '—') + ' · ' + P.fmt_date(h.responded_at)) + '</td><td data-label="Result">' + (h.result ? P.status_badge(h.result) : '—') + '</td></tr>';
            }).join('') : P.empty_state(5, false, 'This is the first round for this property')) + '</tbody></table></div></div>';
        html += P.propertyTimeline(task.property_id, 'Property action timeline — every action on Plot ' + task.plot_no);
        return {
            title: 'Verify Plot ' + task.plot_no, html: html,
            after: function (root) {
                const f = root.querySelector('form[data-response-form]');
                if (f) {
                    P.initUploader(f);
                    if (f.querySelector('[data-site-check]')) { initSiteCheck(f); }
                    if (f.querySelector('[data-section-check]')) { initSectionChecks(f); }
                }
            }
        };
    }, 'task.view');

})(window);
