# PRAMAAN
**Property Records & Auction-readiness Multi-section Assessment Network** — Kota Development Authority

Pure PHP 8.2 MVC (no framework) · MySQL / MariaDB (InnoDB, utf8mb4) · vanilla JS · every asset self-hosted (no CDN).
The screens, design and behaviour are the same as `html/index.html`; all data now lives in the database and every
change is checked and saved by the server.

## 1. Install (XAMPP)
1. Start **Apache** and **MySQL** in the XAMPP Control Panel. Server settings (database, debug, HTTPS, demo reset) live in **`.env`** — copy `.env.example` to `.env` on a new server and fill it in (`.env` is never served over HTTP and is git-ignored).
2. Create the database with sample data (drops the old `praman_db` / `pramaan_db` first):
   ```
   C:\xampp\php\php.exe database\install.php
   ```
3. Open **http://localhost/pramaan_kda/pramaan_develop/**

Requirements: PHP 8.2+ with `pdo_mysql`, `mbstring`, `fileinfo`, `gd` (sample images); Apache `mod_rewrite` (required),
`mod_headers` (recommended); `AllowOverride All` (XAMPP default). DB settings: `DB_*` in `.env`.

Upgrade an existing database without losing data: `C:
mpp\php\php.exe database\migrate.php`.

## 2. Logins (password `Praman@123` for every sample user)
| Panel | Username |
|---|---|
| Super Admin | `superadmin` |
| Data Entry | `dataentry` |
| OIC Auction | `oic` |
| Role users | `auction1/2`, `lottery1/2`, `sale1/2`, `lfl1/2`, `punarvas1/2`, `eng1…4`, `tp1/2`, `legal1…3` |

5 wrong passwords lock an account for 15 minutes (Super Admin → User Master → unlock).
To reload the sample data (deletes everything), run `C:
mpp\php\php.exe database\install.php` on the server — there is no reset button in the app.

## App Settings (everything dynamic)
Super Admin → **System → App Settings** edits, without touching code: application name (PRAMAAN) and header tagline
(Property Verification System), organisation, website, landing page texts, landing slides (add / caption / order / show / delete),
sign-in demo accounts, password length, lock-out limits, idle logout, Auction Ready validity, re-verification alert and refresh interval.
Values live in the `settings` and `slides` tables; code lists (panels, scheme types, area units, allotment modes, badge colours / icons)
come from `app/Core/Lookups.php` so the screens and the server validation always use the same list.
Every form is validated twice: in the browser (same rule names) and again on the server before anything is saved.

## Permissions (what each panel may do)
The four panels stay fixed; what each one may see and do is dynamic. Super Admin → **System → Permissions** shows a
checkbox matrix (permission × panel). Every action is checked on the server (`can:` middleware on the route, `authorize()`
in the controller for create vs edit), and the menu, screens and buttons follow the same permissions in the browser.
The permission codes are listed once in `app/Core/Permissions.php`; the grants live in the `panel_permissions` table
(the installer / `database\migrate.php` give every panel all of its permissions the first time). Super Admin always keeps
the Permissions screen itself, so nobody can be locked out. To add a new action: add its code to `Permissions::MODULES`,
put `can:<code>` on its route and `P.Auth.can('<code>')` around its button; `database\migrate.php` gives a new code to its panels once
(the codes already seen are kept in the `permission_codes_seen` setting), after that Super Admin decides.

**One user's exceptions** — User Master → <i>shield</i> button (or Edit user → Permissions): for each permission of the user's panel choose
**Panel** (inherit), **Allow** (extra for this user only) or **Deny** (taken away from this user only). What the user can finally do =
panel permissions + Allow − Deny, shown live on the screen. Stored in `user_permissions`; removed when the user's panel changes.
User Master can filter "With personal permissions"; every change is in the audit log.

## 3. Structure (MVC)
```
pramaan_develop/
├── .htaccess              the ONLY .htaccess: routes, security headers, caching, compression, limits
├── app/
│   ├── Core/              App, Router, Request, Response, Session, Csrf, Auth, Database, Model,
│   │                      Controller, Validator, Password, View, Config, Clock, Logger, helpers
│   ├── Middleware/        Auth, Guest, Csrf, Can (permission-based access: "can:user.create")
│   ├── Controllers/       Auth, Home (page + /api/state), Master (every master incl. Scheme — one action), Notification, Attachment, System,
│   │                      Role, User, Insight, Settings, Property, Verification, Task, Permission (flat, like Models/)
│   ├── Models/            one class per table (column whitelist, prepared statements)
│   ├── Codes/             every fixed code the logic checks, written once: Panel, PropertyStatus, TaskStatus,
│   │                      RoundStatus, PermissionEffect, YesNo, SiteCode, Section, AttachmentContext
│   │                      (code compares with Panel::SUPER_ADMIN …, never with a 'SUPER_ADMIN' string)
│   ├── Services/          Verification engine, PropertyService, StateService, Audit, Notify, Uploads,
│   │                      Housekeeping (daily validity expiry), Seeder
│   ├── Views/             layouts/app.php (application page), errors/error.php
│   └── routes.php         every POST: CSRF; every action: permission check ("can:…");
│                          handlers by name — 'Role@save' = Controllers/RoleController::save(), no imports
├── config/                config.php, database.php
├── database/              schema.sql, install.php
├── public/                index.php (front controller) + assets/{css,js,img,fonts,webfonts}
└── storage/               logs, sessions, cache, uploads (attachments) — never served
```
How a screen works: `GET /` returns the page with the signed-in user's data as JSON; the JS in `public/assets/js`
draws the screens (same code as the HTML prototype). Forms post to `POST /api/<action>` (e.g. `/api/property-save`);
the controller validates, applies the business rules inside a DB transaction, writes the audit trail and
notifications, and answers with fresh data. `GET /api/state` is polled every minute for new notifications.

