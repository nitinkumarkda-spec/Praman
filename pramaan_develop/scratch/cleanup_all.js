const fs = require('fs');

// 1. PropertyService.php
let ps = fs.readFileSync('app/Services/PropertyService.php', 'utf8');
ps = ps.replace(/use App\\Models\\Role;\r?\n/, '');
ps = ps.replace(/use App\\Models\\SchemeUser;\r?\n/, '');
ps = ps.replace(/use App\\Models\\User;\r?\n/, '');
const snapRegex = /\s*\$snap = 0;\s*foreach \(SchemeUser::where\(\['scheme_id' => \$d\['scheme_id'\], 'is_active' => 1\]\) as \$m\) \{[\s\S]*?\$snap\+\+;\s*\}\s*\}/;
ps = ps.replace(snapRegex, '');
fs.writeFileSync('app/Services/PropertyService.php', ps, 'utf8');
console.log('Cleaned PropertyService.php');

// 2. StateService.php
let ss = fs.readFileSync('app/Services/StateService.php', 'utf8');
ss = ss.replace(/\r?\n\s*\$t\['property_users'\] = \[\];/, '');
fs.writeFileSync('app/Services/StateService.php', ss, 'utf8');
console.log('Cleaned StateService.php');

// 3. database/schema.sql
let schema = fs.readFileSync('database/schema.sql', 'utf8');
const tableRegex = /-- snapshot of the scheme's role users, taken when the property is created\r?\nCREATE TABLE property_users \([\s\S]*?\) ENGINE=InnoDB;\r?\n/;
schema = schema.replace(tableRegex, '');
fs.writeFileSync('database/schema.sql', schema, 'utf8');
console.log('Cleaned database/schema.sql');

// 4. dataentry.js
let de = fs.readFileSync('public/assets/js/pages/dataentry.js', 'utf8');
de = de.replace(/\r?\n\s*const roleUsers = DB\.Property\.roleUsers\(prop\.property_id\);/, '');
fs.writeFileSync('public/assets/js/pages/dataentry.js', de, 'utf8');
console.log('Cleaned dataentry.js');

// 5. db.js
let db = fs.readFileSync('public/assets/js/db.js', 'utf8');
db = db.replace(/,\r?\n\s*roleUsers: function \(propertyId\) \{\r?\n\s*return \[\];\r?\n\s*\}/, '');
fs.writeFileSync('public/assets/js/db.js', db, 'utf8');
console.log('Cleaned db.js');

