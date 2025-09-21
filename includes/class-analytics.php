<?php

namespace YourShare;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use wpdb;

if (!defined('ABSPATH')) {
    exit;
}

class Analytics
{
    private const RESET_NOTICE_KEY = 'your_share_analytics_reset_notice';

    /** @var Options */
    private $options;

    /** @var string */
    private $text_domain;

    /** @var string */
    private $admin_slug;

    public function __construct(Options $options, string $text_domain, string $admin_slug)
    {
        $this->options     = $options;
        $this->text_domain = $text_domain;
        $this->admin_slug  = $admin_slug;
    }

    public function register_hooks(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('admin_post_your_share_export_events', [$this, 'handle_export_request']);
        add_action('admin_post_your_share_reset_events', [$this, 'handle_reset_request']);
    }

    public function register_routes(): void
    {
        register_rest_route(
            'your-share/v1',
            '/event',
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'handle_event'],
                'permission_callback' => [$this, 'check_nonce'],
                'args'                => [
                    'event'     => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_key',
                    ],
                    'post_id'   => [
                        'required'          => false,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                    'network'   => [
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_key',
                    ],
                    'placement' => [
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_key',
                    ],
                    'url'       => [
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'esc_url_raw',
                    ],
                ],
            ]
        );

        register_rest_route(
            'your-share/v1',
            '/analytics/report',
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'handle_report'],
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                },
            ]
        );
    }

    public function check_nonce($request)
    {
        if (!$request instanceof WP_REST_Request) {
            return new WP_Error('your_share_invalid_request', __('Invalid request.', $this->text_domain), ['status' => 400]);
        }

        $nonce = $request->get_header('x-wp-nonce');

        if (!$nonce) {
            $nonce = $request->get_param('_wpnonce');
        }

        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error('your_share_invalid_nonce', __('Invalid or missing nonce.', $this->text_domain), ['status' => 403]);
        }

        return true;
    }

    public function handle_event(WP_REST_Request $request)
    {
        $event_type = sanitize_key($request->get_param('event'));

        if (!in_array($event_type, ['share', 'reaction'], true)) {
            return new WP_Error('your_share_invalid_event', __('Unsupported event type.', $this->text_domain), ['status' => 400]);
        }

        $post_id   = absint($request->get_param('post_id'));
        $network   = sanitize_key($request->get_param('network'));
        $placement = sanitize_key($request->get_param('placement'));
        $url       = esc_url_raw((string) $request->get_param('url'));

        $ip_address = $this->resolve_ip_address($request);
        $user_agent = $this->resolve_user_agent($request);
        $device     = $this->determine_device($user_agent);

        $options = $this->options->all();
        $stored  = false;

        if (!empty($options['analytics_events'])) {
            $stored = $this->store_event([
                'post_id'    => $post_id,
                'event_type' => $event_type,
                'network'    => $network,
                'placement'  => $placement,
                'device'     => $device,
                'share_url'  => $url,
                'ip_address' => $ip_address,
                'user_agent' => $user_agent,
            ]);
        }

        if (!empty($options['analytics_ga4'])) {
            $this->dispatch_ga4([
                'event'      => $event_type,
                'post_id'    => $post_id,
                'network'    => $network,
                'placement'  => $placement,
                'device'     => $device,
                'share_url'  => $url,
                'ip_address' => $ip_address,
                'user_agent' => $user_agent,
            ]);
        }

        return new WP_REST_Response([
            'recorded' => (bool) $stored,
        ], 200);
    }

    public function handle_report(): WP_REST_Response
    {
        $options = $this->options->all();
        $enabled = !empty($options['analytics_events']);

        $report = [
            'enabled' => $enabled,
            'series'  => [
                '7'  => $this->time_series(7),
                '30' => $this->time_series(30),
                '90' => $this->time_series(90),
            ],
            'top'     => $this->top_lists(),
            'generated_at' => current_time('mysql'),
        ];

        return new WP_REST_Response($report, 200);
    }

    public function handle_export_request(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to export analytics.', $this->text_domain));
        }

        check_admin_referer('your_share_export_events', 'your_share_export_events_nonce');

        $rows = $this->get_all_events();
        $csv  = $this->generate_csv($rows);

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="your-share-events-' . gmdate('Ymd-His') . '.csv"');

        echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    public function handle_reset_request(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to reset analytics.', $this->text_domain));
        }

        check_admin_referer('your_share_reset_events', 'your_share_reset_events_nonce');

        $this->truncate_events();

        set_transient(self::RESET_NOTICE_KEY, 1, MINUTE_IN_SECONDS);

        $redirect = add_query_arg(
            [
                'page' => $this->admin_slug,
                'tab'  => 'analytics',
            ],
            admin_url('options-general.php')
        );

        wp_safe_redirect($redirect);
        exit;
    }

    public function consume_reset_notice(): bool
    {
        $notice = get_transient(self::RESET_NOTICE_KEY);

        if ($notice) {
            delete_transient(self::RESET_NOTICE_KEY);
            return true;
        }

        return false;
    }

    private function table_name(wpdb $wpdb): string
    {
        return $wpdb->prefix . 'yourshare_events';
    }

    private function store_event(array $data): bool
    {
        global $wpdb;

        if (!$wpdb instanceof wpdb) {
            return false;
        }

        $table = $this->table_name($wpdb);
        $row   = [
            'post_id'    => (int) ($data['post_id'] ?? 0),
            'event_type' => $data['event_type'] ?? '',
            'network'    => $data['network'] ?? '',
            'placement'  => $data['placement'] ?? '',
            'device'     => $data['device'] ?? '',
            'share_url'  => $data['share_url'] ?? '',
            'ip_address' => substr((string) ($data['ip_address'] ?? ''), 0, 100),
            'user_agent' => $data['user_agent'] ?? '',
            'created_at' => current_time('mysql'),
        ];

        return false !== $wpdb->insert($table, $row, ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);
    }

    private function resolve_ip_address(WP_REST_Request $request): string
    {
        $headers = [
            'x-forwarded-for',
            'x-real-ip',
        ];

        foreach ($headers as $header) {
            $value = $request->get_header($header);
            if ($value) {
                $parts = explode(',', $value);
                $ip    = trim($parts[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        $remote = $_SERVER['REMOTE_ADDR'] ?? '';
        if (filter_var($remote, FILTER_VALIDATE_IP)) {
            return $remote;
        }

        return '';
    }

    private function resolve_user_agent(WP_REST_Request $request): string
    {
        $header = $request->get_header('user_agent');

        if (!$header) {
            $header = $_SERVER['HTTP_USER_AGENT'] ?? '';
        }

        return sanitize_text_field($header);
    }

    private function determine_device(string $user_agent): string
    {
        $ua = strtolower($user_agent);

        if ($ua === '') {
            return 'unknown';
        }

        if (strpos($ua, 'tablet') !== false || strpos($ua, 'ipad') !== false) {
            return 'tablet';
        }

        if (strpos($ua, 'mobi') !== false || strpos($ua, 'android') !== false) {
            return 'mobile';
        }

        if (strpos($ua, 'bot') !== false || strpos($ua, 'spider') !== false) {
            return 'bot';
        }

        return 'desktop';
    }

    private function dispatch_ga4(array $event): void
    {
        $measurement_id = apply_filters('your_share_ga4_measurement_id', '');
        $api_secret     = apply_filters('your_share_ga4_api_secret', '');

        if (empty($measurement_id) || empty($api_secret)) {
            return;
        }

        $client_source = $event['ip_address'] . '|' . $event['user_agent'];
        $client_id     = substr(md5($client_source !== '' ? $client_source : microtime(true)), 0, 32);

        $params = [
            'interaction_type' => $event['event'],
            'network'          => $event['network'],
            'post_id'          => (int) ($event['post_id'] ?? 0),
            'placement'        => $event['placement'],
            'device'           => $event['device'],
        ];

        if (!empty($event['share_url'])) {
            $params['share_url'] = $event['share_url'];
        }

        $body = [
            'client_id' => $client_id,
            'events'    => [
                [
                    'name'   => 'your_share_interaction',
                    'params' => $params,
                ],
            ],
        ];

        $url = add_query_arg(
            [
                'measurement_id' => $measurement_id,
                'api_secret'     => $api_secret,
            ],
            'https://www.google-analytics.com/mp/collect'
        );

        wp_remote_post(
            $url,
            [
                'timeout' => 5,
                'body'    => wp_json_encode($body),
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ]
        );
    }

    private function time_series(int $days): array
    {
        $days = max(1, $days);

        $dates = $this->date_range($days);
        $series = [
            'share'    => array_fill_keys($dates, 0),
            'reaction' => array_fill_keys($dates, 0),
        ];

        global $wpdb;

        if ($wpdb instanceof wpdb) {
            $table = $this->table_name($wpdb);
            $now   = current_time('mysql');
            $interval = max(0, $days - 1);

            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT DATE(created_at) as day, event_type, COUNT(*) as total FROM {$table} WHERE created_at >= DATE_SUB(%s, INTERVAL %d DAY) GROUP BY DATE(created_at), event_type",
                    $now,
                    $interval
                ),
                ARRAY_A
            );

            if (is_array($results)) {
                foreach ($results as $row) {
                    $day   = $row['day'] ?? '';
                    $type  = $row['event_type'] ?? '';
                    $count = isset($row['total']) ? (int) $row['total'] : 0;

                    if (isset($series[$type]) && isset($series[$type][$day])) {
                        $series[$type][$day] = $count;
                    }
                }
            }
        }

        $labels = array_map(static function ($day) {
            $timestamp = strtotime($day);
            return $timestamp ? date_i18n('M j', $timestamp) : $day;
        }, $dates);

        return [
            'dates'    => $dates,
            'labels'   => $labels,
            'share'    => array_values($series['share']),
            'reaction' => array_values($series['reaction']),
            'totals'   => [
                'share'    => array_sum($series['share']),
                'reaction' => array_sum($series['reaction']),
            ],
        ];
    }

    private function top_lists(): array
    {
        return [
            'posts'    => $this->top_posts(),
            'networks' => $this->top_networks(),
            'devices'  => $this->top_devices(),
        ];
    }

    private function top_posts(): array
    {
        global $wpdb;

        if (!$wpdb instanceof wpdb) {
            return [];
        }

        $table    = $this->table_name($wpdb);
        $now      = current_time('mysql');
        $interval = 29;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_id, COUNT(*) as total FROM {$table} WHERE post_id > 0 AND created_at >= DATE_SUB(%s, INTERVAL %d DAY) GROUP BY post_id ORDER BY total DESC LIMIT 5",
                $now,
                $interval
            ),
            ARRAY_A
        );

        if (!is_array($rows)) {
            return [];
        }

        $output = [];

        foreach ($rows as $row) {
            $post_id = isset($row['post_id']) ? (int) $row['post_id'] : 0;
            if ($post_id <= 0) {
                continue;
            }

            $title = get_the_title($post_id);
            if (!$title) {
                $title = sprintf(__('Post #%d', $this->text_domain), $post_id);
            }

            $output[] = [
                'post_id' => $post_id,
                'title'   => $title,
                'total'   => isset($row['total']) ? (int) $row['total'] : 0,
                'link'    => get_permalink($post_id),
            ];
        }

        return $output;
    }

    private function top_networks(): array
    {
        global $wpdb;

        if (!$wpdb instanceof wpdb) {
            return [];
        }

        $table    = $this->table_name($wpdb);
        $now      = current_time('mysql');
        $interval = 29;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT network, COUNT(*) as total FROM {$table} WHERE event_type = %s AND network <> '' AND created_at >= DATE_SUB(%s, INTERVAL %d DAY) GROUP BY network ORDER BY total DESC LIMIT 5",
                'share',
                $now,
                $interval
            ),
            ARRAY_A
        );

        if (!is_array($rows)) {
            return [];
        }

        $output = [];

        foreach ($rows as $row) {
            $network = sanitize_key($row['network'] ?? '');
            if ($network === '') {
                continue;
            }

            $output[] = [
                'network' => $network,
                'total'   => isset($row['total']) ? (int) $row['total'] : 0,
            ];
        }

        return $output;
    }

    private function top_devices(): array
    {
        global $wpdb;

        if (!$wpdb instanceof wpdb) {
            return [];
        }

        $table    = $this->table_name($wpdb);
        $now      = current_time('mysql');
        $interval = 29;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT device, COUNT(*) as total FROM {$table} WHERE event_type = %s AND device <> '' AND created_at >= DATE_SUB(%s, INTERVAL %d DAY) GROUP BY device ORDER BY total DESC",
                'share',
                $now,
                $interval
            ),
            ARRAY_A
        );

        if (!is_array($rows)) {
            return [];
        }

        $output = [];

        foreach ($rows as $row) {
            $device = sanitize_key($row['device'] ?? '');
            if ($device === '') {
                continue;
            }

            $label = $this->device_label($device);

            $output[] = [
                'device' => $device,
                'label'  => $label,
                'total'  => isset($row['total']) ? (int) $row['total'] : 0,
            ];
        }

        return $output;
    }

    private function device_label(string $device): string
    {
        $labels = [
            'desktop' => __('Desktop', $this->text_domain),
            'mobile'  => __('Mobile', $this->text_domain),
            'tablet'  => __('Tablet', $this->text_domain),
            'bot'     => __('Bot', $this->text_domain),
            'unknown' => __('Unknown', $this->text_domain),
        ];

        return $labels[$device] ?? ucfirst($device);
    }

    private function date_range(int $days): array
    {
        $days = max(1, $days);
        $dates = [];
        $timestamp = current_time('timestamp');

        for ($i = $days - 1; $i >= 0; $i--) {
            $dates[] = wp_date('Y-m-d', $timestamp - ($i * DAY_IN_SECONDS));
        }

        return $dates;
    }

    private function get_all_events(): array
    {
        global $wpdb;

        if (!$wpdb instanceof wpdb) {
            return [];
        }

        $table = $this->table_name($wpdb);

        $rows = $wpdb->get_results(
            "SELECT id, created_at, event_type, post_id, network, placement, device, share_url, ip_address, user_agent FROM {$table} ORDER BY created_at DESC",
            ARRAY_A
        );

        if (!is_array($rows)) {
            return [];
        }

        return $rows;
    }

    private function generate_csv(array $rows): string
    {
        $output = fopen('php://temp', 'w+');

        fputcsv($output, ['ID', 'Created At', 'Event Type', 'Post ID', 'Network', 'Placement', 'Device', 'Share URL', 'IP Address', 'User Agent']);

        foreach ($rows as $row) {
            fputcsv($output, [
                $row['id'] ?? '',
                $row['created_at'] ?? '',
                $row['event_type'] ?? '',
                $row['post_id'] ?? '',
                $row['network'] ?? '',
                $row['placement'] ?? '',
                $row['device'] ?? '',
                $row['share_url'] ?? '',
                $row['ip_address'] ?? '',
                $row['user_agent'] ?? '',
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return (string) $csv;
    }

    private function truncate_events(): void
    {
        global $wpdb;

        if (!$wpdb instanceof wpdb) {
            return;
        }

        $table = $this->table_name($wpdb);
        $wpdb->query("TRUNCATE TABLE {$table}");
    }
}
