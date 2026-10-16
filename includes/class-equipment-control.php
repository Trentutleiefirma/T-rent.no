<?php

namespace TRent_Drift;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * T-Rent equipment readiness checklist.
 *
 * Each RnB inventory gets one explicit operational state. A new WooCommerce
 * rental return automatically makes that inventory due for inspection when
 * its return time passes. The equipment is only shown as ready after a person
 * presses the approval button. Manual/Hygglo rentals can be handled by setting
 * the inventory back to "needs inspection" from the same dashboard.
 *
 * The dashboard is available in wp-admin and through a secret bookmark URL,
 * so day-to-day use does not require a WordPress login. This class does not
 * change rental prices, order totals, dates, payments, or storefront
 * availability.
 */
final class Equipment_Control
{
    const PAGE_SLUG              = 't-rent-equipment-control';
    const PUBLIC_APP_SLUG        = 't-rent-app';
    const PUBLIC_QUERY_VAR       = 't_rent_utstyrskontroll';
    const PUBLIC_TOKEN_PARAM     = 'key';
    const SEARCH_PARAM           = 't_rent_search';
    const TOKEN_OPTION           = 't_rent_equipment_control_token';
    const REWRITE_VERSION_OPTION = 't_rent_admin_app_rewrite_version';
    const STATUS_META            = '_t_rent_equipment_status';
    const CHECKED_AT_META        = '_t_rent_equipment_checked_at';
    const CHECKED_BY_META        = '_t_rent_equipment_checked_by';
    const CHECKED_NOTE_META      = '_t_rent_equipment_checked_note';
    const CHECKED_THROUGH_META   = '_t_rent_equipment_checked_through';
    const SERVICE_AT_META        = '_t_rent_equipment_service_at';
    const SERVICE_BY_META        = '_t_rent_equipment_service_by';
    const SERVICE_NOTE_META      = '_t_rent_equipment_service_note';
    const LOG_META               = '_t_rent_equipment_control_log';

    public function __construct()
    {
        add_action('init', [__CLASS__, 'register_rewrite_rules']);
        add_action('init', [$this, 'maybe_flush_rewrite_rules'], 99);
        add_action('admin_menu', [$this, 'register_admin_page'], 80);
        add_action('template_redirect', [$this, 'maybe_render_public_dashboard'], 0);
        add_filter('wp_robots', [$this, 'prevent_public_indexing']);
    }

    public static function app_base_url()
    {
        return rtrim(home_url('/'), '/') . '/' . self::PUBLIC_APP_SLUG . '/';
    }

    public static function register_rewrite_rules()
    {
        add_rewrite_rule(
            '^' . self::PUBLIC_APP_SLUG . '(?:/.*)?$',
            'index.php?t_rent_app_route=1',
            'top'
        );
    }

    public function maybe_flush_rewrite_rules()
    {
        $version = defined('T_RENT_ADMIN_APP_VERSION')
            ? T_RENT_ADMIN_APP_VERSION
            : '2.3.0';

        if ((string) get_option(self::REWRITE_VERSION_OPTION, '') === $version) {
            return;
        }

        self::register_rewrite_rules();
        flush_rewrite_rules(false);
        update_option(self::REWRITE_VERSION_OPTION, $version, false);
    }

    public function ensure_public_token()
    {
        if (get_option(self::TOKEN_OPTION, '') !== '') {
            return;
        }

        $token = wp_generate_password(48, false, false);
        add_option(self::TOKEN_OPTION, $token, '', 'no');
    }

    public function register_admin_page()
    {
        add_menu_page(
            'Utstyrskontroll',
            'T-Rent Admin',
            'manage_woocommerce',
            self::PAGE_SLUG,
            [$this, 'render_admin_page'],
            'dashicons-clipboard',
            56.5
        );

        add_submenu_page(
            self::PAGE_SLUG,
            'Utstyrskontroll',
            'Utstyrskontroll',
            'manage_woocommerce',
            self::PAGE_SLUG,
            [$this, 'render_admin_page']
        );
    }

    public function prevent_public_indexing($robots)
    {
        if ($this->is_public_request()) {
            $robots['noindex'] = true;
            $robots['nofollow'] = true;
            $robots['noarchive'] = true;
        }

        return $robots;
    }

    public function maybe_render_public_dashboard()
    {
        if ($this->is_service_worker_request()) {
            $this->render_service_worker();
        }

        if ($this->is_manifest_request()) {
            $this->render_manifest();
        }

        if (!$this->is_public_request()) {
            return;
        }

        $token = $this->request_token();
        if (!$this->valid_token($token)) {
            status_header(404);
            nocache_headers();
            exit('Siden finnes ikke.');
        }

        if (!$this->woocommerce_ready()) {
            status_header(503);
            nocache_headers();
            exit('Utstyrskontrollen er midlertidig utilgjengelig.');
        }

        if ($this->is_legacy_public_get()) {
            $url = Date_Blocker::is_public_view()
                ? Date_Blocker::public_url($token)
                : $this->public_url($token);

            if (
                isset($_GET['t_rent_status']) &&
                is_scalar($_GET['t_rent_status'])
            ) {
                $status = sanitize_key(wp_unslash($_GET['t_rent_status']));
                if (in_array($status, ['pending', 'service', 'maintenance', 'out', 'ready'], true)) {
                    $url = add_query_arg('t_rent_status', $status, $url);
                }
            }

            wp_safe_redirect($url, 302);
            exit;
        }

        if (Date_Blocker::is_public_post()) {
            Date_Blocker::handle_public_action($token);
        }

        if ($this->is_status_post()) {
            $message = $this->handle_status_action(true, $token);
            $redirect_url = add_query_arg('t_rent_notice', $message, $this->public_url($token));
            wp_safe_redirect($this->add_posted_dashboard_filters($redirect_url));
            exit;
        }

        $message = isset($_GET['t_rent_notice'])
            ? sanitize_text_field(wp_unslash($_GET['t_rent_notice']))
            : '';

        nocache_headers();
        header('X-Robots-Tag: noindex, nofollow, noarchive', true);
        header('Referrer-Policy: no-referrer', true);
        header('X-Content-Type-Options: nosniff', true);

        $this->render_document($message, true, $token);
        exit;
    }

