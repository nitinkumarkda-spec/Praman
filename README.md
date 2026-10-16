# PRAMAN
**Property Records & Auction-readiness Multi-section Assessment Network** — Kota Development Authority

Pure PHP (no framework) custom MVC · MySQL/MariaDB (InnoDB, utf8mb4) · Vanilla JS · self-hosted assets (no CDN) · light theme only.

| Phase | Scope | Status |
|---|---|---|
| 1 | Core MVC, database, auth & security, assets, layout | ✅ Done |
| 2 | Super Admin: Role Master, User Master (reset pwd, unlock, activate), Audit Log, dashboard | ✅ Done |
| 3 | Data Entry: Scheme Master (multi-sector repeater, "Done" check, role-user mapping, edit), Property Register | ✅ Done |
| 4 | OIC: property list, send for verification (single/bulk), verification status, rounds, re-send, simulate response | ✅ Done |
| 5 | Role User panel: dashboard, My Tasks (pending / overdue / approved / rejected), verify page with Approve / Reject | ✅ Done |
| Next | Validity expiry / re-verification, auction dispatch | ⏳ |

### How to test the full flow
1. **Super Admin** (`superadmin`) → *Role Master*: 8 roles are seeded. Add or edit roles here. → *User Master*: add role users (Panel = Role User + pick a Role). Many users can belong to one role.
2. **Data Entry** (`dataentry`) → *Scheme Master → Create Scheme*:
   - fill in the details and add sectors with **"Add another sector"**
   - tick **"Sector add karna complete / Done"**
   - in **Role – user mapping**, pick at least one user for every *Mandatory* role
   - save. The save is refused if there are no sectors, the Done box is unticked, or a mandatory role has no user.
3. **Data Entry** → *Add Property*:
   - Step 1: pick a scheme. Only schemes with an active sector appear.
   - Step 2: pick a sector. The list loads by AJAX.
   - Step 3: answer **Yes** to *"Is property ko Scheme [X] ke Sector [Y] me create karna hai?"*
   - enter the plot no., type and area (sq m / sq ft) and save. Status becomes **Listed**, and the scheme's role users are copied onto the property.
4. **OIC** (`oic`) → *Properties*: click **Send** on one row, or tick several rows and use **Send selected**. A round (R1) is created with one task per role, and the status becomes *Under Verification*.
5. Click **Status** on a property. The table shows Role | Assigned users | Status | Remark | Date.
6. Log in as each **role user** (e.g. `eng1`, `legal1`) → *My Tasks* → **Verify** → choose Approve or Reject:
   - **Approve** every role → round CLOSED (READY) → property **Auction Ready** (green), valid for 180 days.
   - **Reject** any one role (remark required) → round CLOSED (NOT_READY) → property **Not Ready** (red), with the reasons shown.
7. For a Not Ready property, click **Re-send**, enter a reason, and round **R2** opens. R1 stays in the round history.

> If a role has two users (e.g. `eng1` and `eng2`), either one can respond, and the first response counts. The temporary OIC "Simulate response" feature is now **off** (`verification.simulate_enabled = false`). Set it to `true` only for quick testing.

---

## 1. Requirements
- XAMPP 8.2+ (Apache 2.4 + PHP 8.2/8.3 + MariaDB 10.4+) or WAMP / MySQL 8.0+
- Apache modules: `mod_rewrite` (required), `mod_headers` (recommended), `mod_expires` (optional)
- PHP extensions: `pdo_mysql`, `mbstring`, `sodium`/argon2 (bundled with XAMPP)
- `AllowOverride All` for `htdocs` (XAMPP default)

> The code uses `declare(strict_types=1)` and PHP 8.2+ features. It runs on 8.2 (the XAMPP default) and 8.3+.

