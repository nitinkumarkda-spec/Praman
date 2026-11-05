const fs = require('fs');
let content = fs.readFileSync('public/assets/js/core.js', 'utf8');

const regex = /home:\s*function\s*\(\)\s*\{[\s\S]*?return '\/admin\/dashboard';\s*\}/;
const replacement = `home: function () {
            const u = Auth.user();
            if (!u) { return '/login'; }
            const roleId = Number(u.role_id);
            if (roleId === 1 || Auth.can('role.view', 'user.view', 'master.view', 'settings.view', 'audit.view')) {
                return '/admin/dashboard';
            }
            if (roleId === 7 || Auth.can('verification.send')) {
                return '/oic/dashboard';
            }
            const rObj = roleId ? DB.find('roles', roleId) : null;
            if (roleId > 1 && rObj && rObj.status_id) {
                return '/role/dashboard';
            }
            if (roleId === 6 || (Auth.can('scheme.view') && Auth.can('property.create', 'scheme.create'))) {
                return '/data-entry/dashboard';
            }
            if (roleId > 1) {
                return '/role/dashboard';
            }
            return '/admin/dashboard';
        }`;

if (!regex.test(content)) {
    console.error('Regex match failed!');
    process.exit(1);
}

content = content.replace(regex, replacement);
fs.writeFileSync('public/assets/js/core.js', content, 'utf8');
console.log('Successfully updated core.js Auth.home!');