    private function render_manifest()
    {
        $token = $this->request_token();
        if (!$this->valid_token($token)) {
            status_header(404);
            nocache_headers();
            exit('Manifestet finnes ikke.');
        }

        status_header(200);
        nocache_headers();
        header('Content-Type: application/manifest+json; charset=UTF-8', true);
        header('X-Robots-Tag: noindex, nofollow, noarchive', true);
        header('Referrer-Policy: no-referrer', true);
        header('X-Content-Type-Options: nosniff', true);

        echo wp_json_encode(
            $this->manifest_data($token),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        exit;
    }

    private function manifest_data($token)
    {
        return [
            'id'                      => self::app_base_url(),
            'name'                    => 'T-Rent Utstyrskontroll',
            'short_name'              => 'T-Rent',
            'description'             => 'Utstyrskontroll og manuell datoblokkering for T-Rent.',
            'lang'                    => 'nb',
            'start_url'               => $this->public_url($token),
            'scope'                   => self::app_base_url(),
            'display'                 => 'standalone',
            'display_override'        => ['standalone', 'minimal-ui'],
            'background_color'        => '#f3f4f6',
            'theme_color'             => '#111827',
            'orientation'             => 'any',
            'prefer_related_applications' => false,
            'icons'                   => [
                [
                    'src'     => $this->app_icon_url(192),
                    'sizes'   => '192x192',
                    'type'    => 'image/png',
                    'purpose' => 'any maskable',
                ],
                [
                    'src'     => $this->app_icon_url(512),
                    'sizes'   => '512x512',
                    'type'    => 'image/png',
                    'purpose' => 'any maskable',
                ],
            ],
            'shortcuts'               => [
                [
                    'name'      => 'Utstyrskontroll',
                    'short_name'=> 'Utstyr',
                    'url'       => $this->public_url($token),
                    'icons'     => [
                        [
                            'src'   => $this->app_icon_url(192),
                            'sizes' => '192x192',
                        ],
                    ],
                ],
                [
                    'name'      => 'Blokker dato',
                    'short_name'=> 'Blokker dato',
                    'url'       => Date_Blocker::public_url($token),
                    'icons'     => [
                        [
                            'src'   => $this->app_icon_url(192),
                            'sizes' => '192x192',
                        ],
                    ],
                ],
            ],
        ];
    }

    private function render_service_worker()
    {
        status_header(200);
        nocache_headers();
        header('Content-Type: application/javascript; charset=UTF-8', true);
        header('Service-Worker-Allowed: ' . $this->app_scope_path(), true);
        header('X-Content-Type-Options: nosniff', true);

        echo $this->service_worker_source();
        exit;
    }

    private function service_worker_source()
    {
        $version = defined('T_RENT_ADMIN_APP_VERSION')
            ? T_RENT_ADMIN_APP_VERSION
            : '2.3.0';

        return "'use strict';\n"
            . 'const T_RENT_APP_VERSION = ' . wp_json_encode((string) $version) . ";\n"
            . "self.addEventListener('install', function () {\n"
            . "    self.skipWaiting();\n"
            . "});\n"
            . "self.addEventListener('activate', function (event) {\n"
            . "    event.waitUntil(self.clients.claim());\n"
            . "});\n"
            . "self.addEventListener('fetch', function (event) {\n"
            . "    if (event.request.method !== 'GET') {\n"
            . "        return;\n"
            . "    }\n"
            . "    event.respondWith(fetch(event.request));\n"
            . "});\n";
    }

    private function app_icon_url($size)
    {
        $plugin_file = defined('T_RENT_ADMIN_APP_FILE')
            ? T_RENT_ADMIN_APP_FILE
            : dirname(__DIR__) . '/t-rent-admin-app.php';

        return plugins_url('assets/icon-' . absint($size) . '.png', $plugin_file);
    }

    private function manifest_url($token)
    {
        return add_query_arg(
            self::PUBLIC_TOKEN_PARAM,
            $token,
            self::app_base_url() . 'manifest.webmanifest'
        );
    }

    private function service_worker_url()
    {
        $version = defined('T_RENT_ADMIN_APP_VERSION')
            ? T_RENT_ADMIN_APP_VERSION
            : '2.3.0';

        return add_query_arg(
            'version',
            rawurlencode((string) $version),
            self::app_base_url() . 'sw.js'
        );
    }

    private function app_scope_path()
    {
        $path = parse_url(self::app_base_url(), PHP_URL_PATH);
        return '/' . trim((string) $path, '/') . '/';
    }

    private function request_path()
    {
        $request_uri = isset($_SERVER['REQUEST_URI']) && is_scalar($_SERVER['REQUEST_URI'])
            ? (string) $_SERVER['REQUEST_URI']
            : '';
        $path = parse_url($request_uri, PHP_URL_PATH);

        return '/' . ltrim((string) $path, '/');
    }

    private function is_app_page_request()
    {
        return rtrim($this->request_path(), '/') === rtrim($this->app_scope_path(), '/');
    }

    private function is_manifest_request()
    {
        return $this->request_path() === $this->app_scope_path() . 'manifest.webmanifest';
    }

    private function is_service_worker_request()
    {
        return $this->request_path() === $this->app_scope_path() . 'sw.js';
    }

    private function is_legacy_public_get()
    {
        return isset($_SERVER['REQUEST_METHOD'])
            && strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'GET'
            && isset($_GET[self::PUBLIC_QUERY_VAR])
            && !$this->is_app_page_request();
    }

    public function render_admin_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Du har ikke tilgang til denne siden.', 'redq-rental'));
        }

        if (!$this->woocommerce_ready()) {
            echo '<div class="wrap"><h1>Utstyrskontroll</h1><p>WooCommerce er ikke tilgjengelig.</p></div>';
            return;
        }

        $this->ensure_public_token();

        $message = '';

        if (isset($_POST['t_rent_regenerate_control_link'])) {
            check_admin_referer('t_rent_regenerate_control_link');
            $token = wp_generate_password(48, false, false);
            update_option(self::TOKEN_OPTION, $token, false);
            wp_safe_redirect(add_query_arg(
                't_rent_notice',
                'Ny ekstern lenke er opprettet. Den gamle lenken virker ikke lenger.',
                admin_url('admin.php?page=' . self::PAGE_SLUG)
            ));
            exit;
        } elseif ($this->is_status_post()) {
            $message = $this->handle_status_action(false, '');
            $redirect_url = add_query_arg(
                't_rent_notice',
                $message,
                admin_url('admin.php?page=' . self::PAGE_SLUG)
            );
            wp_safe_redirect($this->add_posted_dashboard_filters($redirect_url));
            exit;
        } elseif (isset($_GET['t_rent_notice'])) {
            $message = sanitize_text_field(wp_unslash($_GET['t_rent_notice']));
        }

        $token = (string) get_option(self::TOKEN_OPTION, '');
        $external_url = $this->public_url($token);

        echo '<div class="wrap">';
        echo '<h1>Utstyrskontroll</h1>';
        echo '<p>Kontrollstatus etter hver utleie. WooCommerce-returer blir automatisk satt til kontroll når returtiden er passert.</p>';
        echo '<div style="max-width:960px;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px;margin:16px 0 22px">';
        echo '<strong>Direktelenke uten WordPress-innlogging</strong>';
        echo '<p style="margin:8px 0"><input type="text" readonly value="' . esc_attr($external_url) . '" style="width:100%;font-family:monospace" onclick="this.select()"></p>';
        echo '<p style="margin:0"><a class="button button-primary" target="_blank" rel="noreferrer" href="' . esc_url($external_url) . '">Åpne kontrollsiden</a></p>';
        echo '<form method="post" style="margin-top:14px">';
        wp_nonce_field('t_rent_regenerate_control_link');
        echo '<button class="button" type="submit" name="t_rent_regenerate_control_link" value="1" onclick="return confirm(\'Den gamle bokmerkelen vil slutte å virke. Fortsette?\')">Lag ny sikker lenke</button>';
        echo '</form>';
        echo '</div>';

