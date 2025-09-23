<?php

namespace YourShare;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
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
                'args'                => [
                    'days'  => [
                        'required'          => false,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                    'start' => [
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => [$this, 'sanitize_date_param'],
                    ],
                    'end'   => [
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => [$this, 'sanitize_date_param'],
                    ],
                ],
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

    public function handle_report(WP_REST_Request $request): WP_REST_Response
    {
        $options = $this->options->all();
        $enabled = !empty($options['analytics_events']);

        $range = $this->resolve_requested_range($request);

        $series = $this->time_series_range($range['start'], $range['end']);

        $report = [
            'enabled'      => $enabled,
            'series'       => $series,
            'top'          => $this->top_lists($range['start'], $range['end']),
            'range'        => [
                'start'     => $series['start'],
                'end'       => $series['end'],
                'label'     => $this->format_range_label($range['start'], $range['end']),
                'days'      => $range['days'],
                'is_custom' => $range['is_custom'],
            ],
            'generated_at' => current_time('mysql'),
        ];

        return new WP_REST_Response($report, 200);
    }

    public function sanitize_date_param($value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        return sanitize_text_field((string) $value);
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

    private function resolve_requested_range(WP_REST_Request $request): array
    {
        $start_param = $request->get_param('start');
        $end_param   = $request->get_param('end');

        $custom_range = $this->resolve_range($start_param, $end_param);

        if ($custom_range) {
            return $custom_range + [
                'days'      => $this->calculate_days($custom_range['start'], $custom_range['end']),
                'is_custom' => true,
            ];
        }

        $days = $this->sanitize_days($request->get_param('days'));

        if (!$days) {
            $days = 7;
        }

        $range = $this->range_from_days($days);

        return $range + [
            'days'      => $days,
            'is_custom' => false,
        ];
    }

    private function sanitize_days($value): int
    {
        $days = absint($value);

        if ($days < 1) {
            return 0;
        }

        return min($days, 365);
    }

    private function resolve_range(?string $start = null, ?string $end = null): ?array
    {
        $start = $start !== null ? trim($start) : '';
        $end   = $end !== null ? trim($end) : '';

        if ($start === '' && $end === '') {
            return null;
        }

        $timezone = wp_timezone();

        $start_date = $start !== '' ? DateTimeImmutable::createFromFormat('Y-m-d', $start, $timezone) : null;
        $end_date   = $end !== '' ? DateTimeImmutable::createFromFormat('Y-m-d', $end, $timezone) : null;

        if ($start_date && $start_date->format('Y-m-d') !== $start) {
            $start_date = null;
        }

        if ($end_date && $end_date->format('Y-m-d') !== $end) {
            $end_date = null;
        }

        if ($start_date && !$end_date) {
            $end_date = $start_date;
        } elseif ($end_date && !$start_date) {
            $start_date = $end_date;
        }

        if (!$start_date || !$end_date) {
            return null;
        }

        if ($end_date < $start_date) {
            [$start_date, $end_date] = [$end_date, $start_date];
        }

        return [
            'start' => $start_date->setTime(0, 0, 0),
            'end'   => $end_date->setTime(23, 59, 59),
        ];
    }

    private function range_from_days(int $days): array
    {
        $days = max(1, $days);
        $timezone = wp_timezone();

        $current_timestamp = current_time('timestamp');
        $end = (new DateTimeImmutable('@' . $current_timestamp))->setTimezone($timezone)->setTime(23, 59, 59);
        $start = $end->modify('-' . ($days - 1) . ' days')->setTime(0, 0, 0);

        return [
            'start' => $start,
            'end'   => $end,
        ];
    }

    private function calculate_days(DateTimeImmutable $start, DateTimeImmutable $end): int
    {
        return (int) $start->diff($end)->format('%a') + 1;
    }

    private function time_series_range(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $dates = $this->date_range_between($start, $end);
        $series = [
            'share'    => array_fill_keys($dates, 0),
            'reaction' => array_fill_keys($dates, 0),
        ];

        global $wpdb;

        if ($wpdb instanceof wpdb) {
            $table = $this->table_name($wpdb);

            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT DATE(created_at) as day, event_type, COUNT(*) as total FROM {$table} WHERE created_at BETWEEN %s AND %s GROUP BY DATE(created_at), event_type",
                    $start->format('Y-m-d H:i:s'),
                    $end->format('Y-m-d H:i:s')
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
            'start'    => $start->setTime(0, 0, 0)->format('Y-m-d'),
            'end'      => $end->setTime(0, 0, 0)->format('Y-m-d'),
        ];
    }

    private function date_range_between(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $start_day = $start->setTime(0, 0, 0);
        $end_day   = $end->setTime(0, 0, 0);

        if ($end_day < $start_day) {
            [$start_day, $end_day] = [$end_day, $start_day];
        }

        $period = new DatePeriod($start_day, new DateInterval('P1D'), $end_day->modify('+1 day'));

        $dates = [];

        foreach ($period as $date) {
            $dates[] = $date->format('Y-m-d');
        }

        return $dates;
    }

    private function top_lists(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        return [
            'posts'    => $this->top_posts($start, $end),
            'networks' => $this->top_networks($start, $end),
            'devices'  => $this->top_devices($start, $end),
        ];
    }

    private function format_range_label(DateTimeImmutable $start, DateTimeImmutable $end): string
    {
        $format = get_option('date_format', 'M j, Y');
        $start_label = wp_date($format, $start->getTimestamp());
        $end_label   = wp_date($format, $end->getTimestamp());

        if ($start_label === $end_label) {
            return $start_label;
        }

        return sprintf('%s – %s', $start_label, $end_label);
    }

    private function top_posts(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        global $wpdb;

        if (!$wpdb instanceof wpdb) {
            return [];
        }

        $table    = $this->table_name($wpdb);
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_id, COUNT(*) as total FROM {$table} WHERE post_id > 0 AND created_at BETWEEN %s AND %s GROUP BY post_id ORDER BY total DESC LIMIT 5",
                $start->format('Y-m-d H:i:s'),
                $end->format('Y-m-d H:i:s')
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

    private function top_networks(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        global $wpdb;

        if (!$wpdb instanceof wpdb) {
            return [];
        }

        $table    = $this->table_name($wpdb);
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT network, COUNT(*) as total FROM {$table} WHERE event_type = %s AND network <> '' AND created_at BETWEEN %s AND %s GROUP BY network ORDER BY total DESC LIMIT 5",
                'share',
                $start->format('Y-m-d H:i:s'),
                $end->format('Y-m-d H:i:s')
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

    private function top_devices(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        global $wpdb;

        if (!$wpdb instanceof wpdb) {
            return [];
        }

        $table    = $this->table_name($wpdb);
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT device, COUNT(*) as total FROM {$table} WHERE event_type = %s AND device <> '' AND created_at BETWEEN %s AND %s GROUP BY device ORDER BY total DESC",
                'share',
                $start->format('Y-m-d H:i:s'),
                $end->format('Y-m-d H:i:s')
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
