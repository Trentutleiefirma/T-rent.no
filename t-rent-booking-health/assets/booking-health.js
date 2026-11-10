(function ($) {
    'use strict';
    var config = window.TRentBookingHealth;
    if (!$ || !config || !window.fetch || !window.AbortController || !window.URLSearchParams) return;

    var readActions = ['rnb_calculate_inventory_data', 'rnb_get_inventory_data'];
    var quoteAction = 'redq_request_for_a_quote';
    var tokens = null;
    var refreshing = null;
    var lastRefresh = 0;
    var resumed = false;
    var hiddenAt = 0;
    var ajaxUrl = new URL(config.ajaxUrl, location.href);
    var productUrl = new URL(config.productUrl, location.href);
    if (ajaxUrl.origin !== location.origin || productUrl.origin !== location.origin) return;

    // Parse only a JSON object produced by wp_localize_script. Never execute HTML.
    function readObject(doc, name) {
        var scripts = doc.querySelectorAll('script:not([src])');
        var pattern = new RegExp('\\bvar\\s+' + name + '\\s*=\\s*');
        for (var i = 0; i < scripts.length; i++) {
            var text = scripts[i].textContent, match = pattern.exec(text);
            if (!match) continue;
            var start = match.index + match[0].length;
            if (text[start] !== '{') continue;
            var depth = 0, quoted = false, escaped = false;
            for (var j = start; j < text.length; j++) {
                var c = text[j];
                if (quoted) {
                    if (escaped) escaped = false;
                    else if (c === '\\') escaped = true;
                    else if (c === '"') quoted = false;
                } else if (c === '"') quoted = true;
                else if (c === '{') depth++;
                else if (c === '}' && --depth === 0) return JSON.parse(text.slice(start, j + 1));
            }
        }
        throw new Error('Missing booking configuration');
    }

    function validToken(value) {
        return typeof value === 'string' && /^[a-zA-Z0-9_-]{6,128}$/.test(value);
    }

    function refresh() {
        if (refreshing) return refreshing;
        var id = Date.now().toString(36) + '-' + Math.random().toString(36).slice(2) + '-booking';
        var url = new URL(productUrl.href);
        url.searchParams.set('_trent_booking_refresh', id);
        var controller = new AbortController();
        var timer = setTimeout(function () { controller.abort(); }, 12000);
        refreshing = fetch(url.href, {credentials:'same-origin', cache:'no-store', signal:controller.signal, headers:{'Cache-Control':'no-cache'}})
            .then(function (response) {
                if (!response.ok || new URL(response.url).origin !== location.origin) throw new Error('Refresh failed');
                return response.text();
            })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var freshConfig = readObject(doc, 'TRentBookingHealth');
                // A cached copy or login page cannot supply a matching request ID.
                if (freshConfig.refreshId !== id || freshConfig.productUrl !== config.productUrl) throw new Error('Stale refresh response');
                var calendar = readObject(doc, 'CALENDAR_DATA');
                var quote = readObject(doc, 'RFQ_DATA');
                if (!validToken(calendar.nonce) || !validToken(quote.nonce)) throw new Error('Invalid refresh data');
                var linked = doc.querySelector('input[name="trent_linked_booking_nonce"]');
                var linkedValue = linked && validToken(linked.value) ? linked.value : null;
                if (document.querySelector('input[name="trent_linked_booking_nonce"]') && !linkedValue) throw new Error('Missing linked booking token');
                tokens = {calendar:calendar.nonce, quote:quote.nonce, linked:linkedValue};
                if (window.CALENDAR_DATA) window.CALENDAR_DATA.nonce = tokens.calendar;
                if (window.RFQ_DATA) window.RFQ_DATA.nonce = tokens.quote;
                if (tokens.linked) document.querySelectorAll('input[name="trent_linked_booking_nonce"]').forEach(function (node) { node.value = tokens.linked; });
                lastRefresh = Date.now();
                resumed = false;
                return tokens;
            })
            .finally(function () { clearTimeout(timer); refreshing = null; });
        return refreshing;
    }

    function needsRefresh() {
        var since = lastRefresh || Number(config.generatedAt) * 1000;
        return resumed || !since || Date.now() - since > config.maxAgeMs;
    }

    function withTokens(body, action) {
        if (!tokens) return body;
        var params = new URLSearchParams(body);
        params.set('nonce', action === quoteAction ? tokens.quote : tokens.calendar);
        if (tokens.linked) {
            Array.from(params.keys()).forEach(function (key) {
                if (key === 'trent_linked_booking_nonce' || /\[trent_linked_booking_nonce\]$/.test(key)) params.set(key, tokens.linked);
                // RnB serializes form_data as pairs of name/value fields.
                if (/\[name\]$/.test(key) && params.get(key) === 'trent_linked_booking_nonce') params.set(key.slice(0, -6) + '[value]', tokens.linked);
            });
        }
        return params.toString();
    }

    function nonceRejected(text) {
        try {
            var response = JSON.parse(text);
            var messages = Array.isArray(response.error) ? response.error : [response.message];
            return messages.some(function (message) { return typeof message === 'string' && /\bnonce\b/i.test(message); });
        } catch (_) { return false; }
    }

    // A jQuery transport preserves the original success/complete/deferred behavior.
    // Only public RnB requests on this product page are handled here.
    $.ajaxTransport('+json', function (options) {
        if (options.async === false || options.type.toUpperCase() !== 'POST' || typeof options.data !== 'string') return;
        var target;
        try { target = new URL(options.url, location.href); } catch (_) { return; }
        if (target.origin !== ajaxUrl.origin || target.pathname !== ajaxUrl.pathname) return;
        var action = new URLSearchParams(options.data).get('action');
        var readOnly = readActions.indexOf(action) !== -1;
        if (!readOnly && action !== quoteAction) return;
        var stopped = false, controller = new AbortController();
        return {
            send: function (headers, complete) {
                var finished = false;
                function finish(status, statusText, text, responseHeaders) {
                    if (stopped || finished) return;
                    finished = true;
                    complete(status, statusText, {text:text}, responseHeaders || '');
                }
                async function request() {
                    var sendHeaders = Object.assign({}, headers);
                    if (!Object.keys(sendHeaders).some(function (name) { return name.toLowerCase() === 'content-type'; })) sendHeaders['Content-Type'] = 'application/x-www-form-urlencoded; charset=UTF-8';
                    sendHeaders['X-Requested-With'] = 'XMLHttpRequest';
                    var response = await fetch(target.href, {method:'POST', headers:sendHeaders, body:withTokens(options.data, action), credentials:'same-origin', cache:'no-store', signal:controller.signal});
                    var text = await response.text();
                    var responseHeaders = '';
                    response.headers.forEach(function (value, name) { responseHeaders += name + ': ' + value + '\r\n'; });
                    return {status:response.status, statusText:response.statusText, text:text, headers:responseHeaders};
                }
                (async function () {
                    if (needsRefresh() || action === quoteAction) {
                        try { await refresh(); }
                        catch (error) {
                            if (!readOnly) throw error;
                            // Keep the ordinary request available if a refresh GET fails.
                        }
                    }
                    if (stopped) return;
                    var response = await request();
                    if (readOnly && response.status === 200 && nonceRejected(response.text)) {
                        try {
                            await refresh();
                            if (stopped) return;
                            response = await request(); // At most one retry; never replay a quote submission.
                        } catch (_) { /* Deliver the original rejection if recovery is unavailable. */ }
                    }
                    finish(response.status, response.statusText, response.text, response.headers);
                })().catch(function () {
                    // RnB's existing UI consumes these shapes and stops its spinner.
                    var message = 'Kunne ikke oppdatere bestillingsskjemaet. Last siden på nytt og prøv igjen.';
                    finish(200, 'OK', JSON.stringify(readOnly ? {error:[message]} : {success:false, message:message}));
                });
            },
            abort: function () { stopped = true; controller.abort(); }
        };
    });

    window.addEventListener('pageshow', function (event) { if (event.persisted) resumed = true; });
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) hiddenAt = Date.now();
        else if (hiddenAt && Date.now() - hiddenAt > config.maxAgeMs) resumed = true;
    });
})(window.jQuery);
