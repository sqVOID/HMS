/**
 * hms_error_capture.js
 * Global HMS error capture — sends ALL frontend errors to SystemLog.
 * Captures:
 *   • Unhandled JS exceptions (window.onerror)
 *   • Unhandled Promise rejections (window.onunhandledrejection)
 *   • Fetch errors & non-2xx HTTP responses
 *   • JSON.parse failures (patched)
 *   • console.error / console.warn calls
 *
 * Include this ONCE in your base layout (role-based-menu.js injects it automatically).
 */
(function () {
    'use strict';

    const ENDPOINT = 'log_client_error.php';
    const MAX_MSG  = 800;

    // Deduplicate: don't send the same message twice in 5 s
    const _sent = new Map();
    function _isDupe(key) {
        const now = Date.now();
        if (_sent.has(key) && now - _sent.get(key) < 5000) return true;
        _sent.set(key, now);
        return false;
    }

    function _send(payload) {
        const key = (payload.error_type || '') + '|' + (payload.message || '').slice(0, 120);
        if (_isDupe(key)) return;

        payload.page = window.location.href;

        // Use sendBeacon if available (works even on page unload)
        const body = JSON.stringify(payload);
        if (navigator.sendBeacon) {
            const blob = new Blob([body], { type: 'application/json' });
            navigator.sendBeacon(ENDPOINT, blob);
        } else {
            fetch(ENDPOINT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: body,
                keepalive: true
            }).catch(() => {}); // swallow — don't cause infinite loop
        }
    }

    // ── 1. Unhandled JS errors ────────────────────────────────────
    window.onerror = function (message, source, lineno, colno, error) {
        _send({
            error_type : 'JS_ERROR',
            category   : 'client',
            message    : String(message).slice(0, MAX_MSG),
            source     : source || '',
            lineno     : lineno || 0,
            colno      : colno  || 0,
            stack      : error && error.stack ? error.stack.slice(0, 1000) : ''
        });
        return false; // don't suppress the browser console output
    };

    // ── 2. Unhandled Promise rejections ──────────────────────────
    window.addEventListener('unhandledrejection', function (event) {
        const reason = event.reason;
        let msg   = '';
        let stack = '';

        if (reason instanceof Error) {
            msg   = reason.message;
            stack = reason.stack || '';
        } else if (typeof reason === 'string') {
            msg = reason;
        } else {
            try { msg = JSON.stringify(reason); } catch (_) { msg = String(reason); }
        }

        _send({
            error_type : 'PROMISE_REJECTION',
            category   : 'promise',
            message    : msg.slice(0, MAX_MSG),
            stack      : stack.slice(0, 1000)
        });
    });

    // ── 3. Patch fetch() to capture HTTP errors & JSON errors ────
    const _origFetch = window.fetch;
    window.fetch = async function (...args) {
        let url = '';
        try {
            url = typeof args[0] === 'string' ? args[0]
                : (args[0] instanceof Request ? args[0].url : String(args[0]));
        } catch (_) {}

        let response;
        try {
            response = await _origFetch.apply(this, args);
        } catch (networkErr) {
            _send({
                error_type : 'FETCH_ERROR',
                category   : 'fetch',
                message    : ('Network error fetching ' + url + ': ' + networkErr.message).slice(0, MAX_MSG),
                source     : url,
                stack      : networkErr.stack ? networkErr.stack.slice(0, 1000) : ''
            });
            throw networkErr;
        }

        // Clone so the original response body is still readable by the caller
        const clone = response.clone();

        // Non-2xx responses
        if (!response.ok) {
            clone.text().then(body => {
                _send({
                    error_type : 'FETCH_HTTP_ERROR',
                    category   : 'fetch',
                    message    : ('HTTP ' + response.status + ' ' + response.statusText + ' — ' + url + ' → ' + body.slice(0, 300)).slice(0, MAX_MSG),
                    source     : url
                });
            }).catch(() => {});
        } else {
            // Peek at body: if it looks like JSON but fails to parse, capture that
            const ct = response.headers.get('content-type') || '';
            if (ct.includes('json')) {
                clone.text().then(body => {
                    try {
                        JSON.parse(body);
                    } catch (jsonErr) {
                        _send({
                            error_type : 'JSON_PARSE_ERROR',
                            category   : 'json',
                            message    : ('JSON parse error from ' + url + ': ' + jsonErr.message + ' — Raw: ' + body.slice(0, 200)).slice(0, MAX_MSG),
                            source     : url
                        });
                    }
                }).catch(() => {});
            }
        }

        return response;
    };

    // ── 4. Patch console.error & console.warn ────────────────────
    const _origConsoleError = console.error;
    console.error = function (...args) {
        _origConsoleError.apply(console, args);
        try {
            const msg = args.map(a => {
                if (a instanceof Error) return a.message + (a.stack ? '\n' + a.stack : '');
                try { return typeof a === 'object' ? JSON.stringify(a) : String(a); } catch (_) { return String(a); }
            }).join(' ');

            _send({
                error_type : 'CONSOLE_ERROR',
                category   : 'client',
                message    : msg.slice(0, MAX_MSG)
            });
        } catch (_) {}
    };

    const _origConsoleWarn = console.warn;
    console.warn = function (...args) {
        _origConsoleWarn.apply(console, args);
        try {
            const msg = args.map(a => {
                try { return typeof a === 'object' ? JSON.stringify(a) : String(a); } catch (_) { return String(a); }
            }).join(' ');

            _send({
                error_type : 'CONSOLE_WARN',
                category   : 'client',
                message    : msg.slice(0, MAX_MSG)
            });
        } catch (_) {}
    };

    console.log('[HMS] Error capture active — all errors will appear in SystemLog.php');

    /**
     * Global helper: call this inside any catch block to explicitly send
     * an error to SystemLog.php.
     */
    window.hmsLogError = function (context, error, category) {
        try {
            const msg = context + ': ' + (error && error.message ? error.message : String(error));
            _send({
                error_type : 'CAUGHT_ERROR',
                category   : category || 'client',
                message    : msg.slice(0, 800),
                stack      : error && error.stack ? error.stack.slice(0, 1000) : ''
            });
        } catch (_) {}
    };

    // ── 5. Real-time Session Monitor (forces immediate logout on all open devices/tabs) ──
    (function monitorActiveSession() {
        // Do not poll if already on Login page
        if (window.location.pathname.toLowerCase().includes('login')) return;

        setInterval(async () => {
            try {
                const res = await _origFetch('get_current_session.php?t=' + Date.now(), { cache: 'no-store' });
                if (!res.ok) return;
                const data = await res.json();
                if (!data.success || data.session_status === 'logged_out') {
                    // Session was killed by LOGOUT ALL ACCOUNTS! Bounce to login immediately!
                    window.location.href = 'Login.html?logout=forced_all';
                }
            } catch (_) {}
        }, 2000);
    })();
})();