## 2. Install on XAMPP
1. Copy the project to `C:\xampp\htdocs\PARMAM` (folder name = URL path).
2. Start **Apache** and **MySQL** from the XAMPP Control Panel.
3. Open **phpMyAdmin** → `http://localhost/phpmyadmin` → **Import** → choose `database/praman_db.sql` → **Go**.
   The script creates the database `praman_db`, all tables, constraints, indexes and seed data.
   (Command line alternative: `C:\xampp\mysql\bin\mysql.exe -uroot < database\praman_db.sql`)
4. Check `config/database.php` (XAMPP default: user `root`, empty password).
5. Open **http://localhost/PARMAM/**

The base URL is detected automatically. If you serve `public/` as a virtual-host document root, it still works (`app.base_path` in `config/config.php` can force it).

## 3. Default logins
Every seeded user has the password **`Praman@123`** and is **forced to set a new password on first login**.

| Panel | Username | Notes |
|---|---|---|
| Super Admin | `superadmin` | |
| Data Entry | `dataentry` | |
| OIC Auction | `oic` | |
| Role User – Auction Cell | `auction1`, `auction2` | My Tasks → Approve / Reject |
| Role User – Lottery Cell | `lottery1`, `lottery2` | |
| Role User – Sale Cell | `sale1`, `sale2` | |
| Role User – Land-for-Land Cell | `lfl1`, `lfl2` | |
| Role User – Punarvas Cell | `punarvas1`, `punarvas2` | |
| Role User – Engineering Cell | `eng1`, `eng2` | |
| Role User – Town Planning Cell | `tp1`, `tp2` | |
| Role User – Legal Cell | `legal1`, `legal2` | |

New password rules: ≥ 10 characters with an uppercase letter, a lowercase letter, a digit and a special character. It must differ from the current password and must not contain the username.

**Locked out while testing?** (5 wrong passwords = 15-minute lock):
```sql
UPDATE praman_db.users SET locked_until = NULL, failed_attempts = 0 WHERE username = 'oic';
DELETE FROM praman_db.login_attempts;
```

## 4. Folder structure
```
PARMAM/
├── .htaccess                 routes everything to /public, blocks source folders
├── README.md
├── app/
│   ├── .htaccess             Require all denied
│   ├── routes.php            route table (middleware per route/group)
│   ├── Core/                 App, Router, Controller, Model, View, Request, Response,
│   │                         Session, Csrf, Auth, Validator, Paginator, Audit, Logger,
│   │                         Config, Database, HttpException, helpers.php
│   ├── Middleware/           Auth, Guest, Panel (RBAC), Csrf, PasswordChange
│   ├── Controllers/          AuthController, HomeController, Admin/, DataEntry/, Oic/
│   ├── Models/               User, Dashboard (+ more per phase)
│   └── Views/                layouts/, partials/, auth/, errors/, admin/, dataentry/, oic/
├── config/                   config.php, database.php
├── database/praman_db.sql    schema + FK + indexes + CHECKs + seed
├── public/
│   ├── .htaccess             front controller + security headers
│   ├── index.php             front controller (PSR-4 autoloader)
│   └── assets/
│       ├── .htaccess         no script execution
│       ├── css/              style.css, responsive.css, animations.css, fontawesome.min.css
│       ├── js/               app.js, validation.js, ajax.js
│       ├── fonts/            Inter 400/500/600/700 (woff2)
│       ├── webfonts/         Font Awesome Free 6.5.2 (woff2 + ttf)
│       └── img/              kda-logo.svg, favicon.svg
└── storage/                  logs/ (app + php errors), sessions/ (PHP session files)
```

## 5. Local assets (already included — no CDN)
The fonts and icons are already in the project. If you need to re-download them:

| File | Source | Place in |
|---|---|---|
| `fontawesome.min.css` | https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css (rename) | `public/assets/css/` |
| `fa-solid-900`, `fa-regular-400`, `fa-brands-400`, `fa-v4compatibility` (`.woff2` + `.ttf`) | `https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/webfonts/<name>.woff2` (and `.ttf`) | `public/assets/webfonts/` |
| `inter-latin-400-normal.woff2` … `-700-` | `https://cdn.jsdelivr.net/npm/@fontsource/inter@5.0.18/files/inter-latin-<weight>-normal.woff2` (400, 500, 600, 700) | `public/assets/fonts/` |

