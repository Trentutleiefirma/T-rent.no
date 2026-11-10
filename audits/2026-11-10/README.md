# T-Rent review, 10 November 2026

## Scope and evidence

- Checked the repository's main branch and relevant historical booking branches. Main contains Admin App 2.4.0; other branches contain RnB source and earlier booking fixes. This repository is not a complete copy of the live WordPress installation. T-Rent Performance and Linked Bookings PHP source are absent.
- Reviewed the Admin App's permission checks, nonce/signature checks and date-blocking paths. This is a focused review, not a claim that every plugin or security property has been audited.
- Opened 16 page URLs (15 unique pages): home, the affected product, account, cart, rental instructions, four categories, Vipps landing page, checkout, product listing, privacy, quote redirect and returns policy. All main documents returned HTTP 200; no horizontal overflow was detected at the tested widths.
- Exercised date/time selection and actual price calculation in isolated Chromium desktop and mobile-emulated contexts. Mobile emulation is not a test on physical Safari.
- Quote submissions in regression tests were intercepted in the browser and answered by a mock. No test booking, email, payment or inventory reservation was created.
- The MCP audit log records connector operations, not customers' browser errors. The exact cause of the original customer's stale/invalid code cannot be reconstructed from it.

## Confirmed findings

1. **Expired nonce prevents further booking.** RnB's `main-script.js` and `rnb-rfq.js` capture a nonce when the document initializes. The price calculation returns `Nonce-verdien kan ikke verifiseres` and disables the quote button when that nonce is invalid. This was reproduced on desktop and mobile emulation by substituting an invalid captured token. Fresh tokens worked. The result establishes the failure mechanism, not whether the original customer got stale HTML from server cache, browser history or a long-lived tab.
2. **WooPayments JavaScript exception on product pages.** `woocommerce-payments/dist/frontend-tracks.js` 11.2.1 accesses `window.wcpayAssets.url` while `wcpayAssets` is missing. The exception was reproduced repeatedly. Supplying the existing registered bundle's asset directory before the bundle executes removes it, without replacing an already defined object.
3. Four CSS/image responses returned HTTP 503 during the first browser pass. These are treated separately from the deterministic JavaScript and nonce findings; they do not establish permanently broken URLs. All four returned HTTP 200 when retried individually (6,951 / 18,420 / 22,765 / 28,893 bytes). No persistent failure was established.

## Repair

`t-rent-booking-health/` is a standalone WordPress plugin. It does not overwrite RnB or the existing performance plugin.

- On a rejected read-only price/inventory request, fetches a fresh canonical product page and retries at most once.
- Confirms the refresh response contains its unique request ID, so an old cached page is not accepted as fresh.
- Re-reads the site's own RnB and Linked Bookings nonces from JSON/hidden fields. No hardcoded nonce action for custom Linked Bookings PHP is needed.
- Refreshes old/restored forms before requests and refreshes before quote submission. It never replays a quote submission.
- Keeps dates, times, products, quantities and customer fields in the original request.
- Provides a bounded server-side nonce-rejection log under WooCommerce > Bestillingskontroll. Fields: timestamp, AJAX action and product ID only. At most 50 events; events older than 14 days are removed on the next recorded failure and hidden from the admin view.
- Restores missing WooPayments tracking asset configuration on rental product pages.
- Clears Cache Enabler once at activation so cached pages receive the script. Existing open tabs must reload once to receive it.

## Validation

- 19 PHP behavioral checks passed on PHP 7.4 and 8.4, plus PHP lint and JavaScript syntax checks.
- Nine browser scenarios passed: desktop stale-token recovery; mobile stale-token recovery; fresh page without an extra snapshot; rejection of a cached refresh; one-retry limit; restored-tab refresh; quote token/linked-token refresh with one mock submission; no replay on rejected quote; no quote submission when refreshing fails.
- No JavaScript exceptions occurred in those nine repaired browser scenarios, including the previously failing WooPayments bundle.
- Browser integration tested the exact release JavaScript against live read-only price calculations with the plugin's PHP-generated config/refresh marker simulated by local response injection. A separate PHP test verifies the marker, no-cache headers, page scoping and asset config. Installation on WordPress still requires live verification.

## Re-run

PHP: `php t-rent-booking-health/tests/php-check.php`

Browser integration: install `playwright` in a local test directory, install Chromium with Playwright, then run `node audits/2026-11-10/browser-check.cjs`. Set `CHROMIUM_PATH` to use system Chromium. The integration fixture uses 17–18 November 2026 and the affected product; update the dates to a valid available future period before a later re-run. It intercepts every quote submission and should remain isolated from real customer sessions.

GitHub Actions runs PHP checks on 7.4 and 8.4 and checks JavaScript syntax. It does not submit forms or run network tests against the live store.
