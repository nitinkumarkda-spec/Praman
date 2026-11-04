const fs = require('fs');

let forms = fs.readFileSync('public/assets/js/forms.js', 'utf8');

function safeReplace(str, target, replacement) {
    const targetNorm = target.replace(/\r\n/g, '\n');
    const strNorm = str.replace(/\r\n/g, '\n');
    if (!strNorm.includes(targetNorm)) {
        throw new Error('Target not found in content: ' + targetNorm.substring(0, 80));
    }
    return strNorm.replace(targetNorm, replacement);
}

// 1. In initPropertyForm:
const targetInitPropertyForm = `        if (area && unit) {
            area.addEventListener('input', updateArea);
            unit.addEventListener('change', updateArea);
            updateArea();
        }`;

const replaceInitPropertyForm = `        const lenEl = form.querySelector('#area_length');
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
        }`;

forms = safeReplace(forms, targetInitPropertyForm, replaceInitPropertyForm);

// 2. In initPropertyBulk:
// Add check in generate()
const targetGenerate = `        function generate() {
            const rows = modeBox(mode()).querySelectorAll('[data-pb-row]');`;

const replaceGenerate = `        function generate() {
            if (pbLen && pbWid) {
                const lStr = pbLen.value.trim();
                const wStr = pbWid.value.trim();
                if ((lStr !== '' && wStr === '') || (lStr === '' && wStr !== '')) {
                    Praman.toast('error', 'Please enter both Length and Width to calculate Area (or leave both blank).');
                    (lStr === '' ? pbLen : pbWid).focus();
                    return;
                }
            }
            const rows = modeBox(mode()).querySelectorAll('[data-pb-row]');`;

forms = safeReplace(forms, targetGenerate, replaceGenerate);

// 3. In edit(i) inside initPropertyBulk:
const targetModalArea = `                '<div class="form-group span-2"><label class="form-label" for="pbm_area">Area</label><div class="pb-area"><input type="text" id="pbm_area" class="form-control" inputmode="decimal" maxlength="13" value="' + esc(value(p, 'area_value')) + '" data-decimals="2">' +
                '<select id="pbm_unit" class="form-select" aria-label="Area unit">' + L.options(L.activeMap('units', 'unit_id', 'name', value(p, 'unit_id')), value(p, 'unit_id')) + '</select></div></div>' +`;

const replaceModalArea = `                '<div class="form-group span-2"><label class="form-label">Area (optional)</label>' +
                '<div class="dim-row">' +
                '<div class="form-group"><label class="form-label text-muted" for="pbm_length">Length</label><input type="text" id="pbm_length" class="form-control" inputmode="decimal" maxlength="10" placeholder="e.g. 30.00" data-decimals="2"></div>' +
                '<span class="dim-x" aria-hidden="true">×</span>' +
                '<div class="form-group"><label class="form-label text-muted" for="pbm_width">Width</label><input type="text" id="pbm_width" class="form-control" inputmode="decimal" maxlength="10" placeholder="e.g. 40.50" data-decimals="2"></div>' +
                '<div class="form-group"><label class="form-label text-muted" for="pbm_area">Calculated Area</label><div class="pb-area"><input type="text" id="pbm_area" class="form-control" readonly placeholder="Auto (L × W)" data-decimals="2" value="' + esc(value(p, 'area_value')) + '" tabindex="-1" style="cursor: not-allowed;">' +
                '<select id="pbm_unit" class="form-select" aria-label="Area unit">' + L.options(L.activeMap('units', 'unit_id', 'name', value(p, 'unit_id')), value(p, 'unit_id')) + '</select></div></div></div>' +
                '<div class="form-hint" id="pbm_hint">Enter Length and Width — Area is auto-calculated and non-editable.</div></div>' +`;

forms = safeReplace(forms, targetModalArea, replaceModalArea);

// Add syncPbmArea in edit(i) right after const $ = function (s) { return m.querySelector(s); };
const targetModalClose = `            const $ = function (s) { return m.querySelector(s); };
            const lastFocus = document.activeElement;`;

const replaceModalClose = `            const $ = function (s) { return m.querySelector(s); };
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
            $('#pbm_width').addEventListener('input', syncPbmArea);`;

forms = safeReplace(forms, targetModalClose, replaceModalClose);

// 4. In initPropertyBulk event bindings:
const targetBulkEvents = `        scheme.addEventListener('change', loadSectors);
        sector.addEventListener('change', sync);`;

const replaceBulkEvents = `        /* --- area calculation from length × width --- */
        const pbLen = form.querySelector('[data-pb-length]');
        const pbWid = form.querySelector('[data-pb-width]');
        const pbArea = form.querySelector('[data-pb-default="area_value"]');
        const pbHint = form.querySelector('[data-pb-area-calc-hint]');

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
        sector.addEventListener('change', sync);`;

forms = safeReplace(forms, targetBulkEvents, replaceBulkEvents);

fs.writeFileSync('public/assets/js/forms.js', forms, 'utf8');
console.log('Updated forms.js successfully');