These links are only used to download the files once. At runtime the app loads nothing from outside the server. The CSP header is `'self'` only.

## 6. Security summary
| Area | Implementation |
|---|---|
| Routing / exposure | Root `.htaccess` sends everything to `public/`. `app/ config/ database/ storage/`, dot-files and `.sql .log .env .ini .md …` → 403. `Options -Indexes`. Only GET/POST allowed. HTTPS redirect is ready but commented. |
| Assets | `public/assets/.htaccess`: PHP engine off, script extensions denied, `-ExecCGI` |
| Headers | CSP (`'self'` only, no inline script or style), X-Frame-Options DENY, nosniff, Referrer-Policy, Permissions-Policy, COOP/CORP, `no-store` on pages. HSTS is ready but commented. |
| SQL | PDO, real prepared statements (emulation off), whitelisted ORDER BY, LIKE-escaping |
| XSS | Every value printed through `e()`. JSON uses `JSON_HEX_TAG` |
| CSRF | Per-session token on every POST (form field or `X-CSRF-Token` header), rotated on login and logout |
| Passwords | Argon2id (`password_hash`). The bcrypt seed hashes are upgraded on first login. Policy enforced on the server. Forced change on first login |
| Sessions | strict mode, HttpOnly, SameSite=Strict, Secure on HTTPS, ID regenerated on login and password change, 30-min idle and 8-h absolute timeout, bound to the user agent, files saved under `storage/sessions` |
| Brute force | 5 failures per user → 15-min lock, 20 failures per IP in 15 min → blocked, generic error message, constant-time check for unknown users |
| RBAC | `panel:` middleware on every route group. Denials logged and audited |
| Errors | `display_errors=0`. Everything is logged to `storage/logs/`. Users only see a friendly page |
| Audit | `audit_logs` (who, what, when, IP, user agent; old and new values as JSON, secrets removed). Production SQL grants the app user **INSERT only** on this table (see the footer of `praman_db.sql`) |

**Production hardening (server config, cannot be set in .htaccess):** in `httpd.conf` set `ServerTokens Prod` and `ServerSignature Off`. In `php.ini` set `expose_php = Off`, `session.cookie_secure = 1`. Then set `force_https => true` in `config/config.php`, and uncomment the HTTPS and HSTS lines in both `.htaccess` files.

## 7. UI conventions (for the next phases)
- Lists: `Paginator::fromRequest()` → `Model::paginate()` → `partials/list_toolbar` + `th_sort()` + `partials/pagination` + `partials/empty_state`. That gives server-side pagination, 10/25/50 rows, search, sort and an empty state on every table.
- Forms: `data-validate` + `data-rules="required|max:50"` (client) — the server always re-validates with `Validator`.
- Confirm dialogs: `data-confirm="message"` on a form or button. Loading state is automatic. Toasts: `Praman.toast(type, msg)`.
- Mobile: add `class="table-stack"` + `data-label` on each `<td>` and tables turn into cards below 576px.

## 8. Phase 1 checklist
- [x] No CDN anywhere — fonts, icons, CSS and JS are all under `public/assets`. CSP `'self'` blocks external loads
- [x] Light theme only (`color-scheme: light only`). No dark-mode CSS
- [x] Responsive breakpoints: 320–575 (stacked tables, single column, off-canvas sidebar), 576–991 (off-canvas sidebar, 2-column stats), 992–1399, 1400+. Target sizes to check: 360 / 768 / 1024 / 1440
- [x] Pagination framework (server-side, page-size, search, sort, empty state) is ready. Phase 2–4 tables use it
- [x] Tested over HTTP: blocked folders (403), method filter, CSRF reject, login, lockout, forced password change with Argon2id upgrade, RBAC 403, 404, logout, audit rows
