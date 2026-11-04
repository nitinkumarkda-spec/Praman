const fs = require('fs');

// 1. Fix reports.js
let rep = fs.readFileSync('public/assets/js/pages/reports.js', 'utf8');
rep = rep.replace(/cells \+= '<td>' \+ \(pRow\.reserve_price \? P\.fmt_rupees\(pRow\.reserve_price\) : '—'\) \+ '<\/td>';\s*else if \(c\.id === 'created'\) \{/, "cells += '<td>' + (pRow.reserve_price ? P.fmt_rupees(pRow.reserve_price) : '—') + '</td>';\n                        } else if (c.id === 'created') {");
fs.writeFileSync('public/assets/js/pages/reports.js', rep, 'utf8');
console.log('Fixed reports.js');

// 2. Fix dataentry.js
let de = fs.readFileSync('public/assets/js/pages/dataentry.js', 'utf8');
de = de.replace('<div class="grid-2"><div class="card">', '<div class="card mb-3">');
de = de.replace(/\(prop\.remarks \? '<dt>Description<\/dt><dd>' \+ e\(prop\.remarks\) \+ '<\/dd>' : ''\) \+ '<\/dl><\/div><\/div>' \+\r?\n<\/div>/, "(prop.remarks ? '<dt>Description</dt><dd>' + e(prop.remarks) + '</dd>' : '') + '</dl></div></div>';");
fs.writeFileSync('public/assets/js/pages/dataentry.js', de, 'utf8');
console.log('Fixed dataentry.js');

