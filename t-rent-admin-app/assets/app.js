(function () {
    'use strict';

    var cfg = window.TRentApp || {};
    var noticeEl = document.getElementById('notice');
    var globalSearchEl = document.getElementById('globalSearch');
    var installAppEl = document.getElementById('installApp');
    var deferredInstallPrompt = null;
    var activeView = 'bookings';

    var products = [];
    var selectedId = null;
    var productTimer = null;

    var bookings = [];
    var quotes = [];
    var blocks = [];
    var equipmentData = {rows: [], counts: {}};
    var bookingsLoaded = false;
    var quotesLoaded = false;
    var blocksLoaded = false;
    var equipmentLoaded = false;
    var productsLoaded = false;
    var productOptions = {categories: [], tags: [], inventories: []};
    var productOptionsLoaded = false;
    var productOptionsPromise = null;
    var equipmentFilter = '';

    function esc(value) {
        return String(value == null ? '' : value).replace(/[&<>'"]/g, function (ch) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[ch];
        });
    }

    function searchQuery() {
        return (globalSearchEl && globalSearchEl.value ? globalSearchEl.value : '').trim().toLowerCase();
    }

    function searchMatch(values) {
        var query = searchQuery();
        if (!query) {
            return true;
        }

        return values.map(function (value) {
            return String(value == null ? '' : value).toLowerCase();
        }).join(' ').indexOf(query) !== -1;
    }

    function setSearchPlaceholder(name) {
        var labels = {
            bookings: 'Søk i bookinger, kunde, telefon, e-post, ordre ...',
            quotes: 'Søk i forespørsler, kunde, produkt, dato eller kommentar ...',
            blocks: 'Søk i blokkeringer, produkt eller dato ...',
            equipment: 'Søk i utstyr, status, service, ordre ...',
            products: 'Søk i produkter ...'
        };

        globalSearchEl.placeholder = labels[name] || 'Søk ...';
    }

    function showNotice(message, ok) {
        noticeEl.textContent = message;
        noticeEl.className = 'notice show ' + (ok === false ? 'err' : 'ok');
        window.clearTimeout(noticeEl._timer);
        noticeEl._timer = window.setTimeout(function () {
            noticeEl.className = 'notice';
        }, 4500);
    }

    function api(path, options) {
        options = options || {};
        var headers = {
            'X-WP-Nonce': cfg.nonce
        };
        var isFormData = typeof FormData !== 'undefined' && options.body instanceof FormData;
        if (!isFormData) {
            headers['Content-Type'] = 'application/json';
        }

        return fetch(cfg.restBase + path, {
            method: options.method || 'GET',
            body: options.body || undefined,
            credentials: 'same-origin',
            headers: headers
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (body) {
                if (!res.ok) {
                    throw new Error(body.message || 'Kunne ikke fullføre handlingen.');
                }
                return body;
            });
        });
    }

    function activateView(name) {
        activeView = name;
        setSearchPlaceholder(name);

        Array.prototype.forEach.call(document.querySelectorAll('.view'), function (view) {
            view.classList.toggle('active', view.id === 'view-' + name);
        });

        Array.prototype.forEach.call(document.querySelectorAll('.nav-btn'), function (btn) {
            btn.classList.toggle('active', btn.getAttribute('data-view') === name);
        });

        if (name === 'bookings') {
            bookingsLoaded ? renderBookings() : loadBookings();
        } else if (name === 'quotes') {
            quotesLoaded ? renderQuotes() : loadQuotes();
        } else if (name === 'blocks') {
            blocksLoaded ? renderBlocks(blocks) : loadBlocks();
        } else if (name === 'equipment') {
            equipmentLoaded ? renderEquipment(equipmentData) : loadEquipment();
        } else if (name === 'products') {
            loadProducts();
        }
    }

    Array.prototype.forEach.call(document.querySelectorAll('.nav-btn'), function (btn) {
        btn.addEventListener('click', function () {
            activateView(btn.getAttribute('data-view'));
        });
    });

    function loadBookings() {
        var list = document.getElementById('bookingList');
        list.innerHTML = '<div class="card empty">Laster bookinger ...</div>';

        api('/bookings').then(function (data) {
            bookings = data.bookings || [];
            bookingsLoaded = true;
            renderBookings();
        }).catch(function (err) {
            list.innerHTML = '<div class="card empty">Kunne ikke laste bookinger.</div>';
            showNotice(err.message, false);
        });
    }

    function renderBookings() {
        var list = document.getElementById('bookingList');
        var filter = document.getElementById('bookingFilter').value;
        var visible = bookings.filter(function (b) {
            var phaseMatch;
            if (filter === 'all') {
                phaseMatch = true;
            } else if (filter === 'open') {
                phaseMatch = b.phase === 'ongoing' || b.phase === 'paused' || b.phase === 'upcoming';
            } else {
                phaseMatch = b.phase === filter;
            }

            if (!phaseMatch) {
                return false;
            }

            return searchMatch([
                b.product_name,
                b.order_number,
                b.status_label,
                b.phase_label,
                b.booking_created,
                b.pickup,
                b.return,
                b.customer_name,
                b.phone,
                b.email,
                b.total,
                b.currency,
                b.payment_method
            ]);
        });

        if (!visible.length) {
            list.innerHTML = '<div class="card empty">Ingen bookinger i dette utvalget.</div>';
            return;
        }

        list.innerHTML = visible.map(function (b) {
            var phone = b.phone
                ? '<a class="contact-link" href="tel:' + esc(b.phone) + '">' + esc(b.phone) + '</a>'
                : '<span class="muted">Ingen telefon</span>';
            var email = b.email
                ? '<a class="contact-link" href="mailto:' + esc(b.email) + '">' + esc(b.email) + '</a>'
                : '<span class="muted">Ingen e-post</span>';

            return '<article class="card booking-card">' +
                '<div class="booking-top">' +
                    '<div>' +
                        '<div class="booking-product">' + esc(b.product_name) + '</div>' +
                        '<div class="meta">Ordre #' + esc(b.order_number) + ' · ' + esc(b.status_label) + '</div>' +
                    '</div>' +
                    '<span class="status-badge status-' + esc(b.phase) + '">' + esc(b.phase_label) + '</span>' +
                '</div>' +
                '<div class="booking-grid">' +
                    '<div class="info-box important">' +
                        '<span>Leieperiode</span>' +
                        '<strong>' + esc(b.pickup) + '</strong>' +
                        '<strong>→ ' + esc(b.return) + '</strong>' +
                    '</div>' +
                    '<div class="info-box">' +
                        '<span>Booket</span>' +
                        '<strong>' + esc(b.booking_created || 'Ukjent') + '</strong>' +
                    '</div>' +
                    '<div class="info-box">' +
                        '<span>Kunde</span>' +
                        '<strong>' + esc(b.customer_name) + '</strong>' +
                        '<div>' + phone + '</div>' +
                        '<div>' + email + '</div>' +
                    '</div>' +
                    '<div class="info-box">' +
                        '<span>Betaling</span>' +
                        '<strong>' + esc(b.total) + ' ' + esc(b.currency) + '</strong>' +
                        '<div class="muted">' + esc(b.payment_method || '') + '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="booking-state-bar">' +
                    '<label>Endre T-Rent-status</label>' +
                    '<select class="booking-state-select">' +
                        '<option value="ongoing"' + (b.phase === 'ongoing' && !b.manual_state ? ' selected' : '') + '>Pågående</option>' +
                        '<option value="paused"' + (b.manual_state === 'paused' ? ' selected' : '') + '>På pause</option>' +
                        '<option value="completed"' + (b.manual_state === 'completed' || (b.phase === 'completed' && !b.manual_state) ? ' selected' : '') + '>Fullført</option>' +
                    '</select>' +
                    '<button class="btn secondary small-btn booking-state-save" type="button" data-order-id="' + b.order_id + '">Lagre status</button>' +
                '</div>' +
            '</article>';
        }).join('');

        Array.prototype.forEach.call(list.querySelectorAll('.booking-state-save'), function (btn) {
            btn.addEventListener('click', function () {
                var card = btn.closest('.booking-card');
                var select = card.querySelector('.booking-state-select');
                var orderId = Number(btn.getAttribute('data-order-id'));

                btn.disabled = true;
                btn.textContent = 'Lagrer ...';

                api('/booking/' + orderId + '/state', {
                    method: 'POST',
                    body: JSON.stringify({state: select.value})
                }).then(function (data) {
                    bookings = data.bookings || [];
                    renderBookings();
                    showNotice('Bookingstatus er oppdatert.', true);
                }).catch(function (err) {
                    btn.disabled = false;
                    btn.textContent = 'Lagre status';
                    showNotice(err.message, false);
                });
            });
        });
    }

    document.getElementById('bookingFilter').addEventListener('change', renderBookings);
    document.getElementById('bookingRefresh').addEventListener('click', loadBookings);

    function loadQuotes() {
        var list = document.getElementById('quoteList');
        list.innerHTML = '<div class="card empty">Laster forespørsler ...</div>';

        api('/quotes').then(function (data) {
            quotes = data.quotes || [];
            quotesLoaded = true;
            renderQuotes();
        }).catch(function (err) {
            list.innerHTML = '<div class="card empty">Kunne ikke laste forespørsler.</div>';
            showNotice(err.message, false);
        });
    }

    function renderQuotes() {
        var list = document.getElementById('quoteList');
        var filter = document.getElementById('quoteFilter').value;
        var visible = quotes.filter(function (q) {
            var statusMatch;

            if (filter === 'all') {
                statusMatch = true;
            } else if (filter === 'open') {
                statusMatch = q.relevant === true;
            } else {
                statusMatch = q.status === filter;
            }

            if (!statusMatch) {
                return false;
            }

            var noteText = (q.notes || []).map(function (note) {
                return [note.text, note.author, note.created_at].join(' ');
            }).join(' ');

            return searchMatch([
                q.id,
                q.product_id,
                q.product_name,
                q.status_label,
                q.created,
                q.pickup,
                q.return,
                q.customer_name,
                q.phone,
                q.email,
                q.quote_price,
                q.currency,
                noteText
            ]);
        });

        if (!visible.length) {
            list.innerHTML = '<div class="card empty">Ingen forespørsler i dette utvalget.</div>';
            return;
        }

        list.innerHTML = visible.map(function (q) {
            var phone = q.phone
                ? '<a class="contact-link" href="tel:' + esc(q.phone) + '">' + esc(q.phone) + '</a>'
                : '<span class="muted">Ingen telefon</span>';
            var email = q.email
                ? '<a class="contact-link" href="mailto:' + esc(q.email) + '">' + esc(q.email) + '</a>'
                : '<span class="muted">Ingen e-post</span>';
            var price = q.quote_price !== ''
                ? '<strong>' + esc(q.quote_price) + ' ' + esc(q.currency) + '</strong>'
                : '<span class="muted">Ikke satt</span>';
            var canDecide = q.status === 'quote-pending' ||
                q.status === 'quote-processing' ||
                q.status === 'quote-on-hold';
            var notes = (q.notes || []).slice().reverse().slice(0, 5).map(function (note) {
                return '<div class="quote-note">' +
                    '<div>' + esc(note.text).replace(/\n/g, '<br>') + '</div>' +
                    '<div class="meta">' + esc(note.author || '') + (note.created_at ? ' · ' + esc(note.created_at) : '') + '</div>' +
                '</div>';
            }).join('');

            return '<article class="card quote-card" data-quote-id="' + q.id + '">' +
                '<div class="booking-top">' +
                    '<div>' +
                        '<div class="booking-product">' + esc(q.product_name) + '</div>' +
                        '<div class="meta">Forespørsel #' + q.id + (q.created ? ' · ' + esc(q.created) : '') + '</div>' +
                    '</div>' +
                    '<span class="status-badge status-' + esc(q.status) + '">' + esc(q.status_label) + '</span>' +
                '</div>' +
                '<div class="quote-grid">' +
                    '<div class="info-box important">' +
                        '<span>Leieperiode</span>' +
                        '<strong>' + esc(q.pickup || 'Ukjent') + '</strong>' +
                        '<strong>→ ' + esc(q.return || 'Ukjent') + '</strong>' +
                    '</div>' +
                    '<div class="info-box">' +
                        '<span>Kunde</span>' +
                        '<strong>' + esc(q.customer_name) + '</strong>' +
                        '<div>' + phone + '</div>' +
                        '<div>' + email + '</div>' +
                    '</div>' +
                    '<div class="info-box">' +
                        '<span>Tilbudspris</span>' +
                        price +
                    '</div>' +
                '</div>' +
                (notes ? '<div class="quote-notes"><div class="quote-notes-title">Interne kommentarer</div>' + notes + '</div>' : '') +
                '<div class="quote-comment-area">' +
                    '<textarea class="quote-comment" maxlength="1000" placeholder="Intern kommentar (valgfritt ved godkjenning/avslag)"></textarea>' +
                    '<div class="quote-actions">' +
                        '<button class="btn secondary small-btn quote-comment-save" type="button">Lagre kommentar</button>' +
                        (canDecide ? '<button class="btn success small-btn quote-decision" data-action="approve" type="button">Godkjenn</button>' : '') +
                        (canDecide ? '<button class="btn danger small-btn quote-decision" data-action="reject" type="button">Avslå</button>' : '') +
                        (q.checkout_url ? '<a class="btn secondary small-btn" href="' + esc(q.checkout_url) + '" target="_blank" rel="noopener">Betalingslenke</a>' : '') +
                    '</div>' +
                '</div>' +
            '</article>';
        }).join('');

        Array.prototype.forEach.call(list.querySelectorAll('.quote-comment-save'), function (btn) {
            btn.addEventListener('click', function () {
                var card = btn.closest('.quote-card');
                var quoteId = Number(card.getAttribute('data-quote-id'));
                var textarea = card.querySelector('.quote-comment');
                var comment = textarea.value.trim();

                if (!comment) {
                    showNotice('Skriv en kommentar først.', false);
                    textarea.focus();
                    return;
                }

                var buttons = card.querySelectorAll('button');
                Array.prototype.forEach.call(buttons, function (button) { button.disabled = true; });

                api('/quote/' + quoteId + '/comment', {
                    method: 'POST',
                    body: JSON.stringify({comment: comment})
                }).then(function (data) {
                    quotes = data.quotes || [];
                    renderQuotes();
                    showNotice(data.message || 'Kommentaren er lagret.', true);
                }).catch(function (err) {
                    Array.prototype.forEach.call(buttons, function (button) { button.disabled = false; });
                    showNotice(err.message, false);
                });
            });
        });

        Array.prototype.forEach.call(list.querySelectorAll('.quote-decision'), function (btn) {
            btn.addEventListener('click', function () {
                var card = btn.closest('.quote-card');
                var quoteId = Number(card.getAttribute('data-quote-id'));
                var action = btn.getAttribute('data-action');
                var comment = card.querySelector('.quote-comment').value.trim();

                if (action === 'reject' && !window.confirm('Avslå denne forespørselen?')) {
                    return;
                }

                var buttons = card.querySelectorAll('button');
                Array.prototype.forEach.call(buttons, function (button) { button.disabled = true; });

                api('/quote/' + quoteId + '/decision', {
                    method: 'POST',
                    body: JSON.stringify({
                        action: action,
                        comment: comment
                    })
                }).then(function (data) {
                    quotes = data.quotes || [];
                    renderQuotes();
                    showNotice(data.message || 'Forespørselen er oppdatert.', true);
                }).catch(function (err) {
                    Array.prototype.forEach.call(buttons, function (button) { button.disabled = false; });
                    showNotice(err.message, false);
                });
            });
        });
    }

    document.getElementById('quoteFilter').addEventListener('change', renderQuotes);
    document.getElementById('quoteRefresh').addEventListener('click', loadQuotes);

    function loadBlocks() {
        var list = document.getElementById('blockList');
        list.innerHTML = '<div class="empty">Laster blokkeringer ...</div>';

        api('/blocks').then(function (data) {
            blocksLoaded = true;
            blocks = data.blocks || [];
            renderBlockProducts(data.products || []);
            renderBlocks(blocks);
        }).catch(function (err) {
            list.innerHTML = '<div class="empty">Kunne ikke laste blokkeringer.</div>';
            showNotice(err.message, false);
        });
    }

    function renderBlockProducts(items) {
        var select = document.getElementById('blockProduct');
        var current = select.value;
        select.innerHTML = '<option value="">Velg produkt</option>' + items.map(function (p) {
            return '<option value="' + p.id + '">' + esc(p.name) + '</option>';
        }).join('');

        if (current && items.some(function (p) { return String(p.id) === String(current); })) {
            select.value = current;
        }
    }

    function renderBlocks(items) {
        var list = document.getElementById('blockList');
        items = (items || []).filter(function (b) {
            return searchMatch([
                b.product_id,
                b.product_name,
                b.from,
                b.to,
                b.inventory_count
            ]);
        });

        if (!items.length) {
            list.innerHTML = '<div class="empty">Ingen aktive manuelle blokkeringer.</div>';
            return;
        }

        list.innerHTML = items.map(function (b) {
            var range = b.from === b.to ? b.from : b.from + ' – ' + b.to;
            return '<div class="block-row">' +
                '<div>' +
                    '<strong>' + esc(b.product_name) + '</strong>' +
                    '<div class="meta">' + esc(range) + ' · ' + b.inventory_count + ' inventory</div>' +
                '</div>' +
                '<button class="btn danger small-btn remove-block" type="button" data-ids="' + esc(b.ids.join(',')) + '">Fjern</button>' +
            '</div>';
        }).join('');

        Array.prototype.forEach.call(list.querySelectorAll('.remove-block'), function (btn) {
            btn.addEventListener('click', function () {
                var ids = btn.getAttribute('data-ids').split(',').map(function (v) { return Number(v); }).filter(Boolean);
                btn.disabled = true;

                api('/blocks/remove', {
                    method: 'POST',
                    body: JSON.stringify({ids: ids})
                }).then(function (data) {
                    blocks = data.blocks || [];
            renderBlocks(blocks);
                    showNotice(data.message || 'Blokkeringen er fjernet.', true);
                }).catch(function (err) {
                    btn.disabled = false;
                    showNotice(err.message, false);
                });
            });
        });
    }

    document.getElementById('blockForm').addEventListener('submit', function (event) {
        event.preventDefault();
        var form = event.currentTarget;
        var button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        button.textContent = 'Blokkerer ...';

        api('/blocks', {
            method: 'POST',
            body: JSON.stringify({
                product_id: Number(document.getElementById('blockProduct').value),
                from_date: document.getElementById('blockFrom').value,
                to_date: document.getElementById('blockTo').value
            })
        }).then(function (data) {
            blocks = data.blocks || [];
                    renderBlocks(blocks);
            showNotice(data.message || 'Datoen er blokkert.', true);
            button.disabled = false;
            button.textContent = 'Blokker dato';
        }).catch(function (err) {
            button.disabled = false;
            button.textContent = 'Blokker dato';
            showNotice(err.message, false);
        });
    });

    document.getElementById('blockRefresh').addEventListener('click', loadBlocks);
    document.getElementById('blockFrom').value = cfg.today || '';
    document.getElementById('blockTo').value = cfg.today || '';

    function loadEquipment() {
        var list = document.getElementById('equipmentList');
        list.innerHTML = '<div class="card empty">Laster utstyr ...</div>';

        api('/equipment').then(function (data) {
            equipmentLoaded = true;
            equipmentData = data;
            renderEquipment(equipmentData);
        }).catch(function (err) {
            list.innerHTML = '<div class="card empty">Kunne ikke laste utstyr.</div>';
            showNotice(err.message, false);
        });
    }

    function renderEquipment(data) {
        renderEquipmentCounts(data.counts || {});
        var rows = data.rows || [];
        var list = document.getElementById('equipmentList');
        var visible = equipmentFilter
            ? rows.filter(function (row) { return row.display_status === equipmentFilter; })
            : rows;

        visible = visible.filter(function (row) {
            var active = row.active_booking || {};
            var last = row.last_returned || {};
            var next = row.next_booking || {};

            return searchMatch([
                row.inventory_id,
                row.name,
                row.status_label,
                row.reason,
                row.checked_at,
                row.checked_by,
                row.checked_note,
                row.service_at,
                row.service_by,
                row.service_note,
                active.order_id,
                active.start,
                active.return,
                last.order_id,
                last.start,
                last.return,
                next.order_id,
                next.start,
                next.return
            ]);
        });

        if (!visible.length) {
            list.innerHTML = '<div class="card empty">Ingen utstyr i dette utvalget.</div>';
            return;
        }

        list.innerHTML = visible.map(function (row) {
            var booking = '';
            if (row.active_booking) {
                booking = '<strong>Ute nå</strong><div class="meta">Ordre #' + esc(row.active_booking.order_id) + '<br>Retur ' + esc(row.active_booking.return) + '</div>';
            } else if (row.last_returned) {
                booking = '<strong>Sist returnert</strong><div class="meta">Ordre #' + esc(row.last_returned.order_id) + '<br>' + esc(row.last_returned.return) + '</div>';
            } else {
                booking = '<span class="muted">Ingen tidligere WooCommerce-retur</span>';
            }

            if (row.next_booking) {
                booking += '<div class="meta next-booking"><strong>Neste:</strong> ordre #' + esc(row.next_booking.order_id) + ', ' + esc(row.next_booking.start) + '</div>';
            }

            var checked = row.checked_at
                ? '<strong>' + esc(row.checked_at) + '</strong>' +
                  (row.checked_by ? '<div class="meta">av ' + esc(row.checked_by) + '</div>' : '') +
                  (row.checked_note ? '<div class="meta">' + esc(row.checked_note) + '</div>' : '')
                : '<span class="muted">Ingen kontroll registrert</span>';

            var service = row.service_at
                ? '<strong>' + esc(row.service_at) + '</strong>' +
                  (row.service_by ? '<div class="meta">av ' + esc(row.service_by) + '</div>' : '') +
                  (row.service_note ? '<div class="meta">' + esc(row.service_note) + '</div>' : '')
                : '<span class="muted">Ingen service registrert</span>';

            return '<article class="card equipment-card" data-inventory="' + row.inventory_id + '">' +
                '<div class="equipment-top">' +
                    '<div><div class="booking-product">' + esc(row.name) + '</div>' +
                    (row.quantity > 1 ? '<div class="meta">Antall: ' + row.quantity + '</div>' : '') + '</div>' +
                    '<span class="status-badge eq-' + esc(row.display_status) + '">' + esc(row.status_label) + '</span>' +
                '</div>' +
                (row.reason ? '<div class="equipment-reason">' + esc(row.reason) + '</div>' : '') +
                '<div class="equipment-info">' +
                    '<div class="info-box"><span>Siste / pågående leie</span>' + booking + '</div>' +
                    '<div class="info-box"><span>Sist kontrollert</span>' + checked + '</div>' +
                    '<div class="info-box"><span>Sist service</span>' + service + '</div>' +
                '</div>' +
                '<div class="equipment-actions">' +
                    '<input class="equipment-note" type="text" maxlength="240" placeholder="Kort notat (valgfritt)">' +
                    '<button class="btn success equipment-status" data-status="ready" type="button">Kontrollert – klar</button>' +
                    '<button class="btn purple equipment-status" data-status="service" type="button">Service</button>' +
                    '<button class="btn danger equipment-status" data-status="maintenance" type="button">Ikke klar</button>' +
                    '<button class="btn secondary equipment-status" data-status="pending" type="button">Sett til kontroll</button>' +
                '</div>' +
            '</article>';
        }).join('');

        Array.prototype.forEach.call(list.querySelectorAll('.equipment-status'), function (btn) {
            btn.addEventListener('click', function () {
                var card = btn.closest('.equipment-card');
                var inventoryId = Number(card.getAttribute('data-inventory'));
                var note = card.querySelector('.equipment-note').value.trim();
                var buttons = card.querySelectorAll('.equipment-status');

                Array.prototype.forEach.call(buttons, function (b) { b.disabled = true; });

                api('/equipment/' + inventoryId + '/status', {
                    method: 'POST',
                    body: JSON.stringify({
                        status: btn.getAttribute('data-status'),
                        note: note
                    })
                }).then(function (payload) {
                    showNotice(payload.message || 'Utstyrsstatus er oppdatert.', true);
                    equipmentData = payload;
                    renderEquipment(equipmentData);
                }).catch(function (err) {
                    Array.prototype.forEach.call(buttons, function (b) { b.disabled = false; });
                    showNotice(err.message, false);
                });
            });
        });
    }

    function renderEquipmentCounts(counts) {
        var box = document.getElementById('equipmentCounts');
        var items = [
            ['pending', 'Må kontrolleres'],
            ['service', 'Service'],
            ['maintenance', 'Ikke klar'],
            ['out', 'Ute nå'],
            ['ready', 'Klar']
        ];

        box.innerHTML = items.map(function (item) {
            var key = item[0];
            return '<button type="button" class="card status-count ' + (equipmentFilter === key ? 'active' : '') + '" data-filter="' + key + '">' +
                '<strong>' + Number(counts[key] || 0) + '</strong><span>' + esc(item[1]) + '</span>' +
            '</button>';
        }).join('');

        Array.prototype.forEach.call(box.querySelectorAll('.status-count'), function (btn) {
            btn.addEventListener('click', function () {
                var next = btn.getAttribute('data-filter');
                equipmentFilter = equipmentFilter === next ? '' : next;
                loadEquipment();
            });
        });
    }

    function loadProducts() {
        var listEl = document.getElementById('productList');
        listEl.innerHTML = '<div class="empty">Laster produkter ...</div>';

        api('/products?search=' + encodeURIComponent(globalSearchEl.value.trim())).then(function (data) {
            products = data.products || [];
            productsLoaded = true;
            renderProductList();

            if (selectedId && products.some(function (p) { return p.id === selectedId; })) {
                selectProduct(selectedId, false);
            }
        }).catch(function (err) {
            listEl.innerHTML = '<div class="empty">Kunne ikke laste produkter.</div>';
            showNotice(err.message, false);
        });
    }

    function renderProductList() {
        var listEl = document.getElementById('productList');

        if (!products.length) {
            listEl.innerHTML = '<div class="empty">Ingen produkter funnet.</div>';
            return;
        }

        listEl.innerHTML = products.map(function (p) {
            var image = p.image
                ? '<img class="thumb" src="' + esc(p.image) + '" alt="">'
                : '<div class="thumb"></div>';
            var price = p.regular_price !== '' ? ' · ' + esc(p.regular_price) + ' kr' : '';

            return '<button type="button" class="product ' + (p.id === selectedId ? 'active' : '') + '" data-id="' + p.id + '">' +
                image +
                '<span class="product-main">' +
                    '<span class="product-name">' + esc(p.name) + '</span>' +
                    '<span class="meta">#' + p.id + ' · ' + esc(p.type_label) + price + '</span>' +
                '</span>' +
                '<span class="pill">' + esc(p.status_label) + '</span>' +
            '</button>';
        }).join('');

        Array.prototype.forEach.call(listEl.querySelectorAll('.product'), function (el) {
            el.addEventListener('click', function () {
                selectProduct(Number(el.getAttribute('data-id')), true);
            });
        });
    }

    function openProductEditor() {
        var view = document.getElementById('view-products');
        if (view) {
            view.classList.add('show-editor');
        }
    }

    function closeProductEditor() {
        var view = document.getElementById('view-products');
        if (view) {
            view.classList.remove('show-editor');
        }

        selectedId = null;
        renderProductList();

        var editorEl = document.getElementById('editor');
        if (editorEl) {
            editorEl.innerHTML = '<div class="empty">Velg et produkt for å redigere.</div>';
        }
    }

    function selectProduct(id, fetchFresh) {
        var editorEl = document.getElementById('editor');
        selectedId = id;
        openProductEditor();
        renderProductList();
        editorEl.innerHTML = '<div class="empty">Laster produkt ...</div>';

        var source = fetchFresh === false
            ? Promise.resolve(products.find(function (p) { return p.id === id; }))
            : api('/product/' + id);

        source.then(function (product) {
            if (!product) {
                throw new Error('Produktet ble ikke funnet.');
            }
            renderEditor(product);
        }).catch(function (err) {
            editorEl.innerHTML = '<div class="empty">Kunne ikke laste produktet.</div>';
            showNotice(err.message, false);
        });
    }

    function loadProductOptions(force) {
        if (force) {
            productOptionsLoaded = false;
            productOptionsPromise = null;
        }

        if (productOptionsLoaded) {
            return Promise.resolve(productOptions);
        }

        if (productOptionsPromise) {
            return productOptionsPromise;
        }

        productOptionsPromise = api('/product-options').then(function (data) {
            productOptions = {
                categories: data.categories || [],
                tags: data.tags || [],
                inventories: data.inventories || []
            };
            productOptionsLoaded = true;
            productOptionsPromise = null;
            return productOptions;
        }).catch(function (err) {
            productOptionsPromise = null;
            throw err;
        });

        return productOptionsPromise;
    }

    function productDefaults() {
        return {
            id: 0,
            name: '',
            slug: '',
            description: '',
            short_description: '',
            status: 'draft',
            status_label: 'Kladd',
            type: 'redq_rental',
            type_label: 'RnB utleie',
            sku: '',
            regular_price: '',
            stock_status: 'instock',
            featured: null,
            gallery: [],
            category_ids: [],
            tag_names: [],
            permalink: '',
            admin_edit_url: '',
            deposit: {
                enabled: true,
                type: 'fixed',
                amount: '5000'
            },
            rental: {
                inventory_id: 0,
                inventory_name: '',
                base_price: '',
                quantity: 1,
                tiers: [
                    {min_days: 3, max_days: 4, daily_price: '', discount_type: '', discount_amount: ''},
                    {min_days: 5, max_days: '', daily_price: '', discount_type: '', discount_amount: ''}
                ],
                request_quote: true,
                book_now: false
            }
        };
    }

    function renderCreateProduct() {
        var editorEl = document.getElementById('editor');
        selectedId = null;
        renderProductList();
        openProductEditor();
        editorEl.innerHTML = '<div class="empty">Laster produktfelter ...</div>';

        loadProductOptions(false).then(function () {
            renderProductForm(productDefaults(), true);
        }).catch(function (err) {
            editorEl.innerHTML = '<div class="empty">Kunne ikke laste produktfeltene.</div>';
            showNotice(err.message, false);
        });
    }

    function renderEditor(p) {
        var editorEl = document.getElementById('editor');
        editorEl.innerHTML = '<div class="empty">Laster produktfelter ...</div>';

        loadProductOptions(false).then(function () {
            renderProductForm(p, false);
        }).catch(function (err) {
            editorEl.innerHTML = '<div class="empty">Kunne ikke laste produktfeltene.</div>';
            showNotice(err.message, false);
        });
    }

    function categoryOptions(selectedIds) {
        selectedIds = (selectedIds || []).map(Number);

        return productOptions.categories.map(function (cat) {
            var prefix = cat.parent ? '— ' : '';
            return '<option value="' + Number(cat.id) + '"' +
                (selectedIds.indexOf(Number(cat.id)) !== -1 ? ' selected' : '') +
                '>' + esc(prefix + cat.name) + '</option>';
        }).join('');
    }

    function inventoryOptions(selectedId) {
        var html = '<option value="new"' + (!selectedId ? ' selected' : '') + '>Opprett nytt utstyr/lager automatisk</option>';

        productOptions.inventories.forEach(function (inventory) {
            html += '<option value="' + Number(inventory.id) + '"' +
                (Number(selectedId) === Number(inventory.id) ? ' selected' : '') +
                '>' + esc(inventory.name) +
                (inventory.base_price !== '' ? ' · ' + esc(inventory.base_price) + ' kr' : '') +
                '</option>';
        });

        return html;
    }

    function selectedNumberValues(select) {
        return Array.prototype.slice.call(select.options)
            .filter(function (option) { return option.selected; })
            .map(function (option) { return Number(option.value); })
            .filter(function (value) { return value > 0; });
    }

    function renderProductForm(product, isNew) {
        var p = product || productDefaults();
        var editorEl = document.getElementById('editor');
        var featured = p.featured || null;
        var gallery = Array.isArray(p.gallery) ? p.gallery.slice() : [];
        var rentalData = p.rental || productDefaults().rental;
        var tiers = Array.isArray(rentalData.tiers) && rentalData.tiers.length
            ? rentalData.tiers.map(function (tier) {
                return {
                    min_days: tier.min_days == null ? '' : tier.min_days,
                    max_days: tier.max_days == null ? '' : tier.max_days,
                    daily_price: tier.daily_price == null ? '' : tier.daily_price,
                    discount_type: tier.discount_type || '',
                    discount_amount: tier.discount_amount == null ? '' : tier.discount_amount
                };
            })
            : [];

        if (isNew && !tiers.length) {
            tiers = [
                {min_days: 3, max_days: 4, daily_price: '', discount_type: '', discount_amount: ''},
                {min_days: 5, max_days: '', daily_price: '', discount_type: '', discount_amount: ''}
            ];
        }

        var type = p.type || 'redq_rental';
        var heading = isNew ? 'Nytt produkt' : 'Rediger produkt #' + p.id;
        var saveLabel = isNew ? 'Opprett produkt' : 'Lagre produkt';

        editorEl.innerHTML =
            '<div class="editor-head">' +
                '<div class="section-title">' + esc(heading) + '</div>' +
                '<button id="backToProducts" class="btn secondary small-btn mobile-product-back" type="button">Tilbake</button>' +
            '</div>' +
            '<form id="productForm" class="product-edit-form">' +
                '<div class="product-form-section">' +
                    '<div class="product-form-section-title">Produkt</div>' +
                    '<div class="field">' +
                        '<label for="productName">Produktnavn</label>' +
                        '<input id="productName" value="' + esc(p.name || '') + '" required autocomplete="off" placeholder="Produktnavn">' +
                    '</div>' +
                    '<div class="product-form-grid">' +
                        '<div class="field">' +
                            '<label for="productType">Produkttype</label>' +
                            '<select id="productType"' + (!isNew ? ' disabled' : '') + '>' +
                                '<option value="redq_rental"' + (type === 'redq_rental' ? ' selected' : '') + '>RnB utleie</option>' +
                                '<option value="simple"' + (type === 'simple' ? ' selected' : '') + '>Enkelt produkt</option>' +
                            '</select>' +
                        '</div>' +
                        '<div class="field">' +
                            '<label for="productStatus">Status</label>' +
                            '<select id="productStatus">' +
                                '<option value="draft"' + (p.status === 'draft' ? ' selected' : '') + '>Kladd</option>' +
                                '<option value="publish"' + (p.status === 'publish' ? ' selected' : '') + '>Publisert</option>' +
                                '<option value="private"' + (p.status === 'private' ? ' selected' : '') + '>Privat</option>' +
                            '</select>' +
                        '</div>' +
                    '</div>' +
                    '<div class="product-form-grid">' +
                        '<div class="field">' +
                            '<label for="productSku">SKU / varenummer</label>' +
                            '<input id="productSku" value="' + esc(p.sku || '') + '" placeholder="Valgfritt">' +
                        '</div>' +
                        '<div class="field">' +
                            '<label for="productSlug">URL-navn</label>' +
                            '<input id="productSlug" value="' + esc(p.slug || '') + '" placeholder="Lages automatisk hvis tomt">' +
                        '</div>' +
                    '</div>' +
                '</div>' +

                '<div class="product-form-section">' +
                    '<div class="product-form-section-title">Annonsetekst</div>' +
                    '<div class="field">' +
                        '<label for="shortDescription">Kort beskrivelse</label>' +
                        '<textarea id="shortDescription" rows="4" placeholder="Kort tekst som vises øverst på produktsiden">' + esc(p.short_description || '') + '</textarea>' +
                    '</div>' +
                    '<div class="field">' +
                        '<label for="description">Beskrivelse</label>' +
                        '<textarea id="description" rows="11" placeholder="Pris, bruksområde, teknisk informasjon, levering osv.">' + esc(p.description || '') + '</textarea>' +
                    '</div>' +
                '</div>' +

                '<div class="product-form-section">' +
                    '<div class="product-form-section-title">Bilder</div>' +
                    '<div class="media-picker-block">' +
                        '<div class="media-picker-label">Hovedbilde</div>' +
                        '<div id="featuredPreview" class="media-preview"></div>' +
                        '<label class="btn secondary media-upload-btn" for="featuredImageInput">Velg hovedbilde</label>' +
                        '<input id="featuredImageInput" class="file-input" type="file" accept="image/*">' +
                    '</div>' +
                    '<div class="media-picker-block">' +
                        '<div class="media-picker-label">Produktgalleri</div>' +
                        '<div id="galleryPreview" class="gallery-preview"></div>' +
                        '<label class="btn secondary media-upload-btn" for="galleryImageInput">Legg til bilder</label>' +
                        '<input id="galleryImageInput" class="file-input" type="file" accept="image/*" multiple>' +
                    '</div>' +
                '</div>' +

                '<div class="product-form-section rental-fields">' +
                    '<div class="product-form-section-title">Pris og utleie</div>' +
                    '<div class="product-form-grid">' +
                        '<div class="field">' +
                            '<label for="rentalBasePrice">Grunnpris per dag</label>' +
                            '<input id="rentalBasePrice" inputmode="decimal" value="' + esc(rentalData.base_price || '') + '" placeholder="f.eks. 1299">' +
                        '</div>' +
                        '<div class="field">' +
                            '<label for="rentalQuantity">Antall tilgjengelig</label>' +
                            '<input id="rentalQuantity" type="number" min="1" step="1" value="' + esc(rentalData.quantity || 1) + '">' +
                        '</div>' +
                    '</div>' +
                    '<div class="field">' +
                        '<label for="rentalInventory">RnB utstyr/lager</label>' +
                        '<select id="rentalInventory">' + inventoryOptions(rentalData.inventory_id || 0) + '</select>' +
                        '<div class="hint">For et nytt produkt er «opprett nytt» normalt riktig. Da kobles pris og tilgjengelighet korrekt til RnB.</div>' +
                    '</div>' +
                    '<div class="tier-head">' +
                        '<div><strong>Prisnivåer</strong><div class="hint">Grunnprisen gjelder ellers. Eksempel: 3–4 dager 1099 kr/dag og 5+ dager 899 kr/dag.</div></div>' +
                        '<button id="addTier" class="btn secondary small-btn" type="button">Legg til nivå</button>' +
                    '</div>' +
                    '<div id="tierRows" class="tier-rows"></div>' +
                    '<div class="toggle-grid">' +
                        '<label class="check"><input id="requestQuote" type="checkbox"' + (rentalData.request_quote !== false ? ' checked' : '') + '> <span>Tillat forespørsel</span></label>' +
                        '<label class="check"><input id="bookNow" type="checkbox"' + (rentalData.book_now ? ' checked' : '') + '> <span>Tillat direkte booking</span></label>' +
                    '</div>' +
                '</div>' +

                '<div class="product-form-section simple-fields">' +
                    '<div class="product-form-section-title">Pris og lager</div>' +
                    '<div class="product-form-grid">' +
                        '<div class="field"><label for="regularPrice">Ordinær pris</label><input id="regularPrice" inputmode="decimal" value="' + esc(p.regular_price || '') + '" placeholder="f.eks. 499"></div>' +
                        '<div class="field"><label for="stockStatus">Lagerstatus</label><select id="stockStatus">' +
                            '<option value="instock"' + (p.stock_status === 'instock' ? ' selected' : '') + '>På lager</option>' +
                            '<option value="outofstock"' + (p.stock_status === 'outofstock' ? ' selected' : '') + '>Ikke på lager</option>' +
                            '<option value="onbackorder"' + (p.stock_status === 'onbackorder' ? ' selected' : '') + '>Restordre</option>' +
                        '</select></div>' +
                    '</div>' +
                '</div>' +

                '<div class="product-form-section">' +
                    '<div class="product-form-section-title">Kategorier og etiketter</div>' +
                    '<div class="field">' +
                        '<label for="productCategories">Produktkategorier</label>' +
                        '<select id="productCategories" multiple size="6">' + categoryOptions(p.category_ids || []) + '</select>' +
                        '<div class="hint">Du kan velge flere kategorier.</div>' +
                    '</div>' +
                    '<div class="field">' +
                        '<label for="productTags">Etiketter</label>' +
                        '<input id="productTags" value="' + esc((p.tag_names || []).join(', ')) + '" placeholder="f.eks. dumper, beltetrillebår">' +
                        '<div class="hint">Skill flere etiketter med komma. Nye etiketter opprettes automatisk.</div>' +
                    '</div>' +
                '</div>' +

                '<div class="product-form-section">' +
                    '<div class="product-form-section-title">Depositum</div>' +
                    '<div class="toggle-grid"><label class="check"><input id="depositEnabled" type="checkbox"' + (p.deposit && p.deposit.enabled ? ' checked' : '') + '> <span>Depositum aktivert</span></label></div>' +
                    '<div class="product-form-grid">' +
                        '<div class="field"><label for="depositType">Type</label><select id="depositType">' +
                            '<option value="fixed"' + (!p.deposit || p.deposit.type !== 'percent' ? ' selected' : '') + '>Fast beløp</option>' +
                            '<option value="percent"' + (p.deposit && p.deposit.type === 'percent' ? ' selected' : '') + '>Prosent</option>' +
                        '</select></div>' +
                        '<div class="field"><label for="depositAmount">Beløp</label><input id="depositAmount" inputmode="decimal" value="' + esc(p.deposit ? p.deposit.amount : '') + '" placeholder="f.eks. 5000"></div>' +
                    '</div>' +
                '</div>' +

                '<div class="savebar product-savebar">' +
                    '<button id="saveProductBtn" class="btn" type="submit">' + esc(saveLabel) + '</button>' +
                    (!isNew && p.permalink ? '<a class="btn secondary" href="' + esc(p.permalink) + '" target="_blank" rel="noopener">Se annonse</a>' : '') +
                    (!isNew && p.admin_edit_url ? '<a class="btn secondary" href="' + esc(p.admin_edit_url) + '" target="_blank" rel="noopener">Åpne i WooCommerce</a>' : '') +
                '</div>' +
            '</form>';

        document.getElementById('backToProducts').addEventListener('click', closeProductEditor);

        var typeEl = document.getElementById('productType');
        function syncProductType() {
            var rental = typeEl.value === 'redq_rental';
            Array.prototype.forEach.call(editorEl.querySelectorAll('.rental-fields'), function (el) {
                el.hidden = !rental;
            });
            Array.prototype.forEach.call(editorEl.querySelectorAll('.simple-fields'), function (el) {
                el.hidden = rental;
            });
        }
        if (isNew) {
            typeEl.addEventListener('change', syncProductType);
        }
        syncProductType();

        function renderFeatured() {
            var box = document.getElementById('featuredPreview');
            if (!featured || !featured.id) {
                box.innerHTML = '<div class="media-empty">Ingen hovedbilde valgt</div>';
                return;
            }

            box.innerHTML =
                '<div class="featured-media-item">' +
                    '<img src="' + esc(featured.thumb || featured.url || '') + '" alt="">' +
                    '<button id="removeFeatured" class="media-remove" type="button" aria-label="Fjern hovedbilde">×</button>' +
                '</div>';

            document.getElementById('removeFeatured').addEventListener('click', function () {
                featured = null;
                renderFeatured();
            });
        }

        function renderGallery() {
            var box = document.getElementById('galleryPreview');
            if (!gallery.length) {
                box.innerHTML = '<div class="media-empty">Ingen galleribilder</div>';
                return;
            }

            box.innerHTML = gallery.map(function (image, index) {
                return '<div class="gallery-media-item">' +
                    '<img src="' + esc(image.thumb || image.url || '') + '" alt="">' +
                    '<button class="media-remove" type="button" data-gallery-index="' + index + '" aria-label="Fjern bilde">×</button>' +
                '</div>';
            }).join('');

            Array.prototype.forEach.call(box.querySelectorAll('[data-gallery-index]'), function (button) {
                button.addEventListener('click', function () {
                    gallery.splice(Number(button.getAttribute('data-gallery-index')), 1);
                    renderGallery();
                });
            });
        }

        function uploadImage(file) {
            var formData = new FormData();
            formData.append('file', file, file.name);
            return api('/media', {
                method: 'POST',
                body: formData
            });
        }

        document.getElementById('featuredImageInput').addEventListener('change', function (event) {
            var file = event.target.files && event.target.files[0];
            if (!file) return;

            showNotice('Laster opp hovedbilde ...', true);
            uploadImage(file).then(function (image) {
                featured = image;
                renderFeatured();
                showNotice('Hovedbildet er lastet opp.', true);
            }).catch(function (err) {
                showNotice(err.message, false);
            }).finally(function () {
                event.target.value = '';
            });
        });

        document.getElementById('galleryImageInput').addEventListener('change', function (event) {
            var files = Array.prototype.slice.call(event.target.files || []);
            if (!files.length) return;

            showNotice('Laster opp ' + files.length + ' bilde' + (files.length === 1 ? '' : 'r') + ' ...', true);
            files.reduce(function (chain, file) {
                return chain.then(function () {
                    return uploadImage(file).then(function (image) {
                        gallery.push(image);
                        renderGallery();
                    });
                });
            }, Promise.resolve()).then(function () {
                showNotice('Galleribildene er lastet opp.', true);
            }).catch(function (err) {
                showNotice(err.message, false);
            }).finally(function () {
                event.target.value = '';
            });
        });

        function renderTiers() {
            var box = document.getElementById('tierRows');
            if (!tiers.length) {
                box.innerHTML = '<div class="media-empty">Ingen ekstra prisnivåer. Grunnprisen gjelder alle dager.</div>';
                return;
            }

            box.innerHTML = tiers.map(function (tier, index) {
                var legacyHint = tier.discount_type === 'fixed' && tier.discount_amount
                    ? '<div class="tier-legacy">Eksisterende fast rabatt ' + esc(tier.discount_amount) + ' kr beholdes hvis dagpris står tom.</div>'
                    : '';

                return '<div class="tier-row" data-tier-row="' + index + '">' +
                    '<div class="field"><label>Fra dag</label><input class="tier-min" type="number" min="1" step="1" value="' + esc(tier.min_days) + '"></div>' +
                    '<div class="field"><label>Til dag</label><input class="tier-max" type="number" min="1" step="1" value="' + esc(tier.max_days) + '" placeholder="∞"></div>' +
                    '<div class="field"><label>Pris / dag</label><input class="tier-price" inputmode="decimal" value="' + esc(tier.daily_price) + '" placeholder="kr"></div>' +
                    '<button class="tier-remove" type="button" aria-label="Fjern prisnivå">×</button>' +
                    legacyHint +
                '</div>';
            }).join('');

            Array.prototype.forEach.call(box.querySelectorAll('[data-tier-row]'), function (row) {
                var index = Number(row.getAttribute('data-tier-row'));
                row.querySelector('.tier-min').addEventListener('input', function (event) {
                    tiers[index].min_days = event.target.value;
                });
                row.querySelector('.tier-max').addEventListener('input', function (event) {
                    tiers[index].max_days = event.target.value;
                });
                row.querySelector('.tier-price').addEventListener('input', function (event) {
                    tiers[index].daily_price = event.target.value;
                });
                row.querySelector('.tier-remove').addEventListener('click', function () {
                    tiers.splice(index, 1);
                    renderTiers();
                });
            });
        }

        document.getElementById('addTier').addEventListener('click', function () {
            tiers.push({min_days: '', max_days: '', daily_price: '', discount_type: '', discount_amount: ''});
            renderTiers();
        });

        renderFeatured();
        renderGallery();
        renderTiers();

        document.getElementById('productForm').addEventListener('submit', function (event) {
            event.preventDefault();

            var saveBtn = document.getElementById('saveProductBtn');
            var currentType = typeEl.value;
            var tagNames = document.getElementById('productTags').value
                .split(',')
                .map(function (tag) { return tag.trim(); })
                .filter(Boolean);

            var payload = {
                name: document.getElementById('productName').value.trim(),
                type: currentType,
                status: document.getElementById('productStatus').value,
                sku: document.getElementById('productSku').value.trim(),
                slug: document.getElementById('productSlug').value.trim(),
                short_description: document.getElementById('shortDescription').value,
                description: document.getElementById('description').value,
                category_ids: selectedNumberValues(document.getElementById('productCategories')),
                tag_names: tagNames,
                featured_media: featured && featured.id ? Number(featured.id) : 0,
                gallery_ids: gallery.map(function (image) { return Number(image.id); }).filter(Boolean),
                deposit_enabled: document.getElementById('depositEnabled').checked,
                deposit_type: document.getElementById('depositType').value,
                deposit_amount: document.getElementById('depositAmount').value.trim(),
                stock_status: document.getElementById('stockStatus').value
            };

            if (currentType === 'redq_rental') {
                var inventoryValue = document.getElementById('rentalInventory').value;
                payload.rental = {
                    create_inventory: inventoryValue === 'new',
                    inventory_id: inventoryValue === 'new' ? 0 : Number(inventoryValue),
                    inventory_name: payload.name,
                    base_price: document.getElementById('rentalBasePrice').value.trim(),
                    quantity: Number(document.getElementById('rentalQuantity').value || 1),
                    tiers: tiers.map(function (tier) {
                        return {
                            min_days: tier.min_days,
                            max_days: tier.max_days,
                            daily_price: tier.daily_price,
                            discount_type: tier.discount_type || '',
                            discount_amount: tier.discount_amount || ''
                        };
                    }),
                    request_quote: document.getElementById('requestQuote').checked,
                    book_now: document.getElementById('bookNow').checked
                };
            } else {
                payload.regular_price = document.getElementById('regularPrice').value.trim();
            }

            saveBtn.disabled = true;
            saveBtn.textContent = isNew ? 'Oppretter ...' : 'Lagrer ...';

            var endpoint = isNew ? '/products' : '/product/' + p.id;
            api(endpoint, {
                method: 'POST',
                body: JSON.stringify(payload)
            }).then(function (updated) {
                var index = products.findIndex(function (item) { return item.id === updated.id; });
                if (index >= 0) {
                    products[index] = updated;
                } else {
                    products.push(updated);
                }

                products.sort(function (a, b) {
                    return String(a.name || '').localeCompare(String(b.name || ''), 'nb');
                });

                selectedId = updated.id;
                renderProductList();
                openProductEditor();

                return loadProductOptions(true).catch(function () {
                    return productOptions;
                }).then(function () {
                    renderProductForm(updated, false);
                    showNotice(isNew ? 'Produktet er opprettet.' : 'Produktet er lagret.', true);
                });
            }).catch(function (err) {
                showNotice(err.message, false);
                saveBtn.disabled = false;
                saveBtn.textContent = saveLabel;
            });
        });

        window.setTimeout(function () {
            var nameEl = document.getElementById('productName');
            if (nameEl && isNew) nameEl.focus();
        }, 0);
    }

    globalSearchEl.addEventListener('input', function () {
        window.clearTimeout(productTimer);
        productTimer = window.setTimeout(function () {
            if (activeView === 'bookings') {
                renderBookings();
            } else if (activeView === 'quotes') {
                renderQuotes();
            } else if (activeView === 'blocks') {
                renderBlocks(blocks);
            } else if (activeView === 'equipment') {
                renderEquipment(equipmentData);
            } else if (activeView === 'products') {
                loadProducts();
            }
        }, activeView === 'products' ? 300 : 120);
    });

    document.getElementById('globalSearchClear').addEventListener('click', function () {
        if (!globalSearchEl.value) {
            return;
        }

        globalSearchEl.value = '';

        if (activeView === 'bookings') {
            renderBookings();
        } else if (activeView === 'quotes') {
            renderQuotes();
        } else if (activeView === 'blocks') {
            renderBlocks(blocks);
        } else if (activeView === 'equipment') {
            renderEquipment(equipmentData);
        } else if (activeView === 'products') {
            loadProducts();
        }

        globalSearchEl.focus();
    });

    document.getElementById('newProduct').addEventListener('click', renderCreateProduct);
    document.getElementById('refresh').addEventListener('click', loadProducts);

    if ('serviceWorker' in navigator && cfg.serviceWorkerUrl) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register(cfg.serviceWorkerUrl, {
                scope: cfg.appScope || '/t-rent-app/'
            }).catch(function () {
                // PWA-installasjon er valgfri. Appen skal fortsatt fungere uten service worker.
            });
        });
    }

    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        deferredInstallPrompt = event;
        if (installAppEl) {
            installAppEl.hidden = false;
        }
    });

    if (installAppEl) {
        installAppEl.addEventListener('click', function () {
            if (!deferredInstallPrompt) {
                showNotice('Åpne T-RENT APP i Chrome og prøv igjen når installasjonsknappen blir tilgjengelig.', false);
                return;
            }

            deferredInstallPrompt.prompt();
            deferredInstallPrompt.userChoice.then(function () {
                deferredInstallPrompt = null;
                installAppEl.hidden = true;
            });
        });
    }

    window.addEventListener('appinstalled', function () {
        deferredInstallPrompt = null;
        if (installAppEl) {
            installAppEl.hidden = true;
        }
        showNotice('T-RENT APP er installert på enheten.', true);
    });

    if (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches && installAppEl) {
        installAppEl.hidden = true;
    }

    setSearchPlaceholder('bookings');
    loadBookings();
})();
