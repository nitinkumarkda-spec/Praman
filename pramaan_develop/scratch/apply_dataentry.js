const fs = require('fs');

// 1. Update dataentry.js
let de = fs.readFileSync('public/assets/js/pages/dataentry.js', 'utf8');

// Replace Step 4 in propertyBulkForm
const oldBulkArea = `'<div class="form-group"><label class="form-label" for="pb_area"><span class="step-no">4</span> Area' + optional + '</label>' +
            '<div class="pb-area"><input type="text" id="pb_area" name="area_value" class="form-control" inputmode="decimal" maxlength="13" placeholder="e.g. 150.50" data-decimals="2" data-rules="decimal:2|positive" data-label="Area" data-pb-default="area_value">' +
            '<select name="unit_id" class="form-select" aria-label="Area unit" data-pb-default="unit_id">' + L.options(L.activeMap('units', 'unit_id', 'name'), L.BASE_UNIT) + '</select></div><div class="invalid-feedback"></div></div>' +
            P.pdfField({ name: 'site_plan', label: 'Site plan (PDF)' }) +`;

const newBulkArea = `P.pdfField({ name: 'site_plan', label: 'Site plan (PDF)' }) +
            '<div class="form-group span-2"><label class="form-label"><span class="step-no">4</span> Area' + optional + '</label>' +
            '<div class="dim-row">' +
            '<div class="form-group"><label class="form-label text-muted" for="pb_length">Length</label>' +
            '<input type="text" id="pb_length" class="form-control" inputmode="decimal" maxlength="10" placeholder="e.g. 30.00" data-decimals="2" data-rules="decimal:2|positive" data-label="Length" data-pb-length autocomplete="off"><div class="invalid-feedback"></div></div>' +
            '<span class="dim-x" aria-hidden="true">×</span>' +
            '<div class="form-group"><label class="form-label text-muted" for="pb_width">Width</label>' +
            '<input type="text" id="pb_width" class="form-control" inputmode="decimal" maxlength="10" placeholder="e.g. 40.50" data-decimals="2" data-rules="decimal:2|positive" data-label="Width" data-pb-width autocomplete="off"><div class="invalid-feedback"></div></div>' +
            '<div class="form-group"><label class="form-label text-muted" for="pb_area">Calculated Area</label>' +
            '<div class="pb-area"><input type="text" id="pb_area" name="area_value" class="form-control" readonly placeholder="Auto (L × W)" data-decimals="2" data-rules="decimal:2|positive" data-label="Area" data-pb-default="area_value" tabindex="-1" style="cursor: not-allowed;">' +
            '<select name="unit_id" class="form-select" aria-label="Area unit" data-pb-default="unit_id">' + L.options(L.activeMap('units', 'unit_id', 'name'), L.BASE_UNIT) + '</select></div>' +
            '<div class="invalid-feedback"></div></div></div>' +
            '<div class="form-hint" data-pb-area-calc-hint>Enter Length and Width — Area is automatically calculated (Length × Width) and is not editable.</div>' +
            '</div>' +`;

// Normalize line endings for replacement
function safeReplace(str, target, replacement) {
    const targetNorm = target.replace(/\r\n/g, '\n');
    const strNorm = str.replace(/\r\n/g, '\n');
    if (!strNorm.includes(targetNorm)) {
        throw new Error('Target not found in content!');
    }
    return strNorm.replace(targetNorm, replacement);
}

de = safeReplace(de, oldBulkArea, newBulkArea);

// Also replace in propertyForm (Edit)
const oldEditArea = `'<div class="form-group"><label class="form-label" for="area_value">Area' + optional + '</label>' +
            '<div class="pb-area"><input type="text" id="area_value" name="area_value" class="form-control" inputmode="decimal" maxlength="13" value="' + e(v('area_value')) + '" placeholder="e.g. 150.50" data-decimals="2" data-rules="decimal:2|positive" data-label="Area" data-area-value>' +
            '<select id="unit_id" name="unit_id" class="form-select" aria-label="Area unit" data-area-unit>' + L.options(L.activeMap('units', 'unit_id', 'name', v('unit_id')), v('unit_id', L.BASE_UNIT)) + '</select></div>' +
            '<div class="form-hint" data-area-preview></div><div class="invalid-feedback"></div></div>' +
            P.pdfField({ name: 'site_plan', label: 'Site plan (PDF)', current: P.propertyDoc(prop.property_id, 'SITE_PLAN') }) +`;

const newEditArea = `P.pdfField({ name: 'site_plan', label: 'Site plan (PDF)', current: P.propertyDoc(prop.property_id, 'SITE_PLAN') }) +
            '<div class="form-group span-2"><label class="form-label">Area' + optional + '</label>' +
            '<div class="dim-row">' +
            '<div class="form-group"><label class="form-label text-muted" for="area_length">Length</label><input type="text" id="area_length" class="form-control" inputmode="decimal" maxlength="10" placeholder="e.g. 30.00" data-decimals="2" data-rules="decimal:2|positive" data-label="Length" autocomplete="off"><div class="invalid-feedback"></div></div>' +
            '<span class="dim-x" aria-hidden="true">×</span>' +
            '<div class="form-group"><label class="form-label text-muted" for="area_width">Width</label><input type="text" id="area_width" class="form-control" inputmode="decimal" maxlength="10" placeholder="e.g. 40.50" data-decimals="2" data-rules="decimal:2|positive" data-label="Width" autocomplete="off"><div class="invalid-feedback"></div></div>' +
            '<div class="form-group"><label class="form-label text-muted" for="area_value">Calculated Area</label><div class="pb-area"><input type="text" id="area_value" name="area_value" class="form-control" readonly value="' + e(v('area_value')) + '" placeholder="Auto (L × W)" data-decimals="2" data-rules="decimal:2|positive" data-label="Area" data-area-value tabindex="-1" style="cursor: not-allowed;">' +
            '<select id="unit_id" name="unit_id" class="form-select" aria-label="Area unit" data-area-unit>' + L.options(L.activeMap('units', 'unit_id', 'name', v('unit_id')), v('unit_id', L.BASE_UNIT)) + '</select></div>' +
            '<div class="form-hint" data-area-preview></div><div class="invalid-feedback"></div></div></div>' +
            '<div class="form-hint" data-area-calc-hint>Enter Length and Width — Area is auto-calculated and non-editable.</div></div>' +`;

de = safeReplace(de, oldEditArea, newEditArea);
fs.writeFileSync('public/assets/js/pages/dataentry.js', de, 'utf8');
console.log('Updated dataentry.js successfully');

