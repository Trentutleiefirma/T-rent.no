(function () {
    'use strict';

    var cfg = window.TRentApp || {};
    var listEl = document.getElementById('productList');
    var editorEl = document.getElementById('editor');
    var searchEl = document.getElementById('search');
    var refreshEl = document.getElementById('refresh');
    var noticeEl = document.getElementById('notice');
    var products = [];
    var selectedId = null;
    var timer = null;

    function esc(value) {
        return String(value == null ? '' : value).replace(/[&<>'"]/g, function (ch) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[ch];
        });
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

    function loadProducts() {
        listEl.innerHTML = '<div class="empty">Laster produkter ...</div>';
        var query = searchEl.value.trim();

        api('/products?search=' + encodeURIComponent(query)).then(function (data) {
            products = data.products || [];
            renderList();
            if (selectedId && products.some(function (p) { return p.id === selectedId; })) {
                selectProduct(selectedId, false);
            }
        }).catch(function (err) {
            listEl.innerHTML = '<div class="empty">Kunne ikke laste produkter.</div>';
            showNotice(err.message, false);
        });
    }

    function renderList() {
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
        selectedId = id;
        renderList();
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
        var rental = p.type === 'redq_rental';
        var priceHint = rental
            ? 'RnB-leieprisen styres av egne prisdata og er låst i første versjon. Vi kobler dette på i neste modul.'
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
                '<div class="hint" style="margin-top:-7px;margin-bottom:16px">' + esc(priceHint) + '</div>' +
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

            var payload = {
                name: document.getElementById('name').value.trim(),
                status: document.getElementById('status').value,
                regular_price: priceInput.disabled ? null : priceInput.value.trim(),
                deposit_enabled: document.getElementById('deposit_enabled').checked,
                deposit_amount: document.getElementById('deposit_amount').value.trim()
            };

            api('/product/' + p.id, {
                method: 'POST',
                body: JSON.stringify(payload)
            }).then(function (updated) {
                var index = products.findIndex(function (item) { return item.id === p.id; });
                if (index >= 0) {
                    products[index] = updated;
                }
                renderList();
                renderEditor(updated);
                showNotice('Produktet er lagret.', true);
            }).catch(function (err) {
                showNotice(err.message, false);
                saveBtn.disabled = false;
                saveBtn.textContent = 'Lagre';
            });
        });
    }

    searchEl.addEventListener('input', function () {
        window.clearTimeout(timer);
        timer = window.setTimeout(loadProducts, 300);
    });

    refreshEl.addEventListener('click', loadProducts);
    loadProducts();
})();
