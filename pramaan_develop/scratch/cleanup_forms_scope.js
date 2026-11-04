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

// Move declarations to top of initPropertyBulk
const targetTop = `        const count = form.querySelector('[data-pb-count]');
        const saveBtn = form.querySelector('[data-pb-save]');
        const KEYS = ['plot_type_id', 'area_value', 'unit_id'];`;

const replaceTop = `        const count = form.querySelector('[data-pb-count]');
        const saveBtn = form.querySelector('[data-pb-save]');
        const pbLen = form.querySelector('[data-pb-length]');
        const pbWid = form.querySelector('[data-pb-width]');
        const pbArea = form.querySelector('[data-pb-default="area_value"]');
        const pbHint = form.querySelector('[data-pb-area-calc-hint]');
        const KEYS = ['plot_type_id', 'area_value', 'unit_id'];`;

forms = safeReplace(forms, targetTop, replaceTop);

// Remove duplicate const declarations from lower down in initPropertyBulk
const targetOldLower = `        /* --- area calculation from length × width --- */
        const pbLen = form.querySelector('[data-pb-length]');
        const pbWid = form.querySelector('[data-pb-width]');
        const pbArea = form.querySelector('[data-pb-default="area_value"]');
        const pbHint = form.querySelector('[data-pb-area-calc-hint]');

        function syncBulkArea() {`;

const replaceOldLower = `        /* --- area calculation from length × width --- */
        function syncBulkArea() {`;

forms = safeReplace(forms, targetOldLower, replaceOldLower);

fs.writeFileSync('public/assets/js/forms.js', forms, 'utf8');
console.log('Cleaned forms.js scoping');

