/* ===== js/pages/dataentry.js ===== */
/* PRAMAAN — Data Entry panel (app/Views/dataentry/*) */
(function (window) {
    'use strict';
    const P = window.Praman;
    const e = P.e, icon = P.icon, DB = P.DB, Auth = P.Auth, L = P.Lookups;
    const PANEL = 'DATA_ENTRY';

    /* ================= Scheme Master ================= */
    function schemeRows() {
        const eligibleRoles = new Set(
            DB.where('roles', function (r) {
                return Number(r.is_active) === 1 && r.status_id != null && Number(r.status_id) > 0;
            }).map(function (r) { return Number(r.role_id); })
        );

        return DB.all('schemes').map(function (s) {
            const secs = DB.Scheme.sectors(s.scheme_id).filter(function (x) { return x.is_active; });
            const mapping = DB.Scheme.mapping(s.scheme_id);
            let mappedUserCount = 0;
            let mappedRoleCount = 0;
            Object.keys(mapping).forEach(function (roleId) {
                const rid = Number(roleId);
                if (eligibleRoles.has(rid) && mapping[roleId] && mapping[roleId].length > 0) {
                    mappedUserCount += mapping[roleId].length;
                    mappedRoleCount += 1;
                }
            });

            return Object.assign({}, s, {
                area: DB.areaName(s.area_id),
                sector_count: secs.length, sector_names: secs.map(function (x) { return x.sector_name; }).join(', '),
                property_count: DB.count('properties', { scheme_id: s.scheme_id, is_active: 1 }),
                mapped_users: mappedUserCount,
                mapped_roles: mappedRoleCount
            });
        });
    }

    /** Open the modal popup to assign role users for a scheme (Fullscreen & Tab-based by Role) */
    function openSchemeUsersModal(schemeId) {
        const scheme = DB.find('schemes', schemeId);
        if (!scheme) { return; }
        // Only active roles that have an assigned status
        const options = DB.Scheme.roleUserOptions();
        const mapping = DB.Scheme.mapping(schemeId);

        const old = document.getElementById('scheme-users-modal');
        if (old) { old.remove(); }

        const backdrop = document.createElement('div');
        backdrop.id = 'scheme-users-modal';
        backdrop.className = 'modal-backdrop modal-fullscreen-backdrop is-open';
        backdrop.setAttribute('role', 'dialog');
        backdrop.setAttribute('aria-modal', 'true');
        backdrop.setAttribute('aria-labelledby', 'scheme-users-title');

        if (!options.length) {
            backdrop.innerHTML = '<div class="modal modal-lg" style="max-width: 600px; margin: auto;">' +
                '<div class="modal-header">' +
                '<span class="modal-icon text-primary">' + icon('fa-users-gear') + '</span>' +
                '<div><h2 class="modal-title" id="scheme-users-title">Assign Role Users</h2>' +
                '<div class="text-muted small">' + e(scheme.scheme_name) + '</div></div>' +
                '<button type="button" class="btn btn-light btn-sm btn-icon-only ml-auto" data-modal-close aria-label="Close">' + icon('fa-xmark') + '</button></div>' +
                '<div class="modal-body" style="padding: 24px 20px;">' +
                '<div class="alert alert-warning m-0">' + icon('fa-triangle-exclamation') +
                '<div><strong>No status-assigned roles found</strong><br><small class="text-muted">Users can only be assigned to roles that have an assigned status. Please assign statuses to roles in the <a href="' + P.BASE + '/admin/roles">Roles Master</a> first.</small></div></div>' +
                '</div>' +
                '<div class="modal-footer">' +
                '<button type="button" class="btn btn-light" data-modal-close>' + icon('fa-xmark') + ' <span>Close</span></button>' +
                '</div></div>';
            document.body.appendChild(backdrop);
            document.body.classList.add('modal-open');
            const closeBtn = backdrop.querySelector('[data-modal-close]');
            if (closeBtn) { closeBtn.addEventListener('click', function () { backdrop.remove(); document.body.classList.remove('modal-open'); }); }
            return;
        }

        // Generate Tabs HTML (Proper tabs bar at top with real-time user names)
        const tabsHtml = options.map(function (opt, idx) {
            const r = opt.role;
            const picked = mapping[r.role_id] || [];
            const pickedUsers = opt.users.filter(function (u) { return picked.indexOf(Number(u.user_id)) !== -1; });
            const namesText = pickedUsers.map(function (u) { return u.name; }).join(', ');
            return '<button type="button" class="scheme-tab-item' + (idx === 0 ? ' active' : '') + '" data-role-tab-btn="' + r.role_id + '" role="tab" aria-selected="' + (idx === 0 ? 'true' : 'false') + '" title="' + e(r.role_name + ': ' + (namesText || 'No users assigned')) + '">' +
                '<span class="scheme-tab-icon">' + icon('fa-user-tie') + '</span>' +
                '<span class="scheme-tab-name">' + e(r.role_name) + '</span>' +
                (opt.status_name ? '<span class="badge badge-success small" style="font-size: 10.5px; padding: 2px 6px;">' + e(opt.status_name) + '</span>' : '') +
                '<span class="scheme-tab-badge badge ' + (pickedUsers.length ? 'badge-primary' : 'badge-muted') + '" data-tab-badge="' + r.role_id + '">' + pickedUsers.length + '</span>' +
                '<span class="scheme-tab-preview" data-tab-names="' + r.role_id + '">' +
                ': <span class="scheme-tab-preview-val">' + (namesText ? e(namesText) : '<em class="text-muted">None</em>') + '</span>' +
                '</span>' +
                '</button>';
        }).join('');

        // Generate Panes HTML (Fullscreen workspace per role with card grid)
        const panesHtml = options.map(function (opt, idx) {
            const r = opt.role;
            const picked = mapping[r.role_id] || [];
            const pickedUsers = opt.users.filter(function (u) { return picked.indexOf(Number(u.user_id)) !== -1; });

            return '<div class="scheme-tab-pane' + (idx === 0 ? ' active' : '') + '" data-role-pane="' + r.role_id + '">' +
                '<div class="scheme-pane-toolbar">' +
                '<div class="scheme-pane-info">' +
                '<h3>' +
                icon('fa-user-tie text-primary') + ' ' + e(r.role_name) +
                (opt.status_name ? ' <span class="badge badge-success">' + icon('fa-tag') + ' Status: ' + e(opt.status_name) + '</span>' : '') +
                (r.is_mandatory ? ' <span class="badge badge-info">Mandatory Role</span>' : '') +
                '</h3>' +
                '<span class="text-muted small d-block mt-1" data-pane-counter="' + r.role_id + '">' +
                pickedUsers.length + ' of ' + opt.users.length + ' users selected' +
                '</span>' +
                '</div>' +
                '<div class="scheme-pane-actions">' +
                (opt.users.length > 2 ? '<div class="scheme-pane-search">' +
                    '<input type="text" class="form-control" placeholder="Search by name, mobile or email…" data-user-search="' + r.role_id + '">' +
                    icon('fa-magnifying-glass') +
                    '</div>' : '') +
                '<label class="select-all-chip"' + (opt.users.length ? '' : ' style="display:none;"') + '>' +
                '<input type="checkbox" data-role-all="' + r.role_id + '">' +
                '<span>Select all in ' + e(r.role_name) + '</span>' +
                '</label>' +
                '</div>' +
                '</div>' +
                '<div class="scheme-pane-content" data-user-list-wrap="' + r.role_id + '">' +
                (opt.users.length ? '<div class="scheme-user-grid">' + opt.users.map(function (u) {
                    const isChecked = picked.indexOf(Number(u.user_id)) !== -1;
                    const initial = (u.name || '').trim().charAt(0).toUpperCase() || 'U';
                    return '<label class="scheme-user-card' + (isChecked ? ' is-selected' : '') + '" data-user-card data-user-search-text="' + e((u.name + ' ' + (u.mobile || '') + ' ' + (u.email || '')).toLowerCase()) + '">' +
                        '<input type="checkbox" name="role_users_' + r.role_id + '[]" value="' + u.user_id + '" data-user-id="' + u.user_id + '" data-role-id="' + r.role_id + '" data-user-name="' + e(u.name) + '"' + (isChecked ? ' checked' : '') + '>' +
                        '<div class="scheme-user-card-avatar">' + e(initial) + '</div>' +
                        '<div class="scheme-user-card-body">' +
                        '<div class="scheme-user-card-name">' + e(u.name) + '</div>' +
                        '<div class="scheme-user-card-meta">' +
                        (u.mobile ? '<span>' + icon('fa-phone') + ' ' + e(u.mobile) + '</span>' : '') +
                        (u.email ? '<span>' + icon('fa-envelope') + ' ' + e(u.email) + '</span>' : '') +
                        '</div></div></label>';
                }).join('') + '</div>' : '<div class="empty-state py-5 text-center">' + icon('fa-users-slash text-muted fa-3x') + '<h4 class="mt-3 text-muted">No active users in this role</h4><p class="text-muted small">You can add active users for "' + e(r.role_name) + '" in the Users master.</p></div>') +
                '<div class="scheme-user-search-empty text-center py-5 text-muted" style="display:none;">' +
                icon('fa-magnifying-glass fa-2x') + '<p class="mt-2">No users match your search query.</p>' +
                '</div>' +
                '</div></div>';
        }).join('');

        backdrop.innerHTML = '<div class="modal modal-fullscreen">' +
            '<form method="post" id="scheme-users-form" class="modal-fullscreen-form" novalidate>' +
            '<input type="hidden" name="scheme_id" value="' + scheme.scheme_id + '">' +
            '<div class="modal-fullscreen-header">' +
            '<div style="display: flex; align-items: center; gap: 14px;">' +
            '<span class="modal-icon text-primary" style="background: var(--navy-100); color: var(--navy-700);">' + icon('fa-users-gear') + '</span>' +
            '<div><h2 class="modal-title" id="scheme-users-title" style="font-size: 19px; font-weight: 700; margin: 0; color: var(--navy-900);">Assign Role Users</h2>' +
            '<div class="text-muted small" style="margin-top: 3px;"><strong>' + e(scheme.scheme_name) + '</strong> &middot; <span class="badge badge-info" style="font-size: 11px;">Status-Assigned Roles Only</span></div></div></div>' +
            '<button type="button" class="btn btn-light btn-icon-only" data-modal-close aria-label="Close" title="Close (Esc)" style="border-radius: 50%; width: 38px; height: 38px;">' + icon('fa-xmark') + '</button></div>' +
            '<div class="scheme-tabs-nav" role="tablist">' + tabsHtml + '</div>' +
            '<div class="modal-fullscreen-body">' + panesHtml + '</div>' +
            '<div class="modal-fullscreen-footer">' +
            '<div class="text-muted" data-global-selection-summary style="font-size: 14px;">' + icon('fa-users') + ' Total: <strong>0</strong> users selected across <strong>0</strong> roles</div>' +
            '<div style="display: flex; gap: 10px;">' +
            '<button type="button" class="btn btn-light btn-lg" data-modal-close>' + icon('fa-xmark') + ' <span>Cancel</span></button>' +
            '<button type="submit" class="btn btn-primary btn-lg" data-submit-btn>' + icon('fa-floppy-disk') + ' <span>Save Role Users</span></button>' +
            '</div></div></form></div>';

        document.body.appendChild(backdrop);
        document.body.classList.add('modal-open');

        const form = backdrop.querySelector('#scheme-users-form');
        const submitBtn = backdrop.querySelector('[data-submit-btn]');
        const tabBtns = Array.prototype.slice.call(backdrop.querySelectorAll('[data-role-tab-btn]'));
        const panes = Array.prototype.slice.call(backdrop.querySelectorAll('[data-role-pane]'));

        // Function to update a role's tab preview and counter
        function syncRole(roleId) {
            const pane = backdrop.querySelector('[data-role-pane="' + roleId + '"]');
            const tabBtn = backdrop.querySelector('[data-role-tab-btn="' + roleId + '"]');
            if (!pane || !tabBtn) { return; }

            const allBoxes = Array.prototype.slice.call(pane.querySelectorAll('input[type="checkbox"][data-user-id]'));
            const checkedBoxes = allBoxes.filter(function (b) { return b.checked; });
            const checkedNames = checkedBoxes.map(function (b) { return b.getAttribute('data-user-name'); });

            // Tab badge
            const badge = tabBtn.querySelector('[data-tab-badge="' + roleId + '"]');
            if (badge) {
                badge.textContent = checkedBoxes.length;
                badge.className = 'scheme-tab-badge badge ' + (checkedBoxes.length ? 'badge-primary' : 'badge-muted');
            }

            // Tab names text in front of role
            const namesEl = tabBtn.querySelector('[data-tab-names="' + roleId + '"]');
            if (namesEl) {
                namesEl.innerHTML = ': <span class="scheme-tab-preview-val">' +
                    (checkedNames.length ? e(checkedNames.join(', ')) : '<em class="text-muted">None</em>') + '</span>';
            }
            const roleTitle = tabBtn.querySelector('.scheme-tab-name').textContent;
            tabBtn.setAttribute('title', roleTitle + ': ' + (checkedNames.length ? checkedNames.join(', ') : 'No users assigned'));

            // Pane counter
            const counter = pane.querySelector('[data-pane-counter="' + roleId + '"]');
            if (counter) {
                counter.textContent = checkedBoxes.length + ' of ' + allBoxes.length + ' users selected';
            }

            // Pane select all
            const allBtn = pane.querySelector('[data-role-all="' + roleId + '"]');
            if (allBtn) {
                allBtn.checked = allBoxes.length > 0 && checkedBoxes.length === allBoxes.length;
                allBtn.indeterminate = checkedBoxes.length > 0 && checkedBoxes.length < allBoxes.length;
            }
        }

        // Function to update global summary
        function syncGlobal() {
            const allChecked = Array.prototype.slice.call(form.querySelectorAll('input[type="checkbox"][data-user-id]:checked'));
            const summary = backdrop.querySelector('[data-global-selection-summary]');
            if (summary) {
                const rolesWithUsers = options.filter(function (opt) {
                    return form.querySelector('input[type="checkbox"][data-role-id="' + opt.role.role_id + '"]:checked');
                }).length;
                summary.innerHTML = icon('fa-users') + ' Total: <strong>' + allChecked.length + '</strong> user' + (allChecked.length === 1 ? '' : 's') + ' selected across <strong>' + rolesWithUsers + '</strong> role' + (rolesWithUsers === 1 ? '' : 's');
            }
        }

        // Initial sync for all roles
        options.forEach(function (opt) {
            syncRole(opt.role.role_id);
        });
        syncGlobal();

        // Proper Tab click switching (only active tab pane displays)
        tabBtns.forEach(function (btn) {
            btn.addEventListener('click', function (ev) {
                ev.preventDefault();
                const targetRoleId = btn.getAttribute('data-role-tab-btn');
                tabBtns.forEach(function (b) {
                    const isTarget = b.getAttribute('data-role-tab-btn') === targetRoleId;
                    b.classList.toggle('active', isTarget);
                    b.setAttribute('aria-selected', isTarget ? 'true' : 'false');
                });
                panes.forEach(function (p) {
                    const isTarget = p.getAttribute('data-role-pane') === targetRoleId;
                    p.classList.toggle('active', isTarget);
                });
            });
        });

        // User checkbox change listener (event delegation on form)
        form.addEventListener('change', function (ev) {
            const target = ev.target;
            if (!target || target.type !== 'checkbox') { return; }

            // Individual user checkbox toggled
            if (target.hasAttribute('data-user-id')) {
                const roleId = target.getAttribute('data-role-id');
                const card = target.closest('.scheme-user-card');
                if (card) { card.classList.toggle('is-selected', target.checked); }
                syncRole(roleId);
                syncGlobal();
                return;
            }

            // Role "Select all" toggled
            if (target.hasAttribute('data-role-all')) {
                const roleId = target.getAttribute('data-role-all');
                const pane = backdrop.querySelector('[data-role-pane="' + roleId + '"]');
                if (!pane) { return; }
                const isChecked = !!target.checked;
                const visibleCheckboxes = Array.prototype.slice.call(pane.querySelectorAll('.scheme-user-card:not([style*="display: none"]) input[type="checkbox"][data-user-id]'));
                visibleCheckboxes.forEach(function (cb) {
                    cb.checked = isChecked;
                    const card = cb.closest('.scheme-user-card');
                    if (card) { card.classList.toggle('is-selected', isChecked); }
                });
                syncRole(roleId);
                syncGlobal();
            }
        });

        // Search inputs per pane (filters cards in real-time)
        backdrop.querySelectorAll('[data-user-search]').forEach(function (input) {
            input.addEventListener('input', function () {
                const q = input.value.trim().toLowerCase();
                const roleId = input.getAttribute('data-user-search');
                const pane = backdrop.querySelector('[data-role-pane="' + roleId + '"]');
                if (!pane) { return; }
                const cards = Array.prototype.slice.call(pane.querySelectorAll('[data-user-card]'));
                let matchesCount = 0;
                cards.forEach(function (card) {
                    const text = card.getAttribute('data-user-search-text') || '';
                    const match = !q || text.indexOf(q) !== -1;
                    card.style.display = match ? '' : 'none';
                    if (match) { matchesCount++; }
                });
                const emptyMsg = pane.querySelector('.scheme-user-search-empty');
                if (emptyMsg) {
                    emptyMsg.style.display = (matchesCount === 0 && cards.length > 0) ? 'block' : 'none';
                }
            });
        });

        // Close modal handlers
        function closeModal() {
            document.body.classList.remove('modal-open');
            window.removeEventListener('keydown', onKeyDown);
            backdrop.remove();
        }

        function onKeyDown(ev) {
            if (ev.key === 'Escape') {
                ev.preventDefault();
                closeModal();
            }
        }
        window.addEventListener('keydown', onKeyDown);

        backdrop.addEventListener('click', function (ev) {
            if (ev.target === backdrop || ev.target.closest('[data-modal-close]')) {
                ev.preventDefault();
                closeModal();
            }
        });

        // Form submit
        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            const origHtml = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = icon('fa-spinner fa-spin') + ' <span>Saving…</span>';

            const fd = new FormData(form);
            Praman.api('scheme-users', fd).then(function (res) {
                if (!res.ok) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = origHtml;
                    alert(res.message || 'Failed to save role users.');
                    return;
                }
                closeModal();
                if (res.message) {
                    Praman.flash('success', res.message);
                }
                Praman.render();
            }).catch(function () {
                submitBtn.disabled = false;
                submitBtn.innerHTML = origHtml;
                alert('An unexpected error occurred. Please try again.');
            });
        });
    }

    // Global listener for opening the Assign Role Users popup from schemes list
    document.addEventListener('click', function (ev) {
        const btn = ev.target.closest('[data-scheme-users]');
        if (btn) {
            ev.preventDefault();
            openSchemeUsersModal(btn.getAttribute('data-scheme-users'));
        }
    });

    function schemeListPage(ctx, base, crumbs) {
        const q = ctx.query;
        let rows = schemeRows();
        if (q.type) { rows = rows.filter(function (s) { return s.scheme_type === q.type; }); }
        if (q.status === 'active') { rows = rows.filter(function (s) { return s.is_active; }); }
        if (q.status === 'inactive') { rows = rows.filter(function (s) { return !s.is_active; }); }
        const p = P.paginate(rows, ctx, {
            sort: 'name', dir: 'asc', filters: ['type', 'status'],
            search: function (s) { return [s.scheme_name, s.sector_names]; },
            sortable: {
                name: function (s) { return s.scheme_name; },
                type: function (s) { return s.scheme_type; },
                sectors: function (s) { return s.sector_count; },
                properties: function (s) { return s.property_count; }
            }
        });
        const filters = '<select name="type" class="form-select" aria-label="Scheme type" data-auto-submit>' + L.options(L.SCHEME_TYPES, q.type, 'All types') + '</select>' +
            '<select name="status" class="form-select" aria-label="Status" data-auto-submit>' + L.options({ active: 'Active', inactive: 'Inactive' }, q.status, 'All status') + '</select>';
        return {
            title: 'Schemes',
            html: (crumbs ? crumbs('Schemes') : '') + P.pageHeader(icon('fa-map') + ' Schemes', 'Manage schemes and their sectors for properties.',
                Auth.can('scheme.create') ? '<a href="' + P.BASE + base + '/create" class="btn btn-primary">' + icon('fa-plus') + ' <span>Create Scheme</span></a>' : '') +
                '<div class="card">' + P.list_toolbar(p, 'Search scheme or sector name…', filters) +
                '<div class="table-wrap"><table class="table table-stack table-top"><thead><tr>' +
                P.th_sort(p, 'name', 'Scheme') + P.th_sort(p, 'type', 'Type') +
                P.th_sort(p, 'sectors', 'Sectors') + P.th_sort(p, 'properties', 'Properties') +
                '<th>Status</th><th class="text-right">Actions</th></tr></thead><tbody>' +
                (p.items.length ? p.items.map(function (s) {
                    const on = s.is_active === 1;
                    const propBase = base.indexOf('/admin/') === 0 ? '/admin/masters/properties' : '/data-entry/properties';
                    return '<tr><td data-label="Scheme"><a href="' + P.BASE + base + '/' + s.scheme_id + '"><strong>' + e(s.scheme_name) + '</strong></a>' +
                        (s.mapped_users > 0
                            ? '<span class="cell-sub text-muted" title="' + s.mapped_users + ' role user(s) assigned across ' + s.mapped_roles + ' role(s)">' + icon('fa-users-gear') + ' ' + s.mapped_users + ' role user' + (s.mapped_users === 1 ? '' : 's') + '</span>'
                            : '<span class="cell-sub text-muted">' + icon('fa-user-slash') + ' No role users</span>') + '</td>' +
                        '<td data-label="Type">' + e(L.SCHEME_TYPES[s.scheme_type] || s.scheme_type) + '</td>' +
                        '<td data-label="Sectors"><span class="badge badge-info">' + s.sector_count + '</span>' +
                        '<span class="cell-sub truncate" title="' + e(s.sector_names) + '">' + e(s.sector_names || '—') + '</span></td>' +
                        '<td data-label="Properties">' + (s.property_count > 0 ? '<a href="' + P.BASE + propBase + '?scheme_id=' + s.scheme_id + '" class="badge badge-info" title="View properties in this scheme">' + s.property_count + '</a>' : '0') + '</td><td data-label="Status">' + P.active_badge(s.is_active) + '</td>' +
                        '<td class="actions"><div class="btn-group">' +
                        '<a href="' + P.BASE + base + '/' + s.scheme_id + '" class="btn btn-light btn-sm btn-icon-only" title="View scheme details" aria-label="View scheme details">' + icon('fa-eye') + '</a>' +
                        (Auth.can('scheme.edit') ? '<button type="button" class="btn btn-light btn-sm btn-icon-only text-primary" data-scheme-users="' + s.scheme_id + '" title="Assign role users" aria-label="Assign role users">' + icon('fa-users-gear') + '</button>' : '') +
                        (Auth.can('scheme.edit') ? '<a href="' + P.BASE + base + '/' + s.scheme_id + '/edit" class="btn btn-light btn-sm btn-icon-only" title="Edit scheme" aria-label="Edit scheme">' + icon('fa-pen') + '</a>' : '') +
                        (Auth.can('scheme.toggle') ? '<form method="post" data-action="master" data-confirm="' + e(on ? 'Deactivate scheme "' + s.scheme_name + '"? No new property can be added in it.' : 'Activate scheme "' + s.scheme_name + '"?') + '" data-confirm-variant="' + (on ? 'danger' : 'success') + '">' +
                            '<input type="hidden" name="key" value="schemes"><input type="hidden" name="do" value="toggle"><input type="hidden" name="id" value="' + s.scheme_id + '">' +
                            (on ? '<button type="submit" class="btn btn-sm btn-light text-danger btn-icon-only" title="Deactivate" aria-label="Deactivate">' + icon('fa-toggle-off') + '</button>'
                                : '<button type="submit" class="btn btn-sm btn-success btn-icon-only" title="Activate" aria-label="Activate">' + icon('fa-toggle-on') + '</button>') + '</form>' : '') + '</div></td></tr>';
                }).join('') : P.empty_state(7, p.hasFilters(), 'No schemes yet — create the first one')) +
                '</tbody></table></div>' + P.pagination(p) + '</div>'
        };
    }

    /** Tag input for new sectors: each name becomes a chip (hidden sectors_new[]); typing searches names used in other schemes (js/forms.js). */
    function sectorTagsHtml(schemeId) {
        return '<div class="tag-input" data-sector-tags data-scheme-id="' + (schemeId || '') + '">' +
            '<div class="tag-box" data-tag-box>' + icon('fa-table-cells tag-box-icon') + '<span class="tag-chips" data-tag-chips></span>' +
            '<input type="text" class="tag-entry" maxlength="80" placeholder="Type a sector name and press Enter — e.g. Sector-4" aria-label="Add sector" ' +
            'autocomplete="off" role="combobox" aria-expanded="false" aria-autocomplete="list" data-sector-entry>' +
            '<button type="button" class="tag-caret" tabindex="-1" aria-label="Show sectors" data-tag-caret>' + icon('fa-chevron-down') + '</button></div>' +
            '<ul class="tag-suggest" role="listbox" aria-label="Sector suggestions" hidden data-tag-suggest></ul>' +
            '<div class="form-hint">Click to pick from existing sectors, or type a new name and press Enter.</div>' +
            '<div class="invalid-feedback" data-tag-error></div></div>';
    }
    function schemeForm(scheme, base, crumbs) {
        const isEdit = !!scheme;
        const v = function (k) { return isEdit ? scheme[k] : ''; };
        const sectors = isEdit ? DB.Scheme.sectors(scheme.scheme_id) : [];

        let html = (crumbs ? crumbs('Schemes', isEdit ? 'Edit' : 'Add') : '') + P.pageHeader(icon('fa-map') + ' ' + (isEdit ? 'Edit Scheme' : 'Create Scheme'),
            isEdit ? 'Update scheme details and manage its sectors.'
                : 'Fill scheme details and add its sectors.',
            '<a href="' + P.BASE + base + '" class="btn btn-light">' + icon('fa-arrow-left') + ' <span>Back to list</span></a>') +
            '<form method="post" data-action="master" novalidate data-validate data-scheme-form data-confirm="' +
            (isEdit ? 'Save changes to this scheme?' : 'Create this scheme with the details and sectors entered?') + '">' +
            '<input type="hidden" name="key" value="schemes"><input type="hidden" name="do" value="save">' +
            '<input type="hidden" name="back" value="' + e(base) + '">' + (isEdit ? '<input type="hidden" name="id" value="' + scheme.scheme_id + '">' : '') +
            // 1. details
            '<div class="card"><div class="card-header"><h3 class="card-title"><span class="step-no">1</span> Scheme details</h3></div><div class="card-body"><div class="form-grid">' +
            '<div class="form-group"><label class="form-label" for="scheme_name">Scheme name <span class="required">*</span></label>' +
            '<input type="text" id="scheme_name" name="scheme_name" class="form-control" maxlength="150" value="' + e(v('scheme_name')) + '" placeholder="e.g. Ravindra Nagar Awasiya Yojana" data-rules="required|min:3|max:150|text" data-label="Scheme name"><div class="invalid-feedback"></div></div>' +
            '<div class="form-group"><label class="form-label" for="scheme_type">Scheme type <span class="required">*</span></label>' +
            '<select id="scheme_type" name="scheme_type" class="form-select" data-rules="required" data-label="Scheme type">' + L.options(L.SCHEME_TYPES, v('scheme_type'), 'Select type') + '</select><div class="invalid-feedback"></div></div>' +
            '</div></div></div>' +
            // 2. sectors
            '<div class="card"><div class="card-header"><h3 class="card-title"><span class="step-no">2</span> Sectors <span class="pb-opt">(optional)</span></h3>' +
            '<span class="badge badge-info"><span data-sector-count>0</span> active</span></div><div class="card-body">';
        if (isEdit && sectors.length) {
            html += '<p class="form-section">' + icon('fa-list') + ' Existing sectors — rename or deactivate</p><div class="sector-list">' +
                sectors.map(function (s) {
                    return '<div class="sector-row"><div class="sector-input"><input type="text" name="sec_name_' + s.sector_id + '" class="form-control" maxlength="80" value="' + e(s.sector_name) + '" aria-label="Sector name" data-rules="required|max:80|text" data-label="Sector name" data-sector-name></div>' +
                        '<label class="check sector-active"><input type="checkbox" name="sec_active_' + s.sector_id + '" value="1"' + (s.is_active ? ' checked' : '') + ' data-sector-active><span>Active</span></label>' +
                        '<span class="badge badge-muted" title="Properties in this sector">' + icon('fa-building') + ' ' + DB.count('properties', { sector_id: s.sector_id, is_active: 1 }) + '</span></div>';
                }).join('') + '</div><p class="form-hint mb-3">Deactivated sectors keep their existing properties but cannot be used for new ones.</p>';
        }
        html += '<p class="form-section">' + icon('fa-plus') + ' ' + (isEdit ? 'Add new sectors' : 'Add sectors') + '</p>' +
            sectorTagsHtml(isEdit ? scheme.scheme_id : null) +
            '</div></div>' +
            '<div class="form-actions sticky-actions"><button type="submit" class="btn btn-primary btn-lg" data-loading-text="Saving…">' + icon('fa-floppy-disk') +
            ' <span>' + (isEdit ? 'Update Scheme' : 'Create Scheme') + '</span></button>' +
            '<a href="' + P.BASE + base + '" class="btn btn-light btn-lg">' + icon('fa-xmark') + ' <span>Cancel</span></a></div></form>';
        return { title: isEdit ? 'Edit Scheme' : 'Create Scheme', html: html };
    }

    /* ================= Unified Scheme Details ================= */
    function schemeDetail(ctx, base, crumbs) {
        const scheme = DB.find('schemes', ctx.params.id);
        if (!scheme) { return P.notFound('Scheme not found.'); }

        const sectors = DB.Scheme.sectors(scheme.scheme_id);
        const activeSectors = sectors.filter(function (s) { return s.is_active; });
        const props = DB.where('properties', { scheme_id: scheme.scheme_id, is_active: 1 });
        const totalProps = props.length;
        const ST = P.ST;
        const listedCount = props.filter(function (p) { return p.status_id === ST.LISTED; }).length;
        const underVfCount = props.filter(function (p) { return p.status_id === ST.UNDER_VERIFICATION; }).length;
        const readyCount = props.filter(function (p) { return p.status_id === ST.AUCTION_READY; }).length;
        const notReadyCount = props.filter(function (p) { return p.status_id === ST.NOT_READY; }).length;
        const expiredCount = props.filter(function (p) { return p.status_id === ST.EXPIRED; }).length;
        const soldCount = props.filter(function (p) { return p.status_id === ST.SOLD || p.status_id === ST.SENT_FOR_AUCTION; }).length;
        const readyPct = totalProps ? Math.round((readyCount / totalProps) * 100) : 0;

        const roleOptions = DB.Scheme.roleUserOptions();
        const mapping = DB.Scheme.mapping(scheme.scheme_id);
        let totalMappedUsers = 0;
        let totalMappedRoles = 0;
        roleOptions.forEach(function (opt) {
            const picked = mapping[opt.role.role_id] || [];
            if (picked.length) {
                totalMappedUsers += picked.length;
                totalMappedRoles += 1;
            }
        });

        const propBase = base.indexOf('/admin/') === 0 ? '/admin/masters/properties' : '/data-entry/properties';
        const propUrl = P.BASE + propBase + '?scheme_id=' + scheme.scheme_id;
        const canEdit = Auth.can('scheme.edit');
        const canCreate = Auth.can('property.create');

        let headerActions = '<a href="' + P.BASE + base + '" class="btn btn-light">' + icon('fa-arrow-left') + ' <span>Back to Schemes</span></a>';
        if (totalProps > 0) {
            headerActions += '<a href="' + propUrl + '" class="btn btn-info">' + icon('fa-building') + ' <span>View Plots (' + totalProps + ')</span></a>';
        }
        if (canEdit) {
            headerActions += '<button type="button" class="btn btn-light text-primary" data-scheme-users="' + scheme.scheme_id + '" title="Assign role users">' + icon('fa-users-gear') + ' <span>Assign Role Users</span></button>';
            headerActions += '<a href="' + P.BASE + base + '/' + scheme.scheme_id + '/edit" class="btn btn-primary">' + icon('fa-pen') + ' <span>Edit Scheme</span></a>';
        }

        let html = (crumbs ? crumbs('Schemes', scheme.scheme_name) : '') +
            P.pageHeader(icon('fa-map') + ' ' + e(scheme.scheme_name) + ' ' + P.active_badge(scheme.is_active),
                'Scheme Overview &middot; ' + (L.SCHEME_TYPES[scheme.scheme_type] || scheme.scheme_type) + (scheme.area_id ? ' &middot; ' + DB.areaName(scheme.area_id) : ''),
                headerActions);

        // 1. Equal-Height KPI Stat Cards Grid (4 Columns)
        html += '<div class="scheme-kpi-grid stat-grid-4 mb-4">' +
            P.statCard('blue', 'fa-table-cells', sectors.length, 'Sectors', activeSectors.length + ' active', sectors.length ? '#sectors-card' : '') +
            P.statCard('navy', 'fa-building', totalProps, 'Registered Plots', totalProps ? (readyPct + '% auction ready') : 'No plots yet', totalProps ? propUrl : '') +
            P.statCard('green', 'fa-circle-check', readyCount, 'Auction Ready', readyCount + ' cleared plots', totalProps ? (propUrl + '&status=' + ST.AUCTION_READY) : '') +
            P.statCard('gold', 'fa-users-gear', totalMappedUsers, 'Role Users Assigned', totalMappedRoles + ' role sections mapped', '#roles-card') +
            '</div>';

        // 2. Scheme Details + Property Inventory Status (2 Columns Grid)
        const listedPct = totalProps ? Math.round(listedCount * 100 / totalProps) : 0;
        const underVfPct = totalProps ? Math.round(underVfCount * 100 / totalProps) : 0;
        const notReadyPct = totalProps ? Math.round(notReadyCount * 100 / totalProps) : 0;
        const expiredPct = totalProps ? Math.round(expiredCount * 100 / totalProps) : 0;
        const soldPct = totalProps ? Math.round(soldCount * 100 / totalProps) : 0;

        html += '<div class="grid-2 mb-3">' +
            // Scheme metadata card
            '<div class="card scheme-info-card">' +
            '<div class="card-header"><h3 class="card-title">' + icon('fa-circle-info text-primary') + ' Scheme Overview</h3>' +
            '<span class="badge badge-info">' + icon('fa-map-location-dot') + ' Master Record</span></div>' +
            '<div class="card-body"><dl class="detail-list scheme-detail-dl">' +
            '<dt>Scheme Name</dt><dd><strong>' + e(scheme.scheme_name) + '</strong></dd>' +
            '<dt>Scheme Type</dt><dd><span class="badge badge-navy">' + e(L.SCHEME_TYPES[scheme.scheme_type] || scheme.scheme_type) + '</span></dd>' +
            (scheme.area_id ? '<dt>Area / Zone</dt><dd>' + e(DB.areaName(scheme.area_id)) + '</dd>' : '') +
            '<dt>Status</dt><dd>' + P.active_badge(scheme.is_active) + '</dd>' +
            '<dt>Total Sectors</dt><dd><strong>' + sectors.length + '</strong> sectors <small class="text-muted">(' + activeSectors.length + ' active)</small></dd>' +
            '<dt>Total Plots</dt><dd><strong>' + totalProps + '</strong> registered plots' + (totalProps ? ' &middot; <a href="' + propUrl + '" class="small">View all</a>' : '') + '</dd>' +
            '<dt>Auction Readiness</dt><dd><span class="badge ' + (readyPct > 0 ? 'badge-success' : 'badge-muted') + '">' + readyPct + '% ready</span></dd>' +
            '</dl></div></div>' +

            // Property Inventory Breakdown Card
            '<div class="card scheme-inventory-card">' +
            '<div class="card-header"><h3 class="card-title">' + icon('fa-chart-pie text-primary') + ' Property Inventory Status</h3>' +
            (totalProps ? '<a href="' + propUrl + '" class="btn btn-light btn-sm">' + icon('fa-arrow-up-right-from-square') + ' <span>Open Register</span></a>' : '') +
            '</div><div class="card-body">' +
            (totalProps ? '<ul class="bar-list scheme-bar-list">' +
                '<li><div class="bar-label"><span>' + icon('fa-list text-info') + ' <a href="' + propUrl + '&status=' + ST.LISTED + '">Listed (New)</a></span><strong>' + listedCount + ' <small class="text-muted">(' + listedPct + '%)</small></strong></div>' +
                '<div class="bar" role="img" aria-label="Listed: ' + listedCount + '"><span class="bar-fill bar-info" style="width: ' + listedPct + '%;" data-pct="' + listedPct + '"></span></div></li>' +
                '<li><div class="bar-label"><span>' + icon('fa-hourglass-half text-warning') + ' <a href="' + propUrl + '&status=' + ST.UNDER_VERIFICATION + '">Under Verification</a></span><strong>' + underVfCount + ' <small class="text-muted">(' + underVfPct + '%)</small></strong></div>' +
                '<div class="bar" role="img" aria-label="Under Verification: ' + underVfCount + '"><span class="bar-fill bar-warning" style="width: ' + underVfPct + '%;" data-pct="' + underVfPct + '"></span></div></li>' +
                '<li><div class="bar-label"><span>' + icon('fa-circle-check text-success') + ' <a href="' + propUrl + '&status=' + ST.AUCTION_READY + '">Auction Ready</a></span><strong class="text-success">' + readyCount + ' <small class="text-muted">(' + readyPct + '%)</small></strong></div>' +
                '<div class="bar" role="img" aria-label="Auction Ready: ' + readyCount + '"><span class="bar-fill bar-success" style="width: ' + readyPct + '%;" data-pct="' + readyPct + '"></span></div></li>' +
                '<li><div class="bar-label"><span>' + icon('fa-circle-xmark text-danger') + ' <a href="' + propUrl + '&status=' + ST.NOT_READY + '">Not Ready (Issues)</a></span><strong class="' + (notReadyCount ? 'text-danger' : '') + '">' + notReadyCount + ' <small class="text-muted">(' + notReadyPct + '%)</small></strong></div>' +
                '<div class="bar" role="img" aria-label="Not Ready: ' + notReadyCount + '"><span class="bar-fill bar-danger" style="width: ' + notReadyPct + '%;" data-pct="' + notReadyPct + '"></span></div></li>' +
                '<li><div class="bar-label"><span>' + icon('fa-calendar-xmark text-muted') + ' <a href="' + propUrl + '&status=' + ST.EXPIRED + '">Validity Expired</a></span><strong>' + expiredCount + ' <small class="text-muted">(' + expiredPct + '%)</small></strong></div>' +
                '<div class="bar" role="img" aria-label="Expired: ' + expiredCount + '"><span class="bar-fill bar-muted" style="width: ' + expiredPct + '%;" data-pct="' + expiredPct + '"></span></div></li>' +
                '<li><div class="bar-label"><span>' + icon('fa-gavel text-primary') + ' <a href="' + propUrl + '&status=' + ST.SOLD + '">Sold / Sent for Auction</a></span><strong>' + soldCount + ' <small class="text-muted">(' + soldPct + '%)</small></strong></div>' +
                '<div class="bar" role="img" aria-label="Sold: ' + soldCount + '"><span class="bar-fill bar-primary" style="width: ' + soldPct + '%;" data-pct="' + soldPct + '"></span></div></li>' +
                '</ul>'
                : '<div class="empty-state compact py-4 text-center">' + icon('fa-building-circle-exclamation empty-icon') + '<p class="empty-title">No plots registered in this scheme</p><p class="text-muted">Add plots in Property Register to see inventory progress.</p>' +
                (canCreate ? '<a href="' + P.BASE + propBase + '/create" class="btn btn-primary btn-sm mt-2">' + icon('fa-plus') + ' <span>Add Property</span></a>' : '') +
                '</div>') +
            '</div></div></div>';

        // 3. Sectors List Card
        html += '<div class="card mb-3" id="sectors-card"><div class="card-header"><h3 class="card-title">' + icon('fa-table-cells text-primary') + ' Sectors in this Scheme (' + sectors.length + ')</h3>' +
            (canEdit ? '<a href="' + P.BASE + base + '/' + scheme.scheme_id + '/edit" class="btn btn-light btn-sm">' + icon('fa-pen') + ' <span>Manage Sectors</span></a>' : '') +
            '</div><div class="card-body p-0">';
        if (sectors.length) {
            html += '<div class="table-wrap"><table class="table table-stack table-top"><thead><tr>' +
                '<th class="cell-no">#</th><th>Sector Name</th><th>Active Status</th><th>Plots in Sector</th><th class="text-right">Actions</th></tr></thead><tbody>' +
                sectors.map(function (s, idx) {
                    const secPropsCount = DB.count('properties', { sector_id: s.sector_id, is_active: 1 });
                    return '<tr>' +
                        '<td class="cell-no">' + (idx + 1) + '</td>' +
                        '<td><strong>' + e(s.sector_name) + '</strong></td>' +
                        '<td>' + P.active_badge(s.is_active) + '</td>' +
                        '<td><span class="badge badge-info">' + secPropsCount + '</span></td>' +
                        '<td class="text-right">' +
                        (secPropsCount > 0 ? '<a href="' + propUrl + '&sector_id=' + s.sector_id + '" class="btn btn-light btn-sm" title="View plots in ' + e(s.sector_name) + '">' + icon('fa-eye') + ' <span>View Plots</span></a>' : '<span class="text-muted small">No plots</span>') +
                        '</td></tr>';
                }).join('') +
                '</tbody></table></div>';
        } else {
            html += '<div class="empty-state compact py-4 text-center">' + icon('fa-table-cells empty-icon') +
                '<p class="empty-title">No sectors added in this scheme yet</p>' +
                '<p class="text-muted">Sectors help subdivide your scheme into manageable blocks. You can add sectors by editing this scheme.</p>' +
                (canEdit ? '<a href="' + P.BASE + base + '/' + scheme.scheme_id + '/edit" class="btn btn-primary btn-sm mt-2">' + icon('fa-plus') + ' <span>Add Sectors</span></a>' : '') +
                '</div>';
        }
        html += '</div></div>';

        // 4. Assigned Role Users Card (Grid of modern Role Boxes)
        html += '<div class="card mb-3" id="roles-card"><div class="card-header">' +
            '<div><h3 class="card-title">' + icon('fa-users-gear text-primary') + ' Assigned Role Users</h3>' +
            '<div class="text-muted small">Designated role users who verify properties belonging to this scheme</div></div>' +
            (canEdit ? '<button type="button" class="btn btn-primary btn-sm" data-scheme-users="' + scheme.scheme_id + '">' + icon('fa-user-pen') + ' <span>Assign Users</span></button>' : '') +
            '</div><div class="card-body">';

        if (roleOptions.length) {
            html += '<div class="scheme-roles-grid">' + roleOptions.map(function (opt) {
                const r = opt.role;
                const picked = mapping[r.role_id] || [];
                const pickedUsers = opt.users.filter(function (u) { return picked.indexOf(Number(u.user_id)) !== -1; });
                return '<div class="scheme-role-card">' +
                    '<div class="scheme-role-header">' +
                    '<div class="scheme-role-title-wrap">' +
                    '<span class="scheme-role-icon">' + icon('fa-user-tie') + '</span>' +
                    '<span class="scheme-role-name">' + e(r.role_name) + '</span>' +
                    (opt.status_name ? '<span class="badge badge-success small" style="font-size: 10.5px; padding: 2px 6px;">' + e(opt.status_name) + '</span>' : '') +
                    '</div>' +
                    (pickedUsers.length
                        ? '<span class="badge badge-primary">' + icon('fa-user-check') + ' ' + pickedUsers.length + ' assigned</span>'
                        : '<span class="badge badge-muted">' + icon('fa-user-slash') + ' 0 assigned</span>') +
                    '</div><div class="scheme-role-body">' +
                    (pickedUsers.length
                        ? '<div class="scheme-role-user-list">' + pickedUsers.map(function (u) {
                            const initial = (u.name || '').trim().charAt(0).toUpperCase() || 'U';
                            return '<div class="scheme-role-user-item">' +
                                '<div class="scheme-role-user-avatar">' + e(initial) + '</div>' +
                                '<div class="scheme-role-user-info">' +
                                '<div class="scheme-role-user-name">' + e(u.name) + '</div>' +
                                '<div class="scheme-role-user-meta">' +
                                (u.mobile ? '<span>' + icon('fa-phone') + ' ' + e(u.mobile) + '</span>' : '') +
                                (u.email ? '<span>' + icon('fa-envelope') + ' ' + e(u.email) + '</span>' : '') +
                                '</div></div></div>';
                        }).join('') + '</div>'
                        : '<div class="scheme-role-empty">' +
                        '<div class="scheme-role-empty-info">' +
                        '<span class="scheme-role-empty-icon">' + icon('fa-user-clock') + '</span>' +
                        '<div class="scheme-role-empty-text">' +
                        '<strong>No users assigned for ' + e(r.role_name) + '</strong>' +
                        '<small>Verification tasks for this role currently have no designated user.</small>' +
                        '</div></div>' +
                        (canEdit ? '<button type="button" class="btn btn-light btn-sm" data-scheme-users="' + scheme.scheme_id + '" title="Assign users to ' + e(r.role_name) + '">' + icon('fa-plus') + ' <span>Assign</span></button>' : '') +
                        '</div>') +
                    '</div></div>';
            }).join('') + '</div>';
        } else {
            html += '<div class="empty-state compact py-4 text-center">' + icon('fa-users-slash empty-icon') +
                '<p class="empty-title">No status-assigned roles configured</p>' +
                '<p class="text-muted">Please configure roles with verification statuses in the Roles Master first.</p></div>';
        }

        html += '</div></div>';

        return { title: scheme.scheme_name, html: html };
    }

    /** Scheme list + create + view + edit screens under any base path (Data Entry and Super Admin masters). */
    P.registerSchemeScreens = function (base, panel, crumbs) {
        P.route(base, panel, function (ctx) { return schemeListPage(ctx, base, crumbs); }, 'scheme.view');
        P.route(base + '/create', panel, function () { return schemeForm(null, base, crumbs); }, 'scheme.create');
        P.route(base + '/{id}', panel, function (ctx) { return schemeDetail(ctx, base, crumbs); }, 'scheme.view');
        P.route(base + '/{id}/edit', panel, function (ctx) {
            const s = DB.find('schemes', ctx.params.id);
            return s ? schemeForm(s, base, crumbs) : P.notFound('Scheme not found.');
        }, 'scheme.edit');
    };
    P.registerSchemeScreens('/data-entry/schemes', PANEL, null);


    /* ================= Property Status Update Modal (Direct Hierarchy Transfer) ================= */
    function openPropertyStatusModal(taskId, plotNo, schemeName, sectorName, roleName, currentStatusName, currentStatusId, propertyId) {
        const old = document.getElementById('property-status-modal');
        if (old) { old.remove(); }

        const backdrop = document.createElement('div');
        backdrop.id = 'property-status-modal';
        backdrop.className = 'modal-backdrop is-open';
        backdrop.style.zIndex = '1090';
        backdrop.setAttribute('role', 'dialog');
        backdrop.setAttribute('aria-modal', 'true');
        backdrop.setAttribute('aria-labelledby', 'status-modal-title');

        const curPath = window.location.pathname;

        // Fetch dynamic statuses from Status Master (Master Admin)
        const allStatuses = (DB.all('statuses') || [])
            .filter(function (s) { return s.is_active; })
            .sort(function (a, b) { return (a.sort_order || 0) - (b.sort_order || 0); });

        // Fetch active roles mapped to status
        const rolesWithStatus = (DB.all('roles') || [])
            .filter(function (r) { return r.is_active && r.status_id; })
            .sort(function (a, b) { return (a.sort_order || 0) - (b.sort_order || 0); });

        // Find current task and next recommended role in ascending hierarchy
        const taskObj = DB.find('tasks', taskId);
        const currentRoleId = taskObj ? taskObj.role_id : null;
        let currentRoleIdx = -1;
        if (currentRoleId) {
            for (let i = 0; i < rolesWithStatus.length; i++) {
                if (rolesWithStatus[i].role_id === currentRoleId) {
                    currentRoleIdx = i;
                    break;
                }
            }
        }
        const nextRole = (currentRoleIdx !== -1 && currentRoleIdx + 1 < rolesWithStatus.length) ? rolesWithStatus[currentRoleIdx + 1] : null;
        const defaultStatusId = nextRole ? nextRole.status_id : (allStatuses.length ? allStatuses[0].status_id : null);

        // Build dynamic options for Status Master dropdown
        const statusOptions = allStatuses.map(function (s) {
            const mappedRole = rolesWithStatus.find(function (r) { return r.status_id === s.status_id; });
            const isRecommended = nextRole && nextRole.status_id === s.status_id;
            let label = s.name;
            if (isRecommended) {
                label = '⭐ ' + s.name + ' — Recommended (Next: ' + mappedRole.role_name + ')';
            } else if (mappedRole) {
                label = '➔ ' + s.name + ' — (Forwards to: ' + mappedRole.role_name + ')';
            } else {
                label = '• ' + s.name + ' — (Master Status)';
            }
            const isSel = (s.status_id === defaultStatusId) ? ' selected' : '';
            return '<option value="' + s.status_id + '"' + isSel + ' data-decision="APPROVED" data-name="' + e(s.name) + '" data-color="' + (s.color || 'info') + '" data-icon="' + (s.icon || 'fa-flag') + '" data-desc="' + e(s.description || '') + '" data-role="' + e(mappedRole ? mappedRole.role_name : '') + '">' + e(label) + '</option>';
        }).join('');

        const allOptionsHtml = statusOptions +
            '<option value="__OBJECTION__" data-decision="REJECTED" data-name="Not Ready" data-color="danger" data-icon="fa-circle-xmark" data-role="" style="color: #dc2626; font-weight: 700;">' +
            '⚠ Raise Objection / Mark as Not Ready' +
            '</option>';

        backdrop.innerHTML = '<div class="modal status-modal">' +
            '<div class="modal-header">' +
            '<div class="modal-header-left">' +
            '<span class="modal-icon">' + icon('fa-arrows-rotate') + '</span>' +
            '<div class="modal-header-text"><h2 class="modal-title" id="status-modal-title">Update Property Status</h2>' +
            '<div class="modal-subtitle">Plot <strong class="text-plot">' + e(plotNo) + '</strong> &bull; ' + e(schemeName) + (sectorName && sectorName !== '—' ? ' &bull; ' + e(sectorName) : '') + '</div></div></div>' +
            '<button type="button" class="btn btn-light btn-sm btn-icon-only modal-close-btn" data-modal-close aria-label="Close">' + icon('fa-xmark') + '</button></div>' +

            '<form method="post" data-action="task-respond" id="status-update-form" class="status-modal-form">' +
            '<input type="hidden" name="id" value="' + taskId + '">' +
            '<input type="hidden" name="back" value="' + e(curPath) + '">' +
            '<input type="hidden" name="decision" id="modal_decision" value="APPROVED">' +
            '<input type="hidden" name="status_id" id="modal_status_id" value="' + (defaultStatusId || '') + '">' +

            '<div class="modal-body status-modal-body">' +

            // Summary Context Ribbon
            '<div class="status-modal-summary">' +
            '<div class="summary-item summary-item-plot"><span class="summary-label">Plot No.</span><span class="summary-val plot-pill">' + e(plotNo) + '</span></div>' +
            '<div class="summary-item summary-item-scheme"><span class="summary-label">Scheme / Sector</span><span class="summary-val scheme-name" title="' + e(schemeName + (sectorName && sectorName !== '—' ? ' / ' + sectorName : '')) + '">' + e(schemeName + (sectorName && sectorName !== '—' ? ' / ' + sectorName : '')) + '</span></div>' +
            '<div class="summary-item summary-item-stage"><span class="summary-label">Your Stage</span><span class="summary-val stage-val"><i class="fa-solid fa-user-tie"></i> ' + e(roleName || 'Officer') + '</span></div>' +
            '<div class="summary-item summary-item-status"><span class="summary-label">Current Status</span><div class="status-badge-wrap">' + (currentStatusId ? P.status_badge(Number(currentStatusId)) : (currentStatusName ? '<span class="badge badge-info">' + e(currentStatusName) + '</span>' : '—')) + '</div></div>' +
            '</div>' +

            // Status Selection Dropdown
            '<div class="form-group status-select-group">' +
            '<div class="status-select-label-row">' +
            '<label class="form-label" for="modal_status_select">' +
            'Select New Status <span class="required">*</span></label>' +
            '<span class="status-master-tag"><i class="fa-solid fa-database text-primary"></i> Status Master</span>' +
            '</div>' +
            '<div class="status-select-wrap">' +
            '<select id="modal_status_select" class="form-select status-select-field">' +
            allOptionsHtml +
            '</select>' +
            '</div></div>' +

            // Dynamic Action Feedback Banner / Preview
            '<div id="modal_action_banner" class="status-action-banner banner-info">' +
            '<div class="banner-icon-box" id="banner_icon_box">' +
            icon('fa-arrow-right-arrow-left') +
            '</div>' +
            '<div class="banner-content">' +
            '<div class="banner-head">' +
            '<span id="banner_badge"></span>' +
            '<span id="banner_role_badge"></span>' +
            '</div>' +
            '<div id="banner_text" class="banner-desc"></div>' +
            '</div></div>' +

            // Description / Remarks Textarea
            '<div class="form-group remarks-form-group">' +
            '<div class="remarks-header-row">' +
            '<label class="form-label" for="modal_status_remarks">' +
            'Description / Verification Remarks <span class="required">*</span></label>' +
            '<span id="remarks-count" class="remarks-counter">0 / 1000</span>' +
            '</div>' +
            '<textarea id="modal_status_remarks" name="remarks" class="form-control status-remarks-field" rows="3" maxlength="1000" placeholder="Enter detailed inspection findings, report notes, reasons, or status update description..." required></textarea>' +
            '<div class="remarks-audit-note">' +
            '<i class="fa-solid fa-clock-rotate-left text-primary"></i>' +
            '<span>Permanently recorded in round history &amp; audit log</span>' +
            '</div></div>' +

            '</div>' +

            // Modal Footer
            '<div class="modal-footer">' +
            '<button type="button" class="btn btn-light" data-modal-close>' + icon('fa-xmark') + ' <span>Cancel</span></button>' +
            '<button type="submit" class="btn btn-primary" id="btn-submit-status" data-loading-text="Updating Status…">' + icon('fa-paper-plane') + ' <span>Submit &amp; Update Status</span></button>' +
            '</div></form></div>';

        document.body.appendChild(backdrop);
        document.body.classList.add('modal-open');

        const closeModal = function () {
            backdrop.remove();
            document.body.classList.remove('modal-open');
        };
        backdrop.querySelectorAll('[data-modal-close]').forEach(function (btn) {
            btn.addEventListener('click', closeModal);
        });
        backdrop.addEventListener('click', function (ev) {
            if (ev.target === backdrop) { closeModal(); }
        });

        // Dropdown selection logic
        const statusSelect = backdrop.querySelector('#modal_status_select');
        const inputDecision = backdrop.querySelector('#modal_decision');
        const inputStatusId = backdrop.querySelector('#modal_status_id');
        const submitBtn = backdrop.querySelector('#btn-submit-status');
        const banner = backdrop.querySelector('#modal_action_banner');
        const bannerBadge = backdrop.querySelector('#banner_badge');
        const bannerText = backdrop.querySelector('#banner_text');
        const bannerRoleBadge = backdrop.querySelector('#banner_role_badge');

        function updateFromSelect() {
            if (!statusSelect || !statusSelect.options.length) { return; }
            const opt = statusSelect.options[statusSelect.selectedIndex];
            if (!opt) { return; }

            const decision = opt.getAttribute('data-decision') || 'APPROVED';
            const val = opt.value;
            const sName = opt.getAttribute('data-name') || opt.text;
            const sColor = opt.getAttribute('data-color') || 'info';
            const sIcon = opt.getAttribute('data-icon') || 'fa-flag';
            const sRole = opt.getAttribute('data-role') || '';

            inputDecision.value = decision;
            inputStatusId.value = (val === '__OBJECTION__' ? '' : val);

            const iconBox = backdrop.querySelector('#banner_icon_box');

            if (decision === 'REJECTED') {
                submitBtn.className = 'btn btn-danger';
                submitBtn.innerHTML = icon('fa-circle-xmark') + ' <span>Submit Objection (Not Ready)</span>';
                banner.className = 'status-action-banner banner-danger';
                if (iconBox) { iconBox.innerHTML = icon('fa-triangle-exclamation'); }
                bannerBadge.innerHTML = '<span class="badge badge-danger"><i class="fa-solid fa-circle-xmark"></i> Not Ready</span>';
                bannerRoleBadge.innerHTML = '<span class="badge badge-light badge-round-close" style="color: #b91c1c; font-weight: 700; border: 1px solid #fecaca;">Round Closes</span>';
                bannerText.innerHTML = 'Property will be marked as <strong>Not Ready</strong> and the verification round will close.';
            } else {
                submitBtn.className = 'btn btn-primary';
                submitBtn.innerHTML = icon('fa-paper-plane') + ' <span>Submit &amp; Update Status</span>';
                banner.className = 'status-action-banner banner-info';
                if (iconBox) { iconBox.innerHTML = icon('fa-arrow-right-arrow-left'); }
                bannerBadge.innerHTML = '<span class="badge badge-' + e(sColor) + '"><i class="fa-solid ' + e(sIcon) + '"></i> ' + e(sName) + '</span>';
                if (sRole) {
                    bannerRoleBadge.innerHTML = '<span class="badge badge-primary"><i class="fa-solid fa-user-tie"></i> Next: ' + e(sRole) + '</span>';
                    bannerText.innerHTML = 'Upon submit, plot will be transferred to <strong>' + e(sRole) + '</strong> team for verification.';
                } else {
                    bannerRoleBadge.innerHTML = '<span class="badge badge-light" style="border: 1px solid #cbd5e1; color: #475569;">Master Status</span>';
                    bannerText.innerHTML = 'Upon submit, property status will be updated to <strong>"' + e(sName) + '"</strong>.';
                }
            }
        }

        if (statusSelect) {
            statusSelect.addEventListener('change', updateFromSelect);
            updateFromSelect();
        }

        // Character counter
        const ta = backdrop.querySelector('#modal_status_remarks');
        const counter = backdrop.querySelector('#remarks-count');
        if (ta && counter) {
            ta.addEventListener('input', function () {
                counter.textContent = ta.value.length + ' / 1000';
            });
            setTimeout(function () { ta.focus(); }, 120);
        }

        // Validate description
        const form = backdrop.querySelector('#status-update-form');
        form.addEventListener('submit', function (ev) {
            if (!ta.value.trim()) {
                ev.preventDefault();
                ev.stopPropagation();
                ta.focus();
                P.toast('error', 'Please enter a description / remark before submitting.');
                return false;
            }
        });
    }

    // Global listener for opening the status update modal
    document.addEventListener('click', function (ev) {
        const btn = ev.target.closest('[data-update-status]');
        if (btn) {
            ev.preventDefault();
            const taskId = btn.getAttribute('data-update-status');
            const plotNo = btn.getAttribute('data-plot-no') || '';
            const schemeName = btn.getAttribute('data-scheme-name') || '';
            const sectorName = btn.getAttribute('data-sector-name') || '';
            const roleName = btn.getAttribute('data-role-name') || '';
            const statusName = btn.getAttribute('data-status-name') || '';
            const currentStatusId = btn.getAttribute('data-current-status-id') || '';
            const propertyId = btn.getAttribute('data-property-id') || '';
            openPropertyStatusModal(taskId, plotNo, schemeName, sectorName, roleName, statusName, currentStatusId, propertyId);
        }
    });

    /* ================= Property Register ================= */
    P.propertyRows = function () {
        const meUser = Auth.user();
        const meId = meUser ? meUser.user_id : null;
        return DB.where('properties', { is_active: 1 }).map(function (p) {
            const sc = DB.find('schemes', p.scheme_id);
            const se = DB.find('sectors', p.sector_id);
            const cu = DB.find('users', p.created_by);
            const round = p.current_round_id ? DB.find('rounds', p.current_round_id) : null;
            const tasks = round ? DB.where('tasks', { round_id: round.round_id }) : [];
            let myPendingTaskId = null;
            let myPendingRoleName = null;
            if (meId && round && round.round_status === 'OPEN') {
                const myTask = tasks.find(function (t) {
                    return t.task_status === 'PENDING' && DB.exists('task_assignees', { task_id: t.task_id, user_id: meId });
                });
                if (myTask) {
                    myPendingTaskId = myTask.task_id;
                    const rObj = DB.find('roles', myTask.role_id);
                    myPendingRoleName = rObj ? rObj.role_name : '';
                }
            }
            return Object.assign({}, p, {
                scheme_name: sc ? sc.scheme_name : '—', sector_name: se ? se.sector_name : '—',
                type_name: L.PROPERTY_TYPES[p.plot_type_id] || '—', status_name: P.statusName(p.status_id),
                area: sc ? DB.areaName(sc.area_id) : '—',
                created_by_name: cu ? cu.name : '', round_no: round ? round.round_no : null,
                tasks_total: tasks.length, tasks_approved: tasks.filter(function (t) { return t.task_status === 'APPROVED'; }).length,
                my_pending_task_id: myPendingTaskId,
                my_pending_role_name: myPendingRoleName
            });
        });
    };
    P.propertyFilters = function (q, extraStatuses) {
        const schemes = {};
        DB.all('schemes').sort(P.by(function (s) { return s.scheme_name; })).forEach(function (s) { schemes[s.scheme_id] = s.scheme_name; });
        const sectors = {};
        if (q.scheme_id) { DB.Scheme.sectors(q.scheme_id).forEach(function (s) { sectors[s.sector_id] = s.sector_name; }); }
        const none = !Object.keys(sectors).length;
        const allStatuses = Object.assign({ MY_PENDING: '⚡ Action Required by Me' }, L.PROPERTY_STATUSES, extraStatuses || {});
        return '<select name="scheme_id" class="form-select" aria-label="Scheme" data-auto-submit data-reset-on-change="sector_id">' + L.options(schemes, q.scheme_id, 'All schemes') + '</select>' +
            '<select name="sector_id" class="form-select" aria-label="Sector" data-auto-submit' + (none ? ' disabled' : '') + '>' + L.options(sectors, q.sector_id, none ? 'Select scheme first' : 'All sectors') + '</select>' +
            '<select name="type" class="form-select" aria-label="Plot type" data-auto-submit>' + L.options(L.PROPERTY_TYPES, q.type, 'All plot types') + '</select>' +
            '<select name="status" class="form-select" aria-label="Status" data-auto-submit>' + L.options(allStatuses, q.status, 'All status') + '</select>';
    };
    P.filterProperties = function (rows, q) {
        if (q.scheme_id) { rows = rows.filter(function (r) { return String(r.scheme_id) === q.scheme_id; }); }
        if (q.sector_id) { rows = rows.filter(function (r) { return String(r.sector_id) === q.sector_id; }); }
        if (q.type) { rows = rows.filter(function (r) { return String(r.plot_type_id) === q.type; }); }
        if (q.status === 'MY_PENDING') { rows = rows.filter(function (r) { return Boolean(r.my_pending_task_id); }); }
        else if (q.status === 'REVERIFY_DUE') { rows = rows.filter(function (r) { return DB.Verification.isDue(r); }); }
        else if (q.status) { rows = rows.filter(function (r) { return String(r.status_id) === q.status; }); }
        return rows;
    };
    P.propertySortable = {
        scheme: function (r) { return r.scheme_name; }, sector: function (r) { return r.sector_name; },
        plot: function (r) { return r.plot_no; }, type: function (r) { return r.type_name; }, area: function (r) { return r.area_sqm; },
        status: function (r) { return r.status_name; }, created: function (r) { return r.property_id; }
    };
    P.propertySearch = function (r) { return [r.plot_no, r.scheme_name, r.sector_name, r.type_name, r.area]; };
    /** "Area" cell of a property table: value + unit (and sq m when another unit), or — */
    P.areaCell = function (r) {
        if (r.area_value == null) { return '—'; }
        return P.fmt_num(r.area_value, 2) + ' ' + e(L.unitSymbol(r.unit_id)) +
            (r.unit_id !== L.BASE_UNIT ? '<span class="cell-sub">= ' + P.fmt_num(r.area_sqm, 2) + ' ' + e(L.unitSymbol(L.BASE_UNIT)) + '</span>' : '');
    };

    /* Reserve price + site plan (entered by Data Entry), shown on the detail pages */
    P.propertyDoc = function (propertyId, context) {
        const rows = P.attachmentsFor({ property_id: propertyId, context: context });
        return rows.length ? rows[rows.length - 1] : null;
    };
    P.fmt_rupees = function (v) {
        return v ? '₹' + Number(v).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—';
    };
    P.propertyPriceRows = function (prop) {
        const plan = P.propertyDoc(prop.property_id, 'SITE_PLAN');
        const priceRow = (prop && prop.reserve_price) ? '<dt>Reserve price</dt><dd>' + e(P.fmt_rupees(prop.reserve_price)) + '</dd>' : '';
        return priceRow + '<dt>Site plan</dt><dd>' + (plan ? P.attachmentChips([plan]) : '<span class="text-muted">—</span>') + '</dd>';
    };

    /* ================= Verification Indicators & Mapped Role Modal ================= */
    const VF = {
        APPROVED: ['ok', 'fa-circle-check', 'Approved'],
        REJECTED: ['bad', 'fa-circle-xmark', 'Rejected'],
        NO_RESPONSE: ['late', 'fa-triangle-exclamation', 'No response'],
        PENDING: ['wait', 'fa-hourglass-half', 'Pending'],
        SKIPPED: ['skip', 'fa-minus', 'Not required (round closed)']
    };
    function verificationCell(r) {
        const round = DB.where('rounds', { property_id: r.property_id }).sort(function (a, b) { return b.round_no - a.round_no; })[0];
        if (!round) {
            return '<div class="vf vf-empty"><span class="vf-none">' + icon('fa-hourglass-start') + ' Not sent yet</span><span class="vf-sub">' + (r.role_count || 0) + ' sections mapped</span></div>';
        }
        const open = round.round_status === 'OPEN';
        const groups = { APPROVED: [], REJECTED: [], NO_RESPONSE: [], PENDING: [], SKIPPED: [] };
        const segs = DB.where('tasks', { round_id: round.round_id }).map(function (t) {
            return { t: t, role: DB.find('roles', t.role_id), user: t.responded_by ? DB.find('users', t.responded_by) : null };
        }).sort(function (a, b) { return (a.role ? a.role.sort_order : 0) - (b.role ? b.role.sort_order : 0); }).map(function (x) {
            const roleName = x.role ? x.role.role_name : 'Role';
            const key = x.t.task_status === 'PENDING' && !open ? 'SKIPPED' : (groups[x.t.task_status] ? x.t.task_status : 'PENDING');
            const tip = roleName + ' — ' + VF[key][2] + (x.user ? ' by ' + x.user.name : '') +
                (x.t.responded_at ? ' on ' + P.fmt_datetime(x.t.responded_at) : (key === 'PENDING' ? ' · due ' + P.fmt_date(x.t.sla_due_date) : '')) +
                (x.t.remarks ? ' · “' + x.t.remarks + '”' : '');
            groups[key].push({ name: roleName.replace(/\s+Cell$/i, ''), tip: tip });
            return '<span class="vf-seg vf-' + VF[key][0] + '" title="' + e(tip) + '"></span>';
        });
        const total = segs.length;
        const responded = groups.APPROVED.length + groups.REJECTED.length;
        const note = function (key, text) {
            const g = groups[key];
            return '<span class="vf-note vf-' + VF[key][0] + '" title="' + e(g.map(function (x) { return x.tip; }).join('\n')) + '">' + icon(VF[key][1]) + ' <span>' + text + '</span></span>';
        };
        const names = function (key) { return e(groups[key].map(function (x) { return x.name; }).join(', ')); };
        const notes = Object.keys(groups).filter(function (k) { return groups[k].length; }).map(function (k) {
            if (groups[k].length === total) { return note(k, 'All ' + VF[k][2].toLowerCase()); }
            return k === 'APPROVED' ? note(k, groups[k].length + ' approved') : note(k, names(k));
        }).join('');
        return '<div class="vf"><div class="vf-head"><span class="vf-count" title="' + responded + ' of ' + total + ' sections verified">' +
            '<strong>' + responded + '</strong>/' + total + '<span class="sr-only"> sections verified</span></span>' +
            '<span class="vf-round" title="Verification round ' + round.round_no + '">R' + round.round_no + '</span></div>' +
            '<div class="vf-bar" aria-hidden="true">' + segs.join('') + '</div><div class="vf-notes">' + notes + '</div></div>';
    }



    /* ================= Unified Property Register List ================= */
    function propertyListPage(ctx, base, crumbs) {
        const q = ctx.query;
        const p = P.paginate(P.filterProperties(P.propertyRows(), q), ctx, {
            sort: 'created', dir: 'desc', filters: ['scheme_id', 'sector_id', 'type', 'status'],
            search: P.propertySearch, sortable: P.propertySortable
        });
        const sendable = DB.Verification.SENDABLE;
        const canSend = Auth.can('verification.send');
        const canCreate = Auth.can('property.create');
        const canEdit = Auth.can('property.edit');
        const ST = P.ST;

        const rows = p.items.map(function (r, i) {
            const st = r.status_id;
            const can = canSend && sendable.indexOf(st) !== -1;
            const due = DB.Verification.isDue(r);
            const cls = due ? 'row-warning' : st === ST.AUCTION_READY ? 'row-success' : (st === ST.NOT_READY || st === ST.EXPIRED ? 'row-danger' : '');
            const view = P.BASE + base + '/' + r.property_id;

            return '<tr class="' + cls + '">' +
                (canSend ? '<td class="cell-check" data-label="Select">' +
                    (can ? '<input type="checkbox" name="ids[]" value="' + r.property_id + '" aria-label="Select plot ' + e(r.plot_no) + '" data-status="' + st + '">'
                        : '<input type="checkbox" disabled aria-label="Not sendable">') + '</td>' : '') +
                '<td data-label="S.No" class="cell-no">' + (p.from() + i) + '</td>' +
                '<td data-label="Plot no."><a href="' + view + '"><strong>' + e(r.plot_no) + '</strong></a>' + (r.is_corner ? '<span class="cell-sub">Corner</span>' : '') + '</td>' +
                '<td data-label="Scheme">' + e(r.scheme_name) + '</td><td data-label="Sector">' + e(r.sector_name) + '</td>' +
                '<td data-label="Plot type">' + e(r.type_name) + '</td>' +
                '<td data-label="Area" class="nowrap">' + P.areaCell(r) + '</td>' +
                '<td data-label="Verification" class="cell-vf">' + verificationCell(r) + '</td>' +
                '<td data-label="Status">' + P.status_badge(st) +
                (r.my_pending_task_id ? '<span class="badge badge-warning" style="display:inline-flex; align-items:center; gap:4px; margin-top:4px; font-size:11px; font-weight:600;">' + icon('fa-hand-point-right') + ' Review Required</span>' : '') +
                (r.round_no ? '<span class="cell-sub">R' + r.round_no + (st === ST.UNDER_VERIFICATION ? ' · ' + r.tasks_approved + '/' + r.tasks_total + ' approved' : '') + '</span>' : '') +
                (st === ST.AUCTION_READY || st === ST.EXPIRED
                    ? '<span class="cell-sub' + (due || st === ST.EXPIRED ? ' text-danger' : '') + '">' + (st === ST.EXPIRED ? 'Expired ' : 'Valid till ') +
                    e(P.fmt_date(r.valid_till)) + (due ? ' (' + P.dueText(r.valid_till) + ')' : '') + '</span>' : '') + '</td>' +
                '<td class="actions"><div class="btn-group' + (r.my_pending_task_id ? '' : ' btn-group-icons') + '">' +
                (r.my_pending_task_id ? '<button type="button" class="btn btn-primary btn-sm" data-update-status="' + r.my_pending_task_id + '" data-plot-no="' + e(r.plot_no) + '" data-scheme-name="' + e(r.scheme_name) + '" data-sector-name="' + e(r.sector_name) + '" data-role-name="' + e(r.my_pending_role_name || '') + '" data-status-name="' + e(r.status_name || '') + '" data-current-status-id="' + r.status_id + '" data-property-id="' + r.property_id + '" title="Update Status &amp; Description">' + icon('fa-pen-to-square') + ' <span>Update Status</span></button>' : '') +
                '<a href="' + view + '" class="btn btn-light btn-sm btn-icon-only" title="View details &amp; status" aria-label="View details">' + icon('fa-eye') + '</a>' +
                '<a href="' + view + '#timeline" class="btn btn-light btn-sm btn-icon-only" title="Action timeline" aria-label="Action timeline">' + icon('fa-timeline') + '</a>' +
                (canEdit ? (st === ST.LISTED ? '<a href="' + view + '/edit" class="btn btn-light btn-sm btn-icon-only" title="Edit" aria-label="Edit">' + icon('fa-pen') + '</a>'
                    : '<span class="btn btn-sm btn-light btn-icon-only is-disabled" title="Locked — only Listed properties can be edited">' + icon('fa-lock') + '</span>') : '') +
                (!canSend ? '' : st === ST.LISTED ? '<button type="submit" form="send-' + r.property_id + '" class="btn btn-primary btn-sm btn-icon-only" title="Send for verification" data-loading-text="Sending…">' + icon('fa-paper-plane') + '<span class="sr-only">Send for verification</span></button>'
                    : st === ST.NOT_READY ? '<a href="' + view + '#resend" class="btn btn-warning btn-sm btn-icon-only" title="Re-send for verification" aria-label="Re-send for verification">' + icon('fa-rotate') + '</a>'
                        : st === ST.EXPIRED || due ? '<a href="' + view + '#resend" class="btn btn-warning btn-sm btn-icon-only" title="Re-verify (periodic)" aria-label="Re-verify">' + icon('fa-calendar-check') + '</a>' : '') +
                '</div></td></tr>';
        }).join('');

        const single = p.items.filter(function (r) { return canSend && r.status_id === ST.LISTED; }).map(function (r) {
            return '<form method="post" data-action="oic-send" id="send-' + r.property_id + '" hidden data-confirm="' +
                e('Send plot ' + r.plot_no + ' (' + r.scheme_name + (r.sector_id ? ' / ' + r.sector_name : '') + ') for verification?') + '" data-confirm-ok="Yes, send">' +
                '<input type="hidden" name="ids[]" value="' + r.property_id + '"></form>';
        }).join('');

        const colCount = canSend ? 11 : 10;
        const bulkBar = canSend ? '<div class="bulk-bar"><span class="bulk-count">' + icon('fa-square-check') + ' <strong data-bulk-count="ids[]">0</strong> selected</span>' +
            '<input type="text" name="reason" class="form-control form-control-sm bulk-reason" maxlength="255" placeholder="Re-verification reason (required for Not Ready, optional for Validity Expired)" aria-label="Re-verification reason" data-rules="max:255|text" data-label="Re-verification reason" data-bulk-reason>' +
            '<button type="submit" class="btn btn-primary btn-sm" data-bulk-requires="ids[]" disabled data-loading-text="Sending…">' + icon('fa-paper-plane') + ' <span>Send selected for verification</span></button></div>' : '';

        const myPendingTotal = P.propertyRows().filter(function (pr) { return Boolean(pr.my_pending_task_id); }).length;
        const myPendingAlert = myPendingTotal > 0 ? (
            '<div class="alert alert-info mb-3" style="display:flex; align-items:center; justify-content:space-between; gap:16px;">' +
            '<div style="display:flex; align-items:center; gap:12px;">' +
            '<span style="font-size:20px; color:var(--primary);">' + icon('fa-clipboard-check') + '</span>' +
            '<div><strong style="font-size:14px;">Action Required: You have ' + myPendingTotal + ' property verification ' + (myPendingTotal === 1 ? 'task' : 'tasks') + ' assigned to you.</strong>' +
            '<div class="text-muted" style="font-size:12px; margin-top:2px;">You can update status and submit your description directly using the <strong>Update Status</strong> button on any assigned plot.</div></div></div>' +
            (q.status !== 'MY_PENDING' ? '<a href="' + P.BASE + base + '?status=MY_PENDING" class="btn btn-primary btn-sm">' + icon('fa-filter') + ' <span>Filter My Pending (' + myPendingTotal + ')</span></a>' : '<a href="' + P.BASE + base + '" class="btn btn-light btn-sm">' + icon('fa-xmark') + ' <span>Clear Filter</span></a>') +
            '</div>'
        ) : '';

        return {
            title: 'Property Register',
            html: (crumbs ? crumbs('Property Register') : '') + P.pageHeader(icon('fa-building') + ' Property Register',
                'Comprehensive property register, verification workflow progress, and auction readiness.',
                canCreate ? '<a href="' + P.BASE + base + '/create" class="btn btn-primary">' + icon('fa-plus') + ' <span>Add Property</span></a>' : '') +
                myPendingAlert +
                '<div class="card">' + P.list_toolbar(p, 'Search plot no., scheme, sector, plot type…', P.propertyFilters(q, { REVERIFY_DUE: '⚠ Re-verification due' })) +
                '<form method="post" data-action="oic-send" id="bulk-send-form" data-confirm="Send the selected properties for verification? A new round will be created for each." data-confirm-ok="Yes, send" data-bulk-form data-validate novalidate>' +
                bulkBar +
                '<div class="table-wrap"><table class="table table-stack"><thead><tr>' +
                (canSend ? '<th class="cell-check"><input type="checkbox" data-check-all="ids[]" aria-label="Select all sendable properties"></th>' : '') +
                '<th class="cell-no">S.No</th>' + P.th_sort(p, 'plot', 'Plot no.') + P.th_sort(p, 'scheme', 'Scheme') + P.th_sort(p, 'sector', 'Sector') +
                P.th_sort(p, 'type', 'Plot type') + P.th_sort(p, 'area', 'Area') + '<th class="cell-vf">Verification</th>' +
                P.th_sort(p, 'status', 'Status') + '<th class="text-right">Actions</th></tr></thead><tbody>' +
                (rows || P.empty_state(colCount, p.hasFilters(), 'No properties found')) +
                '</tbody></table></div></form>' + single + P.pagination(p) + '</div>'
        };
    }

    /* Edit one property (only while Listed). New properties come from the Add Property grid below. */
    function propertyForm(prop, base, crumbs) {
        const v = function (k, d) { return prop[k] == null ? (d || '') : prop[k]; };
        const sc = DB.find('schemes', prop.scheme_id);
        const optional = ' <span class="pb-opt">(optional)</span>';
        const sectors = DB.Scheme.sectors(prop.scheme_id, true);
        let html = (crumbs ? crumbs('Property Register', 'Edit') : '') + P.pageHeader(icon('fa-building-circle-check') + ' Edit Property', e('Plot ' + prop.plot_no + ' · ' + sc.scheme_name + ' · ' + P.statusName(prop.status_id)),
            '<a href="' + P.BASE + base + '" class="btn btn-light">' + icon('fa-arrow-left') + ' <span>Back to list</span></a>');
        html += '<form method="post" data-action="property-save" novalidate data-validate data-property-form><input type="hidden" name="id" value="' + prop.property_id + '">' +
            '<input type="hidden" name="back" value="' + e(base) + '">' +
            '<div class="card"><div class="card-header"><h3 class="card-title">' + icon('fa-location-dot') + ' Location</h3></div><div class="card-body"><div class="form-grid">' +
            '<div class="form-group"><label class="form-label" for="scheme_id"><span class="step-no">1</span> Scheme</label>' +
            '<input type="text" class="form-control" value="' + e(sc.scheme_name) + '" readonly id="scheme_id" data-scheme-label="' + e(sc.scheme_name) + '">' +
            '<div class="form-hint">Scheme cannot be changed after creation.</div><div class="invalid-feedback"></div></div>' +
            '<div class="form-group"><label class="form-label" for="sector_id"><span class="step-no">2</span> Sector' + optional + '</label>' +
            '<select id="sector_id" name="sector_id" class="form-select"' + (sectors.length ? '' : ' disabled') + '><option value="">No sector</option>' + sectors.map(function (s) {
                return '<option value="' + s.sector_id + '"' + (s.sector_id === prop.sector_id ? ' selected' : '') + '>' + e(s.sector_name) + '</option>';
            }).join('') + '</select><div class="invalid-feedback"></div></div></div>' +
            '<div class="form-group confirm-box" data-confirm-box><p class="confirm-question"><span class="step-no">3</span> Should this property stay in Scheme <strong data-confirm-scheme>[—]</strong>, Sector <strong data-confirm-sector>[—]</strong>?</p>' +
            '<div class="radio-row"><label class="check"><input type="radio" name="confirm_location" value="YES" data-rules="required" data-label="the confirmation (Yes / No)"> <span><strong>Yes</strong>, that is correct</span></label>' +
            '<label class="check"><input type="radio" name="confirm_location" value="NO"> <span><strong>No</strong>, I need to change it</span></label></div><div class="invalid-feedback"></div>' +
            '<p class="form-hint" data-confirm-no hidden>' + icon('fa-circle-info') + ' Please change the sector above. Save stays disabled until you answer <strong>Yes</strong>.</p></div></div></div>' +
            '<div class="card"><div class="card-header"><h3 class="card-title">' + icon('fa-ruler-combined') + ' Plot details</h3></div><div class="card-body"><div class="form-grid">' +
            '<div class="form-group"><label class="form-label" for="plot_no">Plot no. <span class="required">*</span></label><input type="text" id="plot_no" name="plot_no" class="form-control" maxlength="30" value="' + e(v('plot_no')) + '" placeholder="e.g. 112-A" data-uppercase data-rules="required|plotno" data-label="Plot no."><div class="invalid-feedback"></div></div>' +
            '<div class="form-group"><label class="form-label" for="plot_type_id">Plot type <span class="required">*</span></label><select id="plot_type_id" name="plot_type_id" class="form-select" data-rules="required" data-label="Plot type">' + L.options(L.activeMap('plot_types', 'plot_type_id', 'name', v('plot_type_id')), v('plot_type_id'), 'Select plot type') + '</select><div class="invalid-feedback"></div></div>' +
            P.pdfField({ name: 'site_plan', label: 'Site plan (PDF)', current: P.propertyDoc(prop.property_id, 'SITE_PLAN') }) +
            '<div class="form-group span-2"><label class="form-label">Area' + optional + '</label>' +
            '<div class="dim-row">' +
            '<div class="form-group"><label class="form-label text-muted" for="area_length">Length</label><input type="text" id="area_length" class="form-control" inputmode="decimal" maxlength="10" placeholder="e.g. 30.00" data-decimals="2" data-rules="decimal:2|positive" data-label="Length" autocomplete="off"><div class="invalid-feedback"></div></div>' +
            '<span class="dim-x" aria-hidden="true">×</span>' +
            '<div class="form-group"><label class="form-label text-muted" for="area_width">Width</label><input type="text" id="area_width" class="form-control" inputmode="decimal" maxlength="10" placeholder="e.g. 40.50" data-decimals="2" data-rules="decimal:2|positive" data-label="Width" autocomplete="off"><div class="invalid-feedback"></div></div>' +
            '<div class="form-group"><label class="form-label text-muted" for="area_value">Calculated Area</label><div class="pb-area"><input type="text" id="area_value" name="area_value" class="form-control" readonly value="' + e(v('area_value')) + '" placeholder="Auto (L × W)" data-decimals="2" data-rules="decimal:2|positive" data-label="Area" data-area-value tabindex="-1" style="cursor: not-allowed;">' +
            '<select id="unit_id" name="unit_id" class="form-select" aria-label="Area unit" data-area-unit>' + L.options(L.activeMap('units', 'unit_id', 'name', v('unit_id')), v('unit_id', L.BASE_UNIT)) + '</select></div>' +
            '<div class="form-hint" data-area-preview></div><div class="invalid-feedback"></div></div></div>' +
            '<div class="form-hint" data-area-calc-hint>Enter Length and Width — Area is auto-calculated and non-editable.</div></div>' +
            '<div class="form-group"><span class="form-label">Corner plot</span><label class="check"><input type="checkbox" name="is_corner" value="1"' + (prop.is_corner ? ' checked' : '') + '> <span>This is a <strong>corner</strong> plot</span></label></div>' +
            '<div class="form-group span-2"><label class="form-label" for="remarks">Description' + optional + '</label><textarea id="remarks" name="remarks" class="form-control" maxlength="500" rows="2" placeholder="Optional notes about this plot" data-rules="max:500|text" data-label="Description">' + e(v('remarks')) + '</textarea><div class="invalid-feedback"></div></div>' +
            '</div></div></div>' +

            '<div class="form-actions"><button type="submit" class="btn btn-primary btn-lg" data-loading-text="Saving…" data-requires-yes>' + icon('fa-floppy-disk') + ' <span>Update Property</span></button>' +
            '<a href="' + P.BASE + base + '" class="btn btn-light btn-lg">' + icon('fa-xmark') + ' <span>Cancel</span></a></div></form>';
        return { title: 'Edit Property', html: html };
    }
    /* Add Property: details once → plot numbers (ranges, or one by one with prefix / postfix) → grid to fix corners → save all */
    function propertyBulkForm(base, crumbs) {
        const schemes = DB.Scheme.eligible();
        let html = (crumbs ? crumbs('Property Register', 'Add') : '') + P.pageHeader(icon('fa-building-circle-check') + ' Add Property',
            'Choose the scheme and plot details, enter the plot numbers, then check the grid and save all properties at once.',
            '<a href="' + P.BASE + base + '" class="btn btn-light">' + icon('fa-arrow-left') + ' <span>Back to list</span></a>');
        if (!schemes.length) {
            const schemeLink = base.indexOf('/admin/') === 0 ? '/admin/masters/schemes/create' : '/data-entry/schemes/create';
            return {
                title: 'Add Property',
                html: html + '<div class="card"><div class="card-body"><div class="empty-state">' + icon('fa-map-location-dot', 'fa-bounce empty-icon') +
                    '<p class="empty-title">No active scheme</p><p class="text-muted mb-3">Create or activate a scheme first; its properties are added here.</p>' +
                    (Auth.can('scheme.create') ? '<a href="' + P.BASE + schemeLink + '" class="btn btn-primary">' + icon('fa-plus') + ' <span>Create Scheme</span></a>' : '') + '</div></div></div>'
            };
        }
        const optional = ' <span class="pb-opt">(optional)</span>';
        html += '<form method="post" data-action="property-save" novalidate data-validate data-property-bulk data-confirm="Save these properties?" data-confirm-title="Save properties" data-confirm-ok="Yes, save all">' +
            '<input type="hidden" name="plots" value="[]" data-pb-plots><input type="hidden" name="back" value="' + e(base) + '">' +
            '<div class="card"><div class="card-header"><h3 class="card-title">' + icon('fa-location-dot') + ' Property details</h3></div><div class="card-body"><div class="form-grid">' +
            '<div class="form-group"><label class="form-label" for="scheme_id"><span class="step-no">1</span> Scheme <span class="required">*</span></label>' +
            '<select id="scheme_id" name="scheme_id" class="form-select" data-rules="required" data-label="Scheme">' +
            '<option value="">Select scheme</option>' + schemes.map(function (s) { return '<option value="' + s.scheme_id + '">' + e(s.scheme_name) + '</option>'; }).join('') +
            '</select><div class="invalid-feedback"></div></div>' +
            '<div class="form-group"><label class="form-label" for="sector_id"><span class="step-no">2</span> Sector' + optional + '</label>' +
            '<select id="sector_id" name="sector_id" class="form-select" disabled><option value="">Select scheme first</option></select>' +
            '<div class="invalid-feedback"></div></div>' +
            '<div class="form-group"><label class="form-label" for="pb_type"><span class="step-no">3</span> Plot type <span class="required">*</span></label>' +
            '<select id="pb_type" name="plot_type_id" class="form-select" data-rules="required" data-label="Plot type" data-pb-default="plot_type_id">' + L.options(L.activeMap('plot_types', 'plot_type_id', 'name'), '', 'Select plot type') + '</select><div class="invalid-feedback"></div></div>' +
            P.pdfField({ name: 'site_plan', label: 'Site plan (PDF)' }) +
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
            '</div>' +
            '</div></div></div>' +
            '<div class="card"><div class="card-header"><h3 class="card-title">' + icon('fa-list-ol') + ' Plot numbers</h3></div><div class="card-body">' +
            '<div class="form-group pb-question"><p class="confirm-question"><span class="step-no">5</span> Are the plot numbers in a running range?</p>' +
            '<div class="radio-row"><label class="check"><input type="radio" name="pb_range" value="YES" checked> <span><strong>Yes</strong>, from – to (e.g. 1 to 50)</span></label>' +
            '<label class="check"><input type="radio" name="pb_range" value="NO"> <span><strong>No</strong>, I will enter each plot no.</span></label></div></div>' +
            '<div data-pb-mode="YES"><div class="pb-rows" data-pb-rows></div>' +
            '<p class="form-hint">Leading zeros are kept: 001 to 050 gives 001, 002 … 050.</p></div>' +
            '<div data-pb-mode="NO" hidden><div class="pb-rows" data-pb-rows></div>' +
            '<p class="form-hint">Prefix and postfix are joined exactly as typed: <span class="mono">A-</span> + <span class="mono">12</span> + <span class="mono">/B</span> → <span class="mono">A-12/B</span>.</p></div>' +
            '<div class="pb-row-actions"><button type="button" class="btn btn-light btn-sm" data-pb-add>' + icon('fa-plus') + ' <span>Add more</span></button>' +
            '<button type="button" class="btn btn-primary" data-pb-generate>' + icon('fa-table-cells') + ' <span>Generate Property Grid</span></button></div>' +
            '</div></div>' +
            '<div class="card pb-grid-card" data-pb-grid-card hidden><div class="card-header"><h3 class="card-title">' + icon('fa-table-cells') + ' Property Grid</h3>' +
            '<span class="text-muted pb-grid-tip">Choose Edit to change a plot; use Mark Corner for corner plots</span></div>' +
            '<div class="pb-legend"><span><i class="pb-swatch"></i> Normal</span><span><i class="pb-swatch is-corner"></i> Corner</span></div>' +
            '<div class="pb-grid" data-pb-grid></div><div class="pb-grid-foot" data-pb-count></div></div>' +
            '<div class="form-actions pb-actions"><a href="' + P.BASE + base + '" class="btn btn-light btn-lg">' + icon('fa-xmark') + ' <span>Cancel</span></a>' +
            '<button type="submit" class="btn btn-primary btn-lg" data-loading-text="Saving…" data-pb-save disabled>' + icon('fa-check-double') + ' <span>Review &amp; Save Properties</span></button></div>' +
            '</form>';
        return { title: 'Add Property', html: html };
    }
    /* ================= Unified Property Details ================= */
    function propertyDetail(ctx, base, crumbs) {
        const prop = P.propertyRows().find(function (r) { return r.property_id === ctx.params.id; });
        if (!prop) { return P.notFound('Property not found.'); }
        const rounds = DB.where('rounds', { property_id: prop.property_id }).sort(function (a, b) { return b.round_no - a.round_no; }).map(function (r) {
            const tasks = DB.where('tasks', { round_id: r.round_id }).map(function (t) {
                const role = DB.find('roles', t.role_id);
                const ru = t.responded_by ? DB.find('users', t.responded_by) : null;
                const assignees = DB.where('task_assignees', { task_id: t.task_id }).map(function (a) {
                    const u = DB.find('users', a.user_id); return u ? u.name + (u.mobile ? ' (' + u.mobile + ')' : '') : '';
                }).filter(Boolean).sort().join(', ');
                return Object.assign({}, t, { role_name: role ? role.role_name : '', sort_order: role ? role.sort_order : 0, is_mandatory: role ? role.is_mandatory : 0, responded_by_name: ru ? ru.name : null, assignees: assignees });
            }).sort(function (a, b) { return a.sort_order - b.sort_order; });
            const iu = DB.find('users', r.initiated_by);
            return Object.assign({}, r, { tasks: tasks, initiated_by_name: iu ? iu.name : '' });
        });
        const current = rounds[0] || null;
        const ST = P.ST, st = prop.status_id;
        const canSend = Auth.can('verification.send');
        const canEdit = Auth.can('property.edit');
        const meUser = Auth.user();
        const meId = meUser ? meUser.user_id : null;
        let myPendingTask = null;
        if (meId && current && current.round_status === 'OPEN') {
            myPendingTask = current.tasks.find(function (t) {
                return t.task_status === 'PENDING' && DB.exists('task_assignees', { task_id: t.task_id, user_id: meId });
            });
        }

        let headerActions = '<a href="' + P.BASE + base + '" class="btn btn-light">' + icon('fa-arrow-left') + ' <span>Back to list</span></a>';
        if (myPendingTask) {
            headerActions += '<button type="button" class="btn btn-primary" data-update-status="' + myPendingTask.task_id + '" data-plot-no="' + e(prop.plot_no) + '" data-scheme-name="' + e(prop.scheme_name) + '" data-sector-name="' + e(prop.sector_name || '') + '" data-role-name="' + e(myPendingTask.role_name) + '" data-status-name="' + e(P.statusName(prop.status_id)) + '" data-current-status-id="' + prop.status_id + '" data-property-id="' + prop.property_id + '">' + icon('fa-pen-to-square') + ' <span>Update Status</span></button>';
        }
        if (st === ST.LISTED) {
            if (canEdit) {
                headerActions += '<a href="' + P.BASE + base + '/' + prop.property_id + '/edit" class="btn btn-primary">' + icon('fa-pen') + ' <span>Edit</span></a>';
            }
            if (canSend) {
                headerActions += '<form method="post" data-action="oic-send" data-confirm="Send this property for verification?" data-confirm-ok="Yes, send">' +
                    '<input type="hidden" name="ids[]" value="' + prop.property_id + '"><button type="submit" class="btn btn-success" data-loading-text="Sending…">' + icon('fa-paper-plane') + ' <span>Send for verification</span></button></form>';
            }
        }

        let html = (crumbs ? crumbs('Property Register', 'Plot ' + prop.plot_no) : '') +
            P.pageHeader(icon('fa-building') + ' Plot ' + e(prop.plot_no) + ' ' + P.status_badge(st),
                e(prop.scheme_name + (prop.sector_id ? ' · ' + prop.sector_name : '')),
                headerActions);

        if (myPendingTask) {
            html += '<div class="alert alert-info mb-3" style="display:flex; align-items:center; justify-content:space-between; gap:16px;">' +
                '<div>' + icon('fa-clipboard-check') + ' <span><strong>Verification Task Assigned to You:</strong> You have a pending verification task (' + e(myPendingTask.role_name) + ') for this property.</span></div>' +
                '<button type="button" class="btn btn-primary btn-sm" data-update-status="' + myPendingTask.task_id + '" data-plot-no="' + e(prop.plot_no) + '" data-scheme-name="' + e(prop.scheme_name) + '" data-sector-name="' + e(prop.sector_name || '') + '" data-role-name="' + e(myPendingTask.role_name) + '" data-status-name="' + e(P.statusName(prop.status_id)) + '" data-current-status-id="' + prop.status_id + '" data-property-id="' + prop.property_id + '">' + icon('fa-pen-to-square') + ' <span>Verify &amp; Update Status</span></button>' +
                '</div>';
        }

        const due = DB.Verification.isDue(prop);
        const till = prop.valid_till;
        if (due && current) {
            html += '<div class="alert alert-warning">' + icon('fa-calendar-day', 'fa-shake') + '<span><strong>Re-verification due.</strong> Approved in round R' + current.round_no +
                ', but the validity ends ' + e(P.fmt_date(till)) + ' (' + P.dueText(till) + '). Auction it before that date, or <a href="#resend">start re-verification</a> now.</span></div>';
        } else if (st === ST.AUCTION_READY && current) {
            html += '<div class="alert alert-success">' + icon('fa-circle-check') + '<span><strong>Ready for Auction.</strong> All roles approved in round R' + current.round_no + '. Valid till ' + e(P.fmt_date(till)) + ' (' + P.dueText(till) + ').</span></div>';
        } else if (st === ST.EXPIRED) {
            html += '<div class="alert alert-error">' + icon('fa-calendar-xmark') + '<span><strong>Validity expired on ' + e(P.fmt_date(till)) + '.</strong> The approval of round R' + (current ? current.round_no : '—') +
                ' can no longer be used for an auction. <a href="#resend">Send it for re-verification</a> (new round).</span></div>';
        } else if (st === ST.NOT_READY && current) {
            const rej = current.tasks.filter(function (t) { return t.task_status === 'REJECTED' || t.task_status === 'NO_RESPONSE'; });
            html += '<div class="alert alert-error">' + icon('fa-circle-xmark', 'fa-beat') + '<span><strong>Not Ready — round R' + current.round_no + ' rejected.</strong> Reasons:<ul class="reason-list">' +
                rej.map(function (t) { return '<li><strong>' + e(t.role_name) + ':</strong> ' + e(t.remarks || 'No response') + ' <small>(' + e(t.responded_by_name || '') + ', ' + e(P.fmt_datetime(t.responded_at)) + ')</small></li>'; }).join('') +
                '</ul></span></div>';
        }

        html += '<div class="card mb-3"><div class="card-header"><h3 class="card-title">' + icon('fa-building') + ' Property details</h3></div><div class="card-body"><dl class="detail-list">' +
            '<dt>Scheme</dt><dd>' + e(prop.scheme_name) + '</dd>' +
            (prop.area && prop.area !== '—' ? '<dt>Area</dt><dd>' + e(prop.area) + '</dd>' : '') +
            '<dt>Sector</dt><dd>' + e(prop.sector_name) + '</dd><dt>Plot no.</dt><dd>' + e(prop.plot_no) + '</dd>' +
            '<dt>Plot type</dt><dd>' + e(prop.type_name) + '</dd>' +
            '<dt>Corner plot</dt><dd>' + (prop.is_corner ? '<span class="badge badge-warning">Corner</span>' : 'No') + '</dd>' +
            '<dt>Plot area</dt><dd>' + e(L.area(prop.area_value, prop.unit_id, prop.area_sqm)) + '</dd>' +
            P.propertyPriceRows(prop) +
            '<dt>Created</dt><dd>' + e(P.fmt_datetime(prop.created_at) + ' · ' + prop.created_by_name) + '</dd>' +
            (prop.remarks ? '<dt>Description</dt><dd>' + e(prop.remarks) + '</dd>' : '') + '</dl></div></div>';

        if (st !== ST.LISTED && canSend && DB.Verification.canSend(prop)) {
            const need = st === ST.NOT_READY;
            const nextNo = (current ? current.round_no : 0) + 1;
            html += '<div class="card" id="resend"><div class="card-header"><h3 class="card-title">' + icon('fa-rotate') + ' ' + (need ? 'Send again for verification' : 'Periodic re-verification') + ' (new round R' + nextNo + ')</h3>' +
                (need ? '' : '<span class="text-muted small">Every mapped section verifies the property again; it stays Under Verification until the round closes.</span>') + '</div><div class="card-body">' +
                '<form method="post" data-action="oic-send" id="resend-form" novalidate data-validate data-confirm="Start a new verification round? The old round stays in history." data-confirm-ok="Yes, ' + (need ? 're-send' : 're-verify') + '" data-confirm-variant="warning">' +
                '<input type="hidden" name="ids[]" value="' + prop.property_id + '"><div class="form-group"><label class="form-label" for="reason">Reason for re-verification ' + (need ? '<span class="required">*</span>' : '<small class="text-muted">(optional)</small>') + '</label>' +
                '<input type="text" id="reason" name="reason" class="form-control" maxlength="255" placeholder="' +
                e(need ? 'e.g. Encroachment removed, record corrected' : 'Default: Periodic re-verification — validity ' + (st === ST.EXPIRED ? 'expired' : 'ends') + ' on ' + P.fmt_date(till)) +
                '" data-rules="' + (need ? 'required|min:5|max:255|text' : 'max:255|text') + '" data-label="Reason"><div class="invalid-feedback"></div></div>' +
                '<button type="submit" class="btn btn-warning" data-loading-text="Sending…">' + icon('fa-paper-plane') + ' <span>' + (need ? 'Re-send for verification' : 'Start re-verification') + '</span></button></form></div></div>';
        }

        if (!rounds.length) {
            html += '<div class="card"><div class="card-body"><div class="empty-state">' + icon('fa-hourglass-start', 'empty-icon') +
                '<p class="empty-title">Not sent for verification yet</p><p class="text-muted">Use “Send for verification” to create round R1.</p></div></div></div>';
        }
        rounds.forEach(function (round, i) {
            const isOpen = round.round_status === 'OPEN';
            const approved = round.tasks.filter(function (t) { return t.task_status === 'APPROVED'; }).length;
            html += '<details class="card round-card"' + (i === 0 ? ' open' : '') + '><summary class="card-header"><h3 class="card-title">' + icon('fa-layer-group') + ' Round R' + round.round_no + ' ' +
                P.status_badge(round.round_status) + ' ' + (round.result ? P.status_badge(round.result) : '') + '</h3>' +
                '<span class="text-muted small">' + approved + '/' + round.tasks.length + ' approved · Sent ' + e(P.fmt_datetime(round.initiated_at)) + ' by ' + e(round.initiated_by_name) +
                (round.closed_at ? ' · Closed ' + e(P.fmt_datetime(round.closed_at)) : ' · Due ' + e(P.fmt_date(round.due_date))) + '</span></summary>' +
                (round.resend_reason ? '<p class="round-note">' + icon('fa-quote-left') + ' Re-verification reason: ' + e(round.resend_reason) + '</p>' : '') +
                '<div class="table-wrap"><table class="table table-stack"><thead><tr><th>Role</th><th>Assigned user(s)</th><th>Status</th><th>Remark</th><th>Attachments</th><th>Date</th></tr></thead><tbody>' +
                round.tasks.map(function (t) {
                    const pendingClosed = t.task_status === 'PENDING' && !isOpen;
                    return '<tr class="' + (t.task_status === 'APPROVED' ? 'row-success' : (t.task_status === 'REJECTED' ? 'row-danger' : '')) + '">' +
                        '<td data-label="Role"><strong>' + e(t.role_name) + '</strong>' + (t.is_mandatory ? '' : ' <span class="badge badge-muted">optional</span>') + '</td>' +
                        '<td data-label="Assigned">' + e(t.assignees || '—') + '</td><td data-label="Status">' +
                        (pendingClosed ? '<span class="badge badge-muted">' + icon('fa-minus') + ' Not required (round closed)</span>' : P.status_badge(t.task_status)) + (isOpen && t.task_status === 'PENDING' && meId && DB.exists('task_assignees', { task_id: t.task_id, user_id: meId }) ? ' <button type="button" class="btn btn-primary btn-xs ml-2" data-update-status="' + t.task_id + '" data-plot-no="' + e(prop.plot_no) + '" data-scheme-name="' + e(prop.scheme_name) + '" data-sector-name="' + e(prop.sector_name || '') + '" data-role-name="' + e(t.role_name) + '" data-status-name="' + e(P.statusName(prop.status_id)) + '" data-current-status-id="' + prop.status_id + '" data-property-id="' + prop.property_id + '" title="Update Status &amp; Description">' + icon('fa-pen-to-square') + ' <span>Update Status</span></button>' : '') + '</td>' +
                        '<td data-label="Remark">' + e(t.remarks || '—') +
                        (t.section_check ? '<span class="cell-sub">' + icon('fa-file-signature') + ' ' + e(P.sectionCheckText(t.section_check)) + '</span>' : '') + '</td><td data-label="Attachments">' + (P.attachmentChips(P.attachmentsFor({ task_id: t.task_id })) || '—') + '</td><td data-label="Date">' +
                        (t.responded_at ? e(P.fmt_datetime(t.responded_at)) + '<span class="cell-sub">' + e(t.responded_by_name || '') + '</span>' : '<span class="text-muted">SLA ' + e(P.fmt_date(t.sla_due_date)) + '</span>') + '</td></tr>';
                }).join('') + '</tbody></table></div>' +
                (round.result === 'NOT_READY' && round.result_note ? '<div class="card-footer"><strong>Result:</strong> ' + e(round.result_note).replace(/\n/g, '<br>') + '</div>'
                    : round.result === 'READY' ? '<div class="card-footer"><strong>Result:</strong> All roles approved · valid ' + e(P.fmt_date(round.valid_from)) + ' – ' + e(P.fmt_date(round.valid_till)) + '</div>' : '') +
                '</details>';
        });
        html += P.propertyTimeline(prop.property_id);
        return { title: 'Property ' + prop.plot_no, html: html };
    }

    /** Property list + add + view + edit under any base path. Permission-checked. */
    P.registerPropertyScreens = function (base, panel, crumbs) {
        P.route(base, panel, function (ctx) { return propertyListPage(ctx, base, crumbs); }, ['property.view', 'verification.view']);
        P.route(base + '/create', panel, function () { return propertyBulkForm(base, crumbs); }, 'property.create');
        P.route(base + '/{id}', panel, function (ctx) { return propertyDetail(ctx, base, crumbs); }, ['property.view', 'verification.view']);
        P.route(base + '/{id}/edit', panel, function (ctx) {
            const p = DB.find('properties', ctx.params.id);
            if (!p || !p.is_active) { return P.notFound('Property not found.'); }
            if (p.status_id !== P.ST.LISTED) {
                P.flash('warning', 'Only properties in "Listed" status can be edited. This one is ' + P.statusName(p.status_id) + '.');
                P.go(base);
                return false;
            }
            return propertyForm(p, base, crumbs);
        }, 'property.edit');
    };
    P.registerPropertyScreens('/data-entry/properties', PANEL, null);

})(window);
