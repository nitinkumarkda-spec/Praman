/* =====================================================================
   PRAMAN — fetch() helpers. Same-origin only, CSRF header on every POST.
   Usage:
     const data = await Praman.ajax.get('/data-entry/schemes/5/sectors');
     const res  = await Praman.ajax.post('/oic/properties/send', {ids: [1,2]});
   Throws Praman.AjaxError {status, message, errors} on failure.
   ===================================================================== */
(function (window, document) {
    'use strict';

    const Praman = window.Praman = window.Praman || {};

    function meta(name) {
        const el = document.querySelector('meta[name="' + name + '"]');
        return el ? el.getAttribute('content') : '';
    }

    /** Build an application URL from a route path ("/oic/x" -> "/PARMAM/oic/x"). */
    function appUrl(path, params) {
        const base = (meta('base-url') || '/').replace(/\/+$/, '');
        let url = /^https?:\/\//i.test(path) ? path : base + '/' + String(path).replace(/^\/+/, '');
        if (params) {
            const qs = new URLSearchParams();
            Object.keys(params).forEach(function (k) {
                const v = params[k];
                if (Array.isArray(v)) { v.forEach(function (x) { qs.append(k + '[]', x); }); }
                else if (v !== undefined && v !== null && v !== '') { qs.append(k, v); }
            });
            const s = qs.toString();
            if (s) { url += (url.indexOf('?') === -1 ? '?' : '&') + s; }
        }
        return url;
    }

    class AjaxError extends Error {
        constructor(status, message, errors) {
            super(message || 'Request failed.');
            this.status = status;
            this.errors = errors || {};
        }
    }

    function toFormData(data) {
        if (data instanceof FormData) { return data; }
        const fd = new FormData();
        Object.keys(data || {}).forEach(function (k) {
            const v = data[k];
            if (Array.isArray(v)) { v.forEach(function (x) { fd.append(k + '[]', x); }); }
            else if (v !== undefined && v !== null) { fd.append(k, v); }
        });
        return fd;
    }

    async function request(method, path, data, params) {
        const opts = {
            method: method,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        };
        if (method === 'POST') {
            opts.headers['X-CSRF-Token'] = meta('csrf-token');
            opts.body = toFormData(data);
        }

        let response;
        try {
            response = await fetch(appUrl(path, params), opts);
        } catch (e) {
            throw new AjaxError(0, 'Network error. Please check your connection and try again.');
        }

        let body = null;
        const type = response.headers.get('Content-Type') || '';
        if (type.indexOf('application/json') !== -1) {
            try { body = await response.json(); } catch (e) { body = null; }
        }

        if (response.status === 401) {
            window.location.href = appUrl('/login');
            throw new AjaxError(401, 'Session expired.');
        }
        if (!response.ok || (body && body.ok === false)) {
            const msg = (body && body.message) || (response.status === 419
                ? 'Your session token expired. Please refresh the page.'
                : 'Something went wrong (HTTP ' + response.status + ').');
            throw new AjaxError(response.status, msg, body && body.errors);
        }
        return body || {};
    }

    Praman.url = appUrl;
    Praman.AjaxError = AjaxError;
    Praman.ajax = {
        get: function (path, params) { return request('GET', path, null, params); },
        post: function (path, data) { return request('POST', path, data); }
    };
})(window, document);