        $this->render_dashboard($message, false, '');
        echo '</div>';
    }

    private function render_document($message, $public, $token)
    {
        $calendar_view = Date_Blocker::is_public_view();
        $page_title = $calendar_view ? 'Blokker dato' : 'Utstyrskontroll';
        $refresh_url = $calendar_view
            ? Date_Blocker::public_url($token)
            : $this->public_url($token);
        $version = defined('T_RENT_ADMIN_APP_VERSION')
            ? T_RENT_ADMIN_APP_VERSION
            : '2.3.0';

        status_header(200);
        header('Content-Type: text/html; charset=' . get_bloginfo('charset'));

        echo '<!doctype html><html lang="no"><head>';
        echo '<meta charset="' . esc_attr(get_bloginfo('charset')) . '">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<meta name="robots" content="noindex,nofollow,noarchive">';
        echo '<meta name="theme-color" content="#111827">';
        echo '<meta name="application-name" content="T-Rent">';
        echo '<meta name="mobile-web-app-capable" content="yes">';
        echo '<meta name="apple-mobile-web-app-capable" content="yes">';
        echo '<meta name="apple-mobile-web-app-status-bar-style" content="default">';
        echo '<meta name="apple-mobile-web-app-title" content="T-Rent">';
        echo '<link rel="manifest" href="' . esc_url($this->manifest_url($token)) . '">';
        echo '<link rel="icon" type="image/png" sizes="192x192" href="' . esc_url($this->app_icon_url(192)) . '">';
        echo '<link rel="apple-touch-icon" href="' . esc_url($this->app_icon_url(192)) . '">';
        echo '<title>' . esc_html($page_title) . ' – T-Rent</title>';
        echo '</head><body style="margin:0;background:#f3f4f6;color:#111827;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Arial,sans-serif">';
        echo '<main style="max-width:1180px;margin:0 auto;padding:20px 14px 48px">';
        echo '<header style="display:flex;align-items:end;justify-content:space-between;gap:12px;margin-bottom:18px">';
        echo '<div><div style="font-size:13px;font-weight:700;letter-spacing:.08em;color:#6b7280">T-RENT · APP ' . esc_html($version) . '</div>';
        echo '<h1 style="font-size:28px;line-height:1.15;margin:4px 0">' . esc_html($page_title) . '</h1>';
        echo '<div style="color:#6b7280">Sist oppdatert ' . esc_html(wp_date('d.m.Y H:i')) . '</div></div>';
        echo '<div class="tr-app-header-actions">';
        echo '<button type="button" id="tr-install-app" class="tr-install-app" hidden>Installer app</button>';
        echo '<a href="' . esc_url($refresh_url) . '" style="color:#1d4ed8;text-decoration:none;font-weight:600">Oppdater</a>';
        echo '</div>';
        echo '</header>';

        echo '<style>';
        echo '.tr-app-header-actions{display:flex;align-items:center;gap:10px}.tr-install-app{border:0;border-radius:7px;background:#111827;color:#fff;cursor:pointer;font:inherit;font-size:14px;font-weight:700;padding:9px 12px}.tr-app-tabs{display:grid;grid-template-columns:1fr 1fr;gap:8px;background:#e5e7eb;border-radius:10px;padding:4px;margin:0 0 18px}.tr-app-tab{display:block;border-radius:7px;color:#4b5563;font-size:14px;font-weight:700;padding:11px 10px;text-align:center;text-decoration:none}.tr-app-tab-active{background:#fff;color:#111827;box-shadow:0 1px 2px rgba(0,0,0,.08)}@media(max-width:520px){.tr-app-header-actions{align-items:flex-end;flex-direction:column-reverse;gap:8px}.tr-install-app{padding:8px 10px}}';
        echo '</style>';
        echo '<nav class="tr-app-tabs" aria-label="T-Rent administrasjon">';
        echo '<a class="tr-app-tab' . ($calendar_view ? '' : ' tr-app-tab-active') . '" href="' . esc_url($this->public_url($token)) . '">Utstyrskontroll</a>';
        echo '<a class="tr-app-tab' . ($calendar_view ? ' tr-app-tab-active' : '') . '" href="' . esc_url(Date_Blocker::public_url($token)) . '">Blokker dato</a>';
        echo '</nav>';

        if ($calendar_view) {
            Date_Blocker::render_public($token);
        } else {
            $this->render_dashboard($message, $public, $token);
        }

        $this->render_install_script();
        echo '</main></body></html>';
    }

    private function render_install_script()
    {
        $service_worker_url = $this->service_worker_url();
        $scope = self::app_base_url();
        $version = defined('T_RENT_ADMIN_APP_VERSION')
            ? T_RENT_ADMIN_APP_VERSION
            : '2.3.0';
        ?>
        <script>
            (function () {
                var installButton = document.getElementById('tr-install-app');
                var installPrompt = null;
                var standalone = window.matchMedia('(display-mode: standalone)').matches
                    || window.navigator.standalone === true;

                window.addEventListener('beforeinstallprompt', function (event) {
                    event.preventDefault();
                    installPrompt = event;

                    if (installButton && !standalone) {
                        installButton.hidden = false;
                    }
                });

                if (installButton) {
                    installButton.addEventListener('click', function () {
                        if (!installPrompt) {
                            return;
                        }

                        installPrompt.prompt();
                        installPrompt.userChoice.then(function () {
                            installPrompt = null;
                            installButton.hidden = true;
                        });
                    });
                }

                window.addEventListener('appinstalled', function () {
                    if (installButton) {
                        installButton.hidden = true;
                    }
                });

                if (!('serviceWorker' in navigator)) {
                    return;
                }

                window.addEventListener('load', function () {
                    navigator.serviceWorker.register(
                        <?php echo wp_json_encode($service_worker_url, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>,
                        {scope: <?php echo wp_json_encode($scope, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>}
                    ).then(function () {
                        return navigator.serviceWorker.ready;
                    }).then(function () {
                        if (navigator.serviceWorker.controller) {
                            return;
                        }

                        var reloadKey = 't-rent-pwa-ready-<?php echo esc_js($version); ?>';
                        try {
                            if (window.sessionStorage.getItem(reloadKey)) {
                                return;
                            }
                            window.sessionStorage.setItem(reloadKey, '1');
                        } catch (error) {
                            return;
                        }

                        window.setTimeout(function () {
                            window.location.reload();
                        }, 250);
                    }).catch(function () {
                        // Chrome's menu still offers a normal shortcut if service workers are unavailable.
                    });
                });
            }());
        </script>
        <?php
    }

    private function render_dashboard($message, $public, $token)
    {
        $rows = $this->equipment_rows();
        $filter = $this->requested_status_filter();
        $search = $this->requested_search_query();

        $searched_rows = $rows;
        if ($search !== '') {
            $searched_rows = array_values(array_filter($rows, function ($row) use ($search) {
                return $this->equipment_matches_search($row, $search);
            }));
        }

        $counts = [
            'pending'     => 0,
            'service'     => 0,
            'maintenance' => 0,
            'out'         => 0,
            'ready'       => 0,
        ];

        foreach ($searched_rows as $row) {
            if (isset($counts[$row['display_status']])) {
                $counts[$row['display_status']]++;
            }
        }

        $visible_rows = $searched_rows;
        if ($filter !== '') {
            $visible_rows = array_values(array_filter($searched_rows, function ($row) use ($filter) {
                return isset($row['display_status']) && $row['display_status'] === $filter;
            }));
        }

        if ($message !== '') {
            echo '<div style="max-width:1180px;background:#ecfdf5;border:1px solid #6ee7b7;color:#065f46;border-radius:8px;padding:12px 14px;margin:0 0 16px;font-weight:600">' . esc_html($message) . '</div>';
        }

        echo '<style>';
        echo '.tr-search{display:grid;grid-template-columns:minmax(0,1fr) auto auto;gap:8px;align-items:end;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px;margin:0 0 18px}.tr-search-field label{display:block;font-size:13px;font-weight:700;margin:0 0 6px}.tr-search-field input{box-sizing:border-box;width:100%;min-height:44px;border:1px solid #9ca3af;border-radius:7px;background:#fff;color:#111827;font:inherit;padding:8px 11px}.tr-search-button{min-height:44px;border:0;border-radius:7px;background:#1d4ed8;color:#fff;cursor:pointer;font:inherit;font-weight:700;padding:9px 18px}.tr-search-clear{display:flex;align-items:center;min-height:44px;color:#1d4ed8;font-size:14px;font-weight:700;text-decoration:none;padding:0 6px}.tr-counts{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px;margin:0 0 18px}.tr-count-link{display:block;color:inherit;text-decoration:none;border-radius:9px;outline:none}.tr-count{background:#fff;border:1px solid #e5e7eb;border-radius:9px;padding:13px;transition:transform .12s ease,box-shadow .12s ease}.tr-count-link:active .tr-count{transform:scale(.98)}.tr-count-link:focus-visible .tr-count,.tr-count-active{box-shadow:0 0 0 3px rgba(37,99,235,.18)}.tr-count b{display:block;font-size:25px}.tr-count span{color:#6b7280;font-size:13px}.tr-filterbar{display:flex;align-items:center;justify-content:space-between;gap:12px;background:#fff;border:1px solid #e5e7eb;border-radius:9px;padding:10px 12px;margin:-6px 0 18px}.tr-filterbar strong{font-size:14px}.tr-filterbar a{font-size:14px;font-weight:700;color:#1d4ed8;text-decoration:none}.tr-table{width:100%;border-collapse:separate;border-spacing:0;background:#fff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden}.tr-table th,.tr-table td{text-align:left;padding:12px 10px;border-bottom:1px solid #e5e7eb;vertical-align:top}.tr-table th{background:#f9fafb;font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#6b7280}.tr-table tr:last-child td{border-bottom:0}.tr-badge{display:inline-block;border-radius:999px;padding:5px 9px;font-size:12px;font-weight:700;white-space:nowrap}.tr-badge-pending{background:#fff7ed;color:#9a3412}.tr-badge-service{background:#f5f3ff;color:#6d28d9}.tr-badge-maintenance{background:#fef2f2;color:#991b1b}.tr-badge-out{background:#eff6ff;color:#1e40af}.tr-badge-ready{background:#ecfdf5;color:#065f46}.tr-small{font-size:12px;line-height:1.45;color:#6b7280}.tr-service-history{margin-top:14px;padding-top:12px;border-top:1px solid #e5e7eb}.tr-subhead{margin-bottom:4px;font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#9ca3af}.tr-actions{min-width:310px}.tr-form{display:grid;grid-template-columns:minmax(120px,1fr) auto auto;gap:6px;align-items:center}.tr-form input[type=text]{min-width:0;border:1px solid #d1d5db;border-radius:6px;padding:8px}.tr-btn{border:0;border-radius:6px;padding:8px 10px;font-weight:700;cursor:pointer}.tr-ready{background:#047857;color:#fff}.tr-hold{background:#fee2e2;color:#991b1b}.tr-service{background:#ede9fe;color:#5b21b6}.tr-reset{background:#ffedd5;color:#9a3412}.tr-empty{background:#fff;border:1px solid #e5e7eb;border-radius:9px;padding:28px;text-align:center;color:#6b7280}@media(max-width:800px){.tr-counts{grid-template-columns:repeat(2,minmax(0,1fr))}.tr-table,.tr-table tbody,.tr-table tr,.tr-table td{display:block}.tr-table thead{display:none}.tr-table tr{border-bottom:7px solid #f3f4f6;padding:7px 0}.tr-table td{border:0;padding:6px 12px}.tr-table td:before{content:attr(data-label);display:block;font-size:11px;font-weight:700;text-transform:uppercase;color:#9ca3af;margin-bottom:2px}.tr-actions{min-width:0}.tr-form{grid-template-columns:1fr 1fr}.tr-form input[type=text]{grid-column:1/-1}.tr-form .tr-wide{grid-column:1/-1}}@media(max-width:560px){.tr-search{grid-template-columns:1fr 1fr}.tr-search-field{grid-column:1/-1}.tr-search-clear{justify-content:center}}';
        echo '</style>';

        $this->render_search_form($public, $token, $filter, $search);

        echo '<div class="tr-counts">';
        $this->count_card($counts['pending'], 'Må kontrolleres', '#f97316', 'pending', $filter, $search, $public, $token);
        $this->count_card($counts['service'], 'Service', '#7c3aed', 'service', $filter, $search, $public, $token);
        $this->count_card($counts['maintenance'], 'Ikke klar', '#dc2626', 'maintenance', $filter, $search, $public, $token);
        $this->count_card($counts['out'], 'Ute nå', '#2563eb', 'out', $filter, $search, $public, $token);
        $this->count_card($counts['ready'], 'Kontrollert og klar', '#059669', 'ready', $filter, $search, $public, $token);
        echo '</div>';

        if ($filter !== '' || $search !== '') {
            $clear_url = $public
                ? $this->public_url($token)
                : admin_url('admin.php?page=' . self::PAGE_SLUG);

            $filter_description = [];
            if ($search !== '') {
                $filter_description[] = 'søk «' . $search . '»';
            }
            if ($filter !== '') {
                $filter_description[] = $this->status_label($filter);
            }

            echo '<div class="tr-filterbar">';
            echo '<strong>Viser ' . esc_html(implode(' · ', $filter_description)) . ' (' . esc_html(count($visible_rows)) . ' treff)</strong>';
            echo '<a href="' . esc_url($clear_url) . '">Vis alle</a>';
            echo '</div>';
        }

        if (empty($rows)) {
            echo '<div class="tr-empty">Ingen RnB-utstyr ble funnet.</div>';
            return;
        }

        if (empty($searched_rows)) {
            echo '<div class="tr-empty">Ingen utstyr samsvarer med søket «' . esc_html($search) . '».</div>';
            return;
        }

        if (empty($visible_rows)) {
            echo '<div class="tr-empty">Ingen treff har denne statusen.</div>';
            return;
        }

        echo '<table class="tr-table"><thead><tr>';
        echo '<th>Utstyr</th><th>Status</th><th>Siste / pågående leie</th><th>Sist kontrollert</th><th>Handling</th>';
        echo '</tr></thead><tbody>';

        foreach ($visible_rows as $row) {
            $inventory_id = (int) $row['inventory_id'];
            $status = $row['display_status'];
            echo '<tr>';
            echo '<td data-label="Utstyr"><strong>' . esc_html($row['name']) . '</strong>';
            echo '<div class="tr-small">Utstyrs-ID: #' . esc_html($inventory_id) . '</div>';
            if ($row['quantity'] > 1) {
                echo '<div class="tr-small">Antall i denne lagerposten: ' . esc_html($row['quantity']) . '</div>';
            }
            echo '</td>';
            echo '<td data-label="Status"><span class="tr-badge tr-badge-' . esc_attr($status) . '">' . esc_html($this->status_label($status)) . '</span>';
            if ($row['reason'] !== '') {
                echo '<div class="tr-small" style="margin-top:5px">' . esc_html($row['reason']) . '</div>';
            }
            echo '</td>';
            echo '<td data-label="Siste / pågående leie">' . $this->booking_summary($row, $public) . '</td>';
            echo '<td data-label="Sist kontrollert">';
            if ($row['checked_at'] !== '') {
                echo '<strong>' . esc_html($this->format_datetime($row['checked_at'])) . '</strong>';
                if ($row['checked_by'] !== '') {
                    echo '<div class="tr-small">av ' . esc_html($row['checked_by']) . '</div>';
                }
                if ($row['checked_note'] !== '') {
                    echo '<div class="tr-small">' . esc_html($row['checked_note']) . '</div>';
                }
            } else {
                echo '<span class="tr-small">Ingen kontroll registrert</span>';
            }

            echo '<div class="tr-service-history">';
            echo '<div class="tr-subhead">Sist service</div>';
            if ($row['service_at'] !== '') {
                echo '<strong>' . esc_html($this->format_datetime($row['service_at'])) . '</strong>';
                if ($row['service_by'] !== '') {
                    echo '<div class="tr-small">av ' . esc_html($row['service_by']) . '</div>';
                }
                if ($row['service_note'] !== '') {
                    echo '<div class="tr-small">' . esc_html($row['service_note']) . '</div>';
                }
            } else {
                echo '<span class="tr-small">Ingen service registrert</span>';
            }
            echo '</div>';

            echo '</td>';
            echo '<td data-label="Handling" class="tr-actions">';
            $this->render_action_form($inventory_id, $status, $public, $token, $filter, $search);
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '<p class="tr-small" style="margin:12px 2px 0">«Kontrollert og klar» må registreres etter fysisk kontroll. Etter neste WooCommerce-retur endres status automatisk til «Må kontrolleres». For Hygglo eller annen manuell utleie bruker du «Sett til kontroll» når utstyret kommer tilbake.</p>';
    }

    private function render_search_form($public, $token, $filter, $search)
    {
        $action_url = $public
            ? self::app_base_url()
            : admin_url('admin.php');
        $clear_search_url = $public
            ? $this->public_url($token)
            : admin_url('admin.php?page=' . self::PAGE_SLUG);

        if ($filter !== '') {
            $clear_search_url = add_query_arg('t_rent_status', $filter, $clear_search_url);
        }

        echo '<form class="tr-search" method="get" action="' . esc_url($action_url) . '" role="search">';
        if ($public) {
            echo '<input type="hidden" name="' . esc_attr(self::PUBLIC_QUERY_VAR) . '" value="1">';
            echo '<input type="hidden" name="' . esc_attr(self::PUBLIC_TOKEN_PARAM) . '" value="' . esc_attr($token) . '">';
        } else {
            echo '<input type="hidden" name="page" value="' . esc_attr(self::PAGE_SLUG) . '">';
        }
        if ($filter !== '') {
            echo '<input type="hidden" name="t_rent_status" value="' . esc_attr($filter) . '">';
        }

        echo '<div class="tr-search-field">';
        echo '<label for="t-rent-equipment-search">Søk etter utstyr</label>';
        echo '<input id="t-rent-equipment-search" type="search" name="' . esc_attr(self::SEARCH_PARAM) . '" value="' . esc_attr($search) . '" maxlength="100" placeholder="Navn, utstyrs-ID eller ordrenummer" autocomplete="off" enterkeyhint="search">';
        echo '</div>';
        echo '<button class="tr-search-button" type="submit">Søk</button>';
        if ($search !== '') {
            echo '<a class="tr-search-clear" href="' . esc_url($clear_search_url) . '">Tøm søk</a>';
        }
        echo '</form>';
    }

    private function requested_status_filter()
    {
        $filter = isset($_GET['t_rent_status']) && is_scalar($_GET['t_rent_status'])
            ? sanitize_key(wp_unslash($_GET['t_rent_status']))
            : '';

        return in_array($filter, ['pending', 'service', 'maintenance', 'out', 'ready'], true)
            ? $filter
            : '';
    }

    private function requested_search_query()
    {
        $search = isset($_GET[self::SEARCH_PARAM]) && is_scalar($_GET[self::SEARCH_PARAM])
            ? wp_unslash($_GET[self::SEARCH_PARAM])
            : '';

        return $this->sanitize_search_query($search);
    }

    private function sanitize_search_query($search)
    {
        $search = trim(sanitize_text_field((string) $search));

        return function_exists('mb_substr')
            ? mb_substr($search, 0, 100, 'UTF-8')
            : substr($search, 0, 100);
    }

    private function equipment_matches_search($row, $search)
    {
        $values = [
            isset($row['name']) ? $row['name'] : '',
            isset($row['inventory_id']) ? (string) $row['inventory_id'] : '',
            isset($row['inventory_id']) ? 'utstyr ' . $row['inventory_id'] : '',
            isset($row['display_status']) ? $this->status_label($row['display_status']) : '',
            isset($row['reason']) ? $row['reason'] : '',
            isset($row['checked_by']) ? $row['checked_by'] : '',
            isset($row['checked_note']) ? $row['checked_note'] : '',
            isset($row['service_by']) ? $row['service_by'] : '',
            isset($row['service_note']) ? $row['service_note'] : '',
        ];

        foreach (['last_returned', 'active_booking', 'next_booking'] as $booking_key) {
            if (!empty($row[$booking_key]['order_id'])) {
                $values[] = 'ordre ' . absint($row[$booking_key]['order_id']);
                $values[] = '#' . absint($row[$booking_key]['order_id']);
            }
        }

        $haystack = $this->normalize_search_text(implode(' ', $values));
        $needle = $this->normalize_search_text($search);
        $terms = preg_split('/\s+/u', $needle, -1, PREG_SPLIT_NO_EMPTY);

        if (empty($terms)) {
            return true;
        }

        foreach ($terms as $term) {
            if (strpos($haystack, $term) === false) {
                return false;
            }
        }

        return true;
    }

    private function normalize_search_text($value)
    {
        $value = wp_strip_all_tags((string) $value);
        if (function_exists('remove_accents')) {
            $value = remove_accents($value);
        }

        return function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);
    }

    private function add_posted_dashboard_filters($url)
    {
        $args = [];
        $status = isset($_POST['t_rent_return_status']) && is_scalar($_POST['t_rent_return_status'])
            ? sanitize_key(wp_unslash($_POST['t_rent_return_status']))
            : '';
        $search = isset($_POST['t_rent_return_search']) && is_scalar($_POST['t_rent_return_search'])
            ? $this->sanitize_search_query(wp_unslash($_POST['t_rent_return_search']))
            : '';

        if (in_array($status, ['pending', 'service', 'maintenance', 'out', 'ready'], true)) {
            $args['t_rent_status'] = $status;
        }
        if ($search !== '') {
            $args[self::SEARCH_PARAM] = $search;
        }

        return empty($args) ? $url : add_query_arg($args, $url);
    }

    private function count_card($number, $label, $color, $status, $current_filter, $search, $public, $token)
    {
        $base_url = $public
            ? $this->public_url($token)
            : admin_url('admin.php?page=' . self::PAGE_SLUG);

        $args = ['t_rent_status' => $status];
        if ($search !== '') {
            $args[self::SEARCH_PARAM] = $search;
        }
        $url = add_query_arg($args, $base_url);
        $active_class = $current_filter === $status ? ' tr-count-active' : '';

        echo '<a class="tr-count-link" href="' . esc_url($url) . '" aria-label="' . esc_attr('Vis ' . $label) . '">';
        echo '<div class="tr-count' . esc_attr($active_class) . '" style="border-top:4px solid ' . esc_attr($color) . '">';
        echo '<b>' . esc_html($number) . '</b><span>' . esc_html($label) . '</span>';
        echo '</div></a>';
    }

    private function render_action_form($inventory_id, $status, $public, $token, $filter, $search)
    {
        $action_url = $public
            ? $this->public_url($token)
            : admin_url('admin.php?page=' . self::PAGE_SLUG);

        echo '<form method="post" action="' . esc_url($action_url) . '" class="tr-form">';
        echo '<input type="hidden" name="t_rent_control_inventory_id" value="' . esc_attr($inventory_id) . '">';
        if ($filter !== '') {
            echo '<input type="hidden" name="t_rent_return_status" value="' . esc_attr($filter) . '">';
        }
        if ($search !== '') {
            echo '<input type="hidden" name="t_rent_return_search" value="' . esc_attr($search) . '">';
        }

        if ($public) {
            echo '<input type="hidden" name="t_rent_control_signature" value="' . esc_attr($this->action_signature($inventory_id, $token)) . '">';
        } else {
            wp_nonce_field('t_rent_equipment_control_' . $inventory_id);
        }

        echo '<input type="text" name="t_rent_control_note" maxlength="240" placeholder="Kort notat (valgfritt)">';

        if ($status !== 'ready') {
            echo '<button class="tr-btn tr-ready" type="submit" name="t_rent_control_action" value="ready">Kontrollert – klar</button>';
        } else {
            echo '<button class="tr-btn tr-reset" type="submit" name="t_rent_control_action" value="pending">Sett til kontroll</button>';
        }

        if ($status !== 'maintenance') {
            echo '<button class="tr-btn tr-hold" type="submit" name="t_rent_control_action" value="maintenance">Ikke klar</button>';
        } else {
            echo '<button class="tr-btn tr-reset" type="submit" name="t_rent_control_action" value="pending">Til ny kontroll</button>';
        }

        if ($status !== 'service') {
            echo '<button class="tr-btn tr-service" type="submit" name="t_rent_control_action" value="service">Service</button>';
        } else {
            echo '<button class="tr-btn tr-reset tr-wide" type="submit" name="t_rent_control_action" value="pending">Til ny kontroll</button>';
        }

        echo '</form>';
    }

    private function booking_summary($row, $public)
    {
        $parts = [];
        $active = $row['active_booking'];
        $last = $row['last_returned'];
        $next = $row['next_booking'];

        if (!empty($active)) {
            $parts[] = '<strong>Ute nå</strong><div class="tr-small">Ordre ' . $this->order_reference($active['order_id'], $public)
                . '<br>Retur ' . esc_html($this->format_timestamp($active['return_ts'])) . '</div>';
        } elseif (!empty($last)) {
            $parts[] = '<strong>Sist returnert</strong><div class="tr-small">Ordre ' . $this->order_reference($last['order_id'], $public)
                . '<br>' . esc_html($this->format_timestamp($last['return_ts'])) . '</div>';
        } else {
            $parts[] = '<span class="tr-small">Ingen tidligere WooCommerce-retur</span>';
        }

        if (!empty($next)) {
            $parts[] = '<div class="tr-small" style="margin-top:5px"><strong>Neste:</strong> ordre ' . $this->order_reference($next['order_id'], $public)
                . ', ' . esc_html($this->format_timestamp($next['start_ts'])) . '</div>';
        }

        return implode('', $parts);
    }

    private function order_reference($order_id, $public)
    {
        $label = '#' . absint($order_id);
        if ($public) {
            return esc_html($label);
        }

        $order = wc_get_order($order_id);
        $url = admin_url('post.php?post=' . absint($order_id) . '&action=edit');
        if ($order && method_exists($order, 'get_edit_order_url')) {
            $url = $order->get_edit_order_url();
        }

        if ($url === '') {
            return esc_html($label);
        }

        return '<a href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
    }

    private function equipment_rows()
    {
        $inventories = get_posts([
            'post_type'      => 'inventory',
            'post_status'    => ['publish', 'private'],
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        if (empty($inventories)) {
            return [];
        }

        $bookings = $this->bookings_by_inventory();
        $rows = [];

        foreach ($inventories as $inventory) {
            $inventory_id = (int) $inventory->ID;
            $stored_status = (string) get_post_meta($inventory_id, self::STATUS_META, true);
            $checked_at = (string) get_post_meta($inventory_id, self::CHECKED_AT_META, true);
            $checked_through = (int) get_post_meta($inventory_id, self::CHECKED_THROUGH_META, true);
            $service_at = (string) get_post_meta($inventory_id, self::SERVICE_AT_META, true);
            $service_by = (string) get_post_meta($inventory_id, self::SERVICE_BY_META, true);
            $service_note = (string) get_post_meta($inventory_id, self::SERVICE_NOTE_META, true);

            // Backward compatibility for service actions recorded before v1.0.3.
            if ($service_at === '') {
                $log = get_post_meta($inventory_id, self::LOG_META, true);
                if (is_array($log)) {
                    foreach ($log as $entry) {
                        if (!is_array($entry) || ($entry['status'] ?? '') !== 'service') {
                            continue;
                        }

                        $service_at = isset($entry['at']) ? (string) $entry['at'] : '';
                        $service_by = isset($entry['by']) ? (string) $entry['by'] : '';
                        $service_note = isset($entry['note']) ? (string) $entry['note'] : '';
                        break;
                    }
                }
            }

            $schedule = isset($bookings[$inventory_id]) ? $bookings[$inventory_id] : $this->empty_schedule();

            if ($stored_status === 'maintenance') {
                $display_status = 'maintenance';
                $reason = 'Registrert som ikke klar for utleie.';
            } elseif ($stored_status === 'service') {
                $display_status = 'service';
                $reason = 'Registrert til service.';
            } elseif ($stored_status === 'pending') {
                $display_status = 'pending';
                $reason = 'Manuelt satt til kontroll.';
            } elseif (
                !empty($schedule['last_returned'])
                && (int) $schedule['last_returned']['return_ts'] > $checked_through
            ) {
                $display_status = 'pending';
                $reason = 'Ny retur etter siste registrerte kontroll.';
            } elseif (!empty($schedule['active_booking'])) {
                $display_status = 'out';
                $reason = 'Blir automatisk satt til kontroll etter retur.';
            } elseif ($stored_status === 'ready' && $checked_at !== '') {
                $display_status = 'ready';
                $reason = '';
            } else {
                $display_status = 'pending';
                $reason = 'Utstyret er ikke grunnkontrollert i systemet ennå.';
            }

            $rows[] = [
                'inventory_id'   => $inventory_id,
                'name'           => $this->equipment_name($inventory),
                'quantity'       => max(1, (int) get_post_meta($inventory_id, 'quantity', true)),
                'display_status' => $display_status,
                'reason'         => $reason,
                'checked_at'     => $checked_at,
                'checked_by'     => (string) get_post_meta($inventory_id, self::CHECKED_BY_META, true),
                'checked_note'   => (string) get_post_meta($inventory_id, self::CHECKED_NOTE_META, true),
                'service_at'     => $service_at,
                'service_by'     => $service_by,
                'service_note'   => $service_note,
                'last_returned'  => $schedule['last_returned'],
                'active_booking' => $schedule['active_booking'],
                'next_booking'   => $schedule['next_booking'],
                'sort_return'    => !empty($schedule['last_returned']) ? (int) $schedule['last_returned']['return_ts'] : 0,
            ];
        }

        $priority = ['pending' => 0, 'service' => 1, 'maintenance' => 2, 'out' => 3, 'ready' => 4];
        usort($rows, function ($a, $b) use ($priority) {
            $a_priority = isset($priority[$a['display_status']]) ? $priority[$a['display_status']] : 9;
            $b_priority = isset($priority[$b['display_status']]) ? $priority[$b['display_status']] : 9;
            if ($a_priority !== $b_priority) {
                return $a_priority - $b_priority;
            }

            if ($a['sort_return'] !== $b['sort_return']) {
                return $b['sort_return'] - $a['sort_return'];
            }

            return strcasecmp($a['name'], $b['name']);
        });

        return $rows;
    }

    private function bookings_by_inventory()
    {
        $results = [];
        $excluded = ['wc-cancelled', 'wc-refunded', 'wc-failed', 'wc-pending', 'wc-checkout-draft', 'wc-rnb-fake-order'];
        $statuses = array_values(array_diff(array_keys(wc_get_order_statuses()), $excluded));

        if (empty($statuses)) {
            return $results;
        }

        $orders = wc_get_orders([
            'limit'   => -1,
            'orderby' => 'date',
            'order'   => 'DESC',
            'status'  => $statuses,
            'return'  => 'objects',
        ]);

        $now = time();

        foreach ($orders as $order) {
            if (!$order || !method_exists($order, 'get_items')) {
                continue;
            }

            foreach ($order->get_items('line_item') as $item_id => $item) {
                if (!$this->is_rental_item($item)) {
                    continue;
                }

                $inventory_id = $this->inventory_id_from_item($item);
                if ($inventory_id <= 0 || get_post_type($inventory_id) !== 'inventory') {
                    continue;
                }

                $period = $this->rental_period_from_item($item);
                if (empty($period['start_ts']) || empty($period['return_ts'])) {
                    continue;
                }

                if (!isset($results[$inventory_id])) {
                    $results[$inventory_id] = $this->empty_schedule();
                }

                $booking = [
                    'order_id'  => (int) $order->get_id(),
                    'item_id'   => (int) $item_id,
                    'start_ts'  => (int) $period['start_ts'],
                    'return_ts' => (int) $period['return_ts'],
                ];

                if ($booking['return_ts'] <= $now) {
                    if (
                        empty($results[$inventory_id]['last_returned'])
                        || $booking['return_ts'] > $results[$inventory_id]['last_returned']['return_ts']
                    ) {
                        $results[$inventory_id]['last_returned'] = $booking;
                    }
                }

                if ($booking['start_ts'] <= $now) {
                    if (
                        empty($results[$inventory_id]['latest_started'])
                        || $booking['start_ts'] > $results[$inventory_id]['latest_started']['start_ts']
                    ) {
                        $results[$inventory_id]['latest_started'] = $booking;
                    }
                }

                if ($booking['start_ts'] <= $now && $booking['return_ts'] > $now) {
                    if (
                        empty($results[$inventory_id]['active_booking'])
                        || $booking['return_ts'] < $results[$inventory_id]['active_booking']['return_ts']
                    ) {
                        $results[$inventory_id]['active_booking'] = $booking;
                    }
                }

                if ($booking['start_ts'] > $now) {
                    if (
                        empty($results[$inventory_id]['next_booking'])
                        || $booking['start_ts'] < $results[$inventory_id]['next_booking']['start_ts']
                    ) {
                        $results[$inventory_id]['next_booking'] = $booking;
                    }
                }
            }
        }

        return $results;
    }

    private function empty_schedule()
    {
        return [
            'last_returned'  => null,
            'latest_started' => null,
            'active_booking' => null,
            'next_booking'   => null,
        ];
    }

    private function is_rental_item($item)
    {
        if (!is_object($item) || !method_exists($item, 'get_product')) {
            return false;
        }

        $product = $item->get_product();
        if ($product && method_exists($product, 'get_type') && $product->get_type() === 'redq_rental') {
            return true;
        }

        foreach (['rnb_hidden_order_meta', '_rnb_hidden_order_meta'] as $key) {
            if (is_array($item->get_meta($key, true))) {
                return true;
            }
        }

        return false;
    }

    private function inventory_id_from_item($item)
    {
        foreach (['booking_inventory', '_booking_inventory'] as $key) {
            $value = $item->get_meta($key, true);
            if (is_numeric($value) && (int) $value > 0) {
                return (int) $value;
            }
        }

        foreach (['rnb_hidden_order_meta', '_rnb_hidden_order_meta'] as $key) {
            $data = $item->get_meta($key, true);
            if (!is_array($data)) {
                continue;
            }

            $posted = isset($data['posted_data']) && is_array($data['posted_data']) ? $data['posted_data'] : [];
            foreach ([$data, $posted] as $source) {
                foreach (['booking_inventory', 'inventory_id'] as $field) {
                    if (isset($source[$field]) && is_numeric($source[$field]) && (int) $source[$field] > 0) {
                        return (int) $source[$field];
                    }
                }
            }
        }

        $product_id = method_exists($item, 'get_product_id') ? (int) $item->get_product_id() : 0;
        if ($product_id > 0 && function_exists('rnb_get_product_inventory_id')) {
            $ids = rnb_get_product_inventory_id($product_id);
            if (is_array($ids) && !empty($ids)) {
                return (int) reset($ids);
            }
        }

        return 0;
    }

    private function rental_period_from_item($item)
    {
        $data_sources = [];
        foreach (['rnb_hidden_order_meta', '_rnb_hidden_order_meta'] as $key) {
            $data = $item->get_meta($key, true);
            if (is_array($data)) {
                $data_sources[] = $data;
                if (isset($data['posted_data']) && is_array($data['posted_data'])) {
                    $data_sources[] = $data['posted_data'];
                }
            }
        }

        $start_date = '';
        $start_time = '';
        $return_date = '';
        $return_time = '';

        foreach ($data_sources as $data) {
            if ($start_date === '' && !empty($data['pickup_date'])) {
                $start_date = $data['pickup_date'];
            }
            if ($start_time === '' && !empty($data['pickup_time'])) {
                $start_time = $data['pickup_time'];
            }
            if ($return_date === '') {
                if (!empty($data['dropoff_date'])) {
                    $return_date = $data['dropoff_date'];
                } elseif (!empty($data['return_date'])) {
                    $return_date = $data['return_date'];
                }
            }
            if ($return_time === '') {
                if (!empty($data['dropoff_time'])) {
                    $return_time = $data['dropoff_time'];
                } elseif (!empty($data['return_time'])) {
                    $return_time = $data['return_time'];
                }
            }
        }

        if ($start_date === '') {
            $hidden = $this->first_item_meta($item, ['_pickup_hidden_datetime', 'pickup_hidden_datetime']);
            list($start_date, $start_time) = $this->split_hidden_datetime($hidden, $start_time);
        }
        if ($return_date === '') {
            $hidden = $this->first_item_meta($item, ['_return_hidden_datetime', 'return_hidden_datetime']);
            list($return_date, $return_time) = $this->split_hidden_datetime($hidden, $return_time);
        }

        if ($start_date === '') {
            $start_date = $this->first_item_meta($item, ['Første leiedag', 'pickup_date']);
        }
        if ($return_date === '') {
            $return_date = $this->first_item_meta($item, ['Siste leiedag', 'dropoff_date', 'return_date']);
        }

        return [
            'start_ts'  => $this->parse_datetime($start_date, $start_time, '08:00'),
            'return_ts' => $this->parse_datetime($return_date, $return_time, '20:00'),
        ];
    }

    private function first_item_meta($item, $keys)
    {
        foreach ($keys as $key) {
            $value = $item->get_meta($key, true);
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return '';
    }

    private function split_hidden_datetime($value, $fallback_time)
    {
        if (!is_scalar($value) || trim((string) $value) === '') {
            return ['', $fallback_time];
        }

        $parts = explode('|', trim((string) $value), 2);
        $date = $parts[0];
        $time = isset($parts[1]) && trim($parts[1]) !== '' ? trim($parts[1]) : $fallback_time;
        return [$date, $time];
    }

    private function parse_datetime($date, $time, $default_time)
    {
        if (!is_scalar($date) || trim((string) $date) === '') {
            return 0;
        }

        $date = trim((string) $date);
        if (strpos($date, '|') !== false) {
            list($date_part, $pipe_time) = array_pad(explode('|', $date, 2), 2, '');
            $date = trim($date_part);
            if (trim((string) $time) === '' && trim($pipe_time) !== '') {
                $time = trim($pipe_time);
            }
        }

        $time = is_scalar($time) && trim((string) $time) !== '' ? trim((string) $time) : $default_time;
        $timezone = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone('UTC');
        $value = $date . ' ' . $time;
        $formats = [
            'Y-m-d H:i:s', 'Y-m-d H:i', 'Y/m/d H:i:s', 'Y/m/d H:i',
            'd.m.Y H:i:s', 'd.m.Y H:i', 'd/m/Y H:i:s', 'd/m/Y H:i',
            'm/d/Y H:i:s', 'm/d/Y H:i',
        ];

        foreach ($formats as $format) {
            $parsed = \DateTimeImmutable::createFromFormat('!' . $format, $value, $timezone);
            if ($parsed instanceof \DateTimeImmutable && $parsed->format($format) === $value) {
                return $parsed->getTimestamp();
            }
        }

        try {
            return (new \DateTimeImmutable($value, $timezone))->getTimestamp();
        } catch (\Exception $exception) {
            return 0;
        }
    }

    private function handle_status_action($public, $token)
    {
        if (!$this->is_status_post()) {
            return '';
        }

        $inventory_id = isset($_POST['t_rent_control_inventory_id'])
            ? absint($_POST['t_rent_control_inventory_id'])
            : 0;
        $action = sanitize_key(wp_unslash($_POST['t_rent_control_action']));

        if ($inventory_id <= 0 || get_post_type($inventory_id) !== 'inventory') {
            return 'Kunne ikke finne utstyret.';
        }

        if (!in_array($action, ['ready', 'pending', 'service', 'maintenance'], true)) {
            return 'Ugyldig kontrollstatus.';
        }

        if ($public) {
            $provided = isset($_POST['t_rent_control_signature'])
                ? (string) wp_unslash($_POST['t_rent_control_signature'])
                : '';
            if (!hash_equals($this->action_signature($inventory_id, $token), $provided)) {
                return 'Sikkerhetskontrollen feilet. Oppdater siden og prøv igjen.';
            }
            $actor = 'Ekstern kontrollside';
        } else {
            if (!current_user_can('manage_woocommerce')) {
                return 'Du har ikke tilgang til å endre kontrollstatus.';
            }
            check_admin_referer('t_rent_equipment_control_' . $inventory_id);
            $user = wp_get_current_user();
            $actor = $user && $user->exists() ? $user->display_name : 'WordPress-administrator';
        }

        $note = isset($_POST['t_rent_control_note'])
            ? sanitize_text_field(wp_unslash($_POST['t_rent_control_note']))
            : '';
        $now_mysql = wp_date('c');
        $covered_return = (int) get_post_meta($inventory_id, self::CHECKED_THROUGH_META, true);

        if ($action === 'ready') {
            $schedule = $this->bookings_by_inventory();
            if (!empty($schedule[$inventory_id]['latest_started'])) {
                $covered_return = max(
                    $covered_return,
                    (int) $schedule[$inventory_id]['latest_started']['return_ts']
                );
            } else {
                $covered_return = max($covered_return, time());
            }

            update_post_meta($inventory_id, self::STATUS_META, 'ready');
            update_post_meta($inventory_id, self::CHECKED_AT_META, $now_mysql);
            update_post_meta($inventory_id, self::CHECKED_BY_META, $actor);
            update_post_meta($inventory_id, self::CHECKED_NOTE_META, $note);
            update_post_meta($inventory_id, self::CHECKED_THROUGH_META, $covered_return);
            $message = get_the_title($inventory_id) . ' er registrert som kontrollert og klar for utleie.';
        } elseif ($action === 'maintenance') {
            update_post_meta($inventory_id, self::STATUS_META, 'maintenance');
            update_post_meta($inventory_id, self::CHECKED_BY_META, $actor);
            update_post_meta($inventory_id, self::CHECKED_NOTE_META, $note);
            $message = get_the_title($inventory_id) . ' er registrert som ikke klar for utleie.';
        } elseif ($action === 'service') {
            update_post_meta($inventory_id, self::STATUS_META, 'service');
            update_post_meta($inventory_id, self::SERVICE_AT_META, $now_mysql);
            update_post_meta($inventory_id, self::SERVICE_BY_META, $actor);
            update_post_meta($inventory_id, self::SERVICE_NOTE_META, $note);
            $message = get_the_title($inventory_id) . ' er satt til service.';
        } else {
            update_post_meta($inventory_id, self::STATUS_META, 'pending');
            update_post_meta($inventory_id, self::CHECKED_BY_META, $actor);
            update_post_meta($inventory_id, self::CHECKED_NOTE_META, $note);
            $message = get_the_title($inventory_id) . ' er satt til kontroll.';
        }

        $this->append_log($inventory_id, [
            'status'          => $action,
            'at'              => $now_mysql,
            'by'              => $actor,
            'note'            => $note,
            'covered_return'  => $covered_return,
        ]);

        return $message;
    }

    private function append_log($inventory_id, $entry)
    {
        $log = get_post_meta($inventory_id, self::LOG_META, true);
        if (!is_array($log)) {
            $log = [];
        }

        array_unshift($log, $entry);
        $log = array_slice($log, 0, 50);
        update_post_meta($inventory_id, self::LOG_META, $log);
    }

    private function equipment_name($inventory)
    {
        global $wpdb;

        $inventory_name = trim(wp_strip_all_tags(get_the_title($inventory)));
        $table = $wpdb->prefix . 'rnb_inventory_product';
        $product_ids = $wpdb->get_col(
            $wpdb->prepare("SELECT product FROM {$table} WHERE inventory = %d", (int) $inventory->ID)
        );

        $product_names = [];
        foreach ((array) $product_ids as $product_id) {
            $name = trim(wp_strip_all_tags(get_the_title((int) $product_id)));
            if ($name !== '') {
                $product_names[] = $name;
            }
        }
        $product_names = array_values(array_unique($product_names));

        if (empty($product_names)) {
            return $inventory_name !== '' ? $inventory_name : 'Utstyr #' . (int) $inventory->ID;
        }

        $product_label = implode(', ', $product_names);
        if ($inventory_name === '' || strcasecmp($inventory_name, $product_label) === 0) {
            return $product_label;
        }

        return $product_label . ' – ' . $inventory_name;
    }

    private function status_label($status)
    {
        $labels = [
            'pending'     => 'Må kontrolleres',
            'service'     => 'Service',
            'maintenance' => 'Ikke klar',
            'out'         => 'Ute på leie',
            'ready'       => 'Kontrollert og klar',
        ];

        return isset($labels[$status]) ? $labels[$status] : 'Ukjent';
    }

    private function format_timestamp($timestamp)
    {
        return wp_date('d.m.Y H:i', (int) $timestamp);
    }

    private function format_datetime($datetime)
    {
        $timestamp = strtotime($datetime);
        return $timestamp ? wp_date('d.m.Y H:i', $timestamp) : $datetime;
    }

    private function is_public_request()
    {
        return isset($_GET[self::PUBLIC_QUERY_VAR])
            || isset($_POST[self::PUBLIC_QUERY_VAR])
            || $this->is_app_page_request();
    }

    private function is_status_post()
    {
        return isset($_SERVER['REQUEST_METHOD'])
            && strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'POST'
            && isset($_POST['t_rent_control_action'])
            && is_scalar($_POST['t_rent_control_action'])
            && $_POST['t_rent_control_action'] !== '';
    }

    private function request_token()
    {
        if (isset($_GET[self::PUBLIC_TOKEN_PARAM]) && is_scalar($_GET[self::PUBLIC_TOKEN_PARAM])) {
            return (string) wp_unslash($_GET[self::PUBLIC_TOKEN_PARAM]);
        }
        if (isset($_POST[self::PUBLIC_TOKEN_PARAM]) && is_scalar($_POST[self::PUBLIC_TOKEN_PARAM])) {
            return (string) wp_unslash($_POST[self::PUBLIC_TOKEN_PARAM]);
        }
        return '';
    }

    private function valid_token($provided)
    {
        $stored = (string) get_option(self::TOKEN_OPTION, '');
        return $stored !== '' && $provided !== '' && hash_equals($stored, $provided);
    }

    private function action_signature($inventory_id, $token)
    {
        return hash_hmac('sha256', 'equipment-control|' . absint($inventory_id), $token);
    }

    private function public_url($token)
    {
        return add_query_arg([
            self::PUBLIC_QUERY_VAR   => '1',
            self::PUBLIC_TOKEN_PARAM => $token,
        ], self::app_base_url());
    }

    private function woocommerce_ready()
    {
        return function_exists('wc_get_orders') && function_exists('wc_get_order_statuses');
    }
}