## 4. Database `pramaan_db` (23 tables)
| Area | Tables |
|---|---|
| Access | `roles`, `users`, `login_attempts`, `panel_permissions` (what each panel may do), `user_permissions` (one user's Allow / Deny exceptions) |
| Masters | `zones`, `wards`, `assemblies`, `plot_types`, `statuses` |
| Schemes | `schemes`, `sectors`, `scheme_users` (role users mapped to a scheme) |
| Properties | `properties`, `property_users` (snapshot of the scheme's role users) |
| Verification | `rounds`, `tasks` (one per role), `task_assignees`, `site_inspections` (Engineering), `sale_checks` (Sale), `attachments` |
| Tracking | `notifications`, `audit_logs` (insert-only) |
| Settings | `settings` (key / value), `slides` (landing page photos) |

Foreign keys, unique keys (codes, names, plot no. per sector, one task per role per round), CHECK constraints and indexes are in `database/schema.sql`.

## 5. Security
| Area | Implementation |
|---|---|
| Exposure | One root `.htaccess` (the only one, sections 1–18) routes to `/public`, denies `app/ config/ database/ storage/`, dot-files and `.sql .log .env .ini .md …`; only GET/POST; `Options -Indexes`; obvious attack query strings blocked. `public/assets` cannot run scripts. |
| Headers | CSP `'self'` only (no inline script or style), X-Frame-Options DENY, nosniff, Referrer-Policy, Permissions-Policy, COOP/CORP, `no-store` on pages and API. HSTS + HTTPS redirect ready (uncomment). |
| Sessions | Strict mode, HttpOnly, SameSite=Strict, Secure on HTTPS, new ID on login / logout / password change / "login as", 30-min idle + 8-h absolute timeout, bound to the browser. |
| CSRF | Per-session token in the `X-CSRF-Token` header on every POST + Origin / Sec-Fetch-Site check. |
| Passwords | Argon2id, policy enforced on the server (10+ chars, upper, lower, digit, special, not the username, not the current one); never sent to the browser. |
| Brute force | 5 failures → account lock, 20 failures per IP in 15 min → blocked, generic error message, same timing for unknown users, Super Admin alerted. |
| Access control | `can:` permission middleware on every action (permissions per panel, Super Admin → Permissions); denials are audited; each panel only receives the rows it needs (role users: only properties assigned to them; no password hashes, no other users' contact details). |
| SQL | PDO real prepared statements, column whitelists, no user input in identifiers. |
| XSS | All output escaped (`e()` in PHP views, `P.e()` in JS); `<` `>` rejected in text fields; JSON embedded with `JSON_HEX_*`. |
| Uploads | PDF/JPG/PNG only, checked by extension + MIME + magic bytes, 5 MB / 10 files, random file names outside the web root, served only to users who may see the property. |
| Audit | `audit_logs`: who, what, when, IP, user agent, old → new values (secrets masked); "login as" recorded with the admin's name. |
| Errors | `display_errors` off; details in `storage/logs`, users see a friendly message. |

Production: set `app.force_https = true`, uncomment the HTTPS / HSTS lines in
`.htaccess` (sections 9 and 3), use a least-privilege DB user (see the end of `schema.sql`), and in `php.ini` set
`expose_php = Off`, `session.cookie_secure = 1`; in `httpd.conf` set `ServerTokens Prod` and `TraceEnable Off`
(the full list is at the end of `.htaccess`).

## 6. Local assets (no CDN)
Everything the browser loads comes from this server; the CSP allows only `'self'`.
- **Fonts** `public/assets/fonts`: Inter (variable 400-800, body), Poppins 400/500/600/700 (headings, landing), Noto Sans Devanagari (Hindi). `css/fonts.css` uses `font-display: swap`; the main files are preloaded.
- **Icons** Font Awesome Free 6.5.2: `css/fontawesome.min.css` + `public/assets/webfonts` (solid, regular, brands).
- **Charts** Chart.js 4.4.1: `js/vendor/chart.umd.min.js`.

## 7. Responsive
`public/assets/css/responsive.css` covers 4K / ultra-wide (1920+), desktop (1400+), laptop (1200–1399), small laptop / iPad Pro
landscape (992–1199), iPad / tablet (768–991, off-canvas menu), iPad portrait / large phones (576–767), phones (360–575),
small phones (320–359), phone landscape (short screens), touch devices (16 px inputs so iOS does not zoom, larger tap targets),
notch safe-areas and print. Tables scroll sideways on small screens with a swipe hint.
