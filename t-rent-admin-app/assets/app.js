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
        return fetch(cfg.restBase + path, {
            method: options.method || 'GET',
            body: options.body || undefined,
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': cfg.nonce
            }
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

            return '<div class="product ' + (p.id === selectedId ? 'active' : '') + '" data-id="' + p.id + '">' +
                image +
                '<div class="product-main">' +
                    '<div class="product-name">' + esc(p.name) + '</div>' +
                    '<div class="meta">#' + p.id + ' · ' + esc(p.type_label) + price + '</div>' +
                '</div>' +
                '<span class="pill">' + esc(p.status_label) + '</span>' +
            '</div>';
        }).join('');

        Array.prototype.forEach.call(listEl.querySelectorAll('.product'), function (el) {
            el.addEventListener('click', function () {
                selectProduct(Number(el.getAttribute('data-id')), true);
            });
        });
    }

    function selectProduct(id, fetchFresh) {
        var editorEl = document.getElementById('editor');
        selectedId = id;
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

    function renderEditor(p) {
        var editorEl = document.getElementById('editor');
        var rental = p.type === 'redq_rental';
        var priceHint = rental
            ? 'RnB-leieprisen styres av egne prisdata og er låst i denne versjonen.'
            : 'Dette er WooCommerce-produktets ordinære grunnpris.';

        editorEl.innerHTML =
            '<div class="section-title">Rediger produkt #' + p.id + '</div>' +
            '<form id="productForm">' +
                '<div class="field">' +
                    '<label for="name">Navn</label>' +
                    '<input id="name" value="' + esc(p.name) + '" required>' +
                '</div>' +
                '<div class="row">' +
                    '<div class="field">' +
                        '<label for="status">Status</label>' +
                        '<select id="status">' +
                            '<option value="publish"' + (p.status === 'publish' ? ' selected' : '') + '>Publisert</option>' +
                            '<option value="draft"' + (p.status === 'draft' ? ' selected' : '') + '>Kladd</option>' +
                            '<option value="private"' + (p.status === 'private' ? ' selected' : '') + '>Privat</option>' +
                        '</select>' +
                    '</div>' +
                    '<div class="field">' +
                        '<label for="regular_price">WooCommerce grunnpris</label>' +
                        '<input id="regular_price" inputmode="decimal" value="' + esc(p.regular_price) + '"' + (rental ? ' disabled' : '') + '>' +
                    '</div>' +
                '</div>' +
                '<div class="hint product-price-hint">' + esc(priceHint) + '</div>' +
                '<div class="section-title">Depositum</div>' +
                '<label class="check"><input id="deposit_enabled" type="checkbox"' + (p.deposit.enabled ? ' checked' : '') + '> <span>Depositum aktivert</span></label>' +
                '<div class="field">' +
                    '<label for="deposit_amount">Depositumbeløp</label>' +
                    '<input id="deposit_amount" inputmode="decimal" value="' + esc(p.deposit.amount) + '" placeholder="f.eks. 3000">' +
                '</div>' +
                '<div class="hint">Depositumtype beholdes som den er i WooCommerce (' + esc(p.deposit.type || 'fixed') + ').</div>' +
                '<div class="savebar">' +
                    '<button id="saveBtn" class="btn" type="submit">Lagre</button>' +
                    (p.permalink ? '<a class="btn secondary" href="' + esc(p.permalink) + '" target="_blank" rel="noopener">Se produkt</a>' : '') +
                '</div>' +
            '</form>';

        document.getElementById('productForm').addEventListener('submit', function (event) {
            event.preventDefault();

            var saveBtn = document.getElementById('saveBtn');
            var priceInput = document.getElementById('regular_price');
            saveBtn.disabled = true;
            saveBtn.textContent = 'Lagrer ...';

            api('/product/' + p.id, {
                method: 'POST',
                body: JSON.stringify({
                    name: document.getElementById('name').value.trim(),
                    status: document.getElementById('status').value,
                    regular_price: priceInput.disabled ? null : priceInput.value.trim(),
                    deposit_enabled: document.getElementById('deposit_enabled').checked,
                    deposit_amount: document.getElementById('deposit_amount').value.trim()
                })
            }).then(function (updated) {
                var index = products.findIndex(function (item) { return item.id === p.id; });
                if (index >= 0) {
                    products[index] = updated;
                }

                renderProductList();
                renderEditor(updated);
                showNotice('Produktet er lagret.', true);
            }).catch(function (err) {
                showNotice(err.message, false);
                saveBtn.disabled = false;
                saveBtn.textContent = 'Lagre';
            });
        });
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
