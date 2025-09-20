<?php

namespace YourShare;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use wpdb;

if (!defined('ABSPATH')) {
    exit;
}

class Counts
{
    /** @var Options */
    private $options;

    /** @var Networks */
    private $networks;

    public function __construct(Options $options, Networks $networks)
    {
        $this->options  = $options;
        $this->networks = $networks;
    }

    public function register_hooks(): void
    {
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    public function register_rest_routes(): void
    {
        register_rest_route(
            'your-share/v1',
            '/counts/(?P<post_id>\\d+)',
            [
                'methods'             => ['GET'],
                'callback'            => [$this, 'rest_get_counts'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'post_id'   => [
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                    'networks' => [
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'shareUrl' => [
                        'type'              => 'string',
                        'sanitize_callback' => 'esc_url_raw',
                    ],
                    'force'    => [
                        'type'              => 'boolean',
                        'sanitize_callback' => 'rest_sanitize_boolean',
                    ],
                ],
            ]
        );
    }

    /**
         * Expose counts through the REST API.
         */
    public function rest_get_counts(WP_REST_Request $request)
    {
        $settings = $this->options->all();

        if (empty($settings['counts_enabled'])) {
            return new WP_REST_Response(
                [
                    'enabled'  => false,
                    'total'    => 0,
                    'networks' => [],
                    'ttl'      => 0,
                ]
            );
        }

        $post_id = absint($request->get_param('post_id'));
        $post    = $post_id > 0 ? get_post($post_id) : null;

        if ($post_id > 0 && (!$post || $post->post_status === 'auto-draft')) {
            return new WP_Error('your_share_invalid_post', __('Invalid post.', Plugin::TEXT_DOMAIN), ['status' => 404]);
        }

        $networks_param = $request->get_param('networks');
        if ($networks_param) {
            $networks = $this->sanitize_network_list($networks_param);
        } else {
            $networks = $this->default_networks($settings);
        }

        $share_url = $request->get_param('shareUrl');
        if (!$share_url) {
            $share_url = $post_id > 0 ? get_permalink($post_id) : home_url('/');
        }

        $force_refresh = !empty($request['force']) && $this->verify_rest_nonce($request);

        $counts = $this->get_counts($post_id, $share_url, $networks, $force_refresh);

        return new WP_REST_Response($counts);
    }

    /**
     * Retrieve counts for a post and URL.
     */
    public function get_counts(int $post_id, string $share_url, array $networks, bool $force_refresh = false): array
    {
        $settings = $this->options->all();

        if (empty($settings['counts_enabled'])) {
            return [
                'enabled'  => false,
                'total'    => 0,
                'networks' => [],
                'ttl'      => 0,
            ];
        }

        $share_url = $share_url ?: ($post_id > 0 ? get_permalink($post_id) : home_url('/'));
        $share_url = esc_url_raw($share_url);

        if ($share_url === '') {
            return [
                'enabled'  => true,
                'total'    => 0,
                'networks' => [],
                'ttl'      => $this->ttl_seconds($settings),
            ];
        }

        $allowed_networks = array_keys($this->networks->all());
        $networks         = array_values(array_intersect($this->normalize_networks($networks), $allowed_networks));

        if (empty($networks)) {
            $networks = $this->default_networks($settings);
        }

        $networks = array_values(array_unique(array_filter($networks)));

        $ttl   = $this->ttl_seconds($settings);
        $total = 0;
        $data  = [
            'enabled'     => true,
            'total'       => 0,
            'networks'    => [],
            'ttl'         => $ttl,
            'generatedAt' => current_time('mysql'),
        ];

        foreach ($networks as $network) {
            $counts = $this->resolve_counts_for_network($post_id, $network, $share_url, $ttl, $force_refresh, $settings);
            $total += $counts['total'];
            $data['networks'][$network] = $counts;
        }

        $data['total'] = $total;

        return $data;
    }

    /**
     * Sanitize network list passed via REST query.
     *
     * @param mixed $value Raw request value.
     */
    public function sanitize_network_list($value): array
    {
        if (is_array($value)) {
            $list = $value;
        } else {
            $list = explode(',', (string) $value);
        }

        return $this->normalize_networks($list);
    }

    private function ttl_seconds(array $settings): int
    {
        $minutes = isset($settings['counts_refresh_interval']) ? (int) $settings['counts_refresh_interval'] : 0;
        if ($minutes <= 0) {
            return 0;
        }

        return $minutes * MINUTE_IN_SECONDS;
    }

    private function normalize_networks($value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (!is_array($value)) {
            $value = [];
        }

        $value = array_map(static function ($item) {
            return sanitize_key((string) $item);
        }, $value);

        return array_values(array_filter($value));
    }

    private function resolve_counts_for_network(int $post_id, string $network, string $share_url, int $ttl, bool $force, array $settings): array
    {
        $now          = current_time('timestamp');
        $cached       = $this->get_cached_count($post_id, $network);
        $cache_age    = $cached ? max(0, $now - $cached['timestamp']) : null;
        $needs_remote = $force || !$cached;

        if (!$needs_remote && $ttl > 0 && $cache_age !== null) {
            $needs_remote = ($cache_age >= $ttl);
        } elseif ($ttl === 0) {
            $needs_remote = true;
        }

        $remote_count = $cached ? (int) $cached['count'] : 0;
        $retrieved_at = $cached['timestamp'] ?? $now;
        $fresh        = false;

        if ($needs_remote) {
            $fresh_value = $this->fetch_remote_count($network, $share_url, $settings);
            if ($fresh_value !== null) {
                $remote_count = max(0, (int) $fresh_value);
                $retrieved_at = $now;
                $fresh        = true;
                $this->store_cached_count($post_id, $network, $remote_count, $retrieved_at);
            }
        }

        $local_total = $this->get_local_interactions($post_id, $network);
        $total       = max(0, $remote_count) + max(0, $local_total);

        return [
            'remote'      => $remote_count,
            'local'       => $local_total,
            'total'       => $total,
            'retrievedAt' => gmdate('c', $retrieved_at),
            'fresh'       => $fresh,
        ];
    }

    private function fetch_remote_count(string $network, string $share_url, array $settings): ?int
    {
        $share_url = esc_url_raw($share_url);
        if ($share_url === '') {
            return null;
        }

        switch ($network) {
            case 'facebook':
                return $this->fetch_facebook_count($share_url, $settings);
            case 'reddit':
                return $this->fetch_reddit_count($share_url, $settings);
            default:
                /**
                 * Allow third-parties to provide remote count providers for custom networks.
                 */
                return apply_filters('your_share_fetch_remote_count', null, $network, $share_url, $settings);
        }
    }

    private function fetch_facebook_count(string $share_url, array $settings): ?int
    {
        $app_id     = trim((string) ($settings['counts_facebook_app_id'] ?? ''));
        $app_secret = trim((string) ($settings['counts_facebook_app_secret'] ?? ''));

        if ($app_id === '' || $app_secret === '') {
            return null;
        }

        $endpoint = add_query_arg(
            [
                'id'           => $share_url,
                'fields'       => 'engagement',
                'access_token' => $app_id . '|' . $app_secret,
            ],
            'https://graph.facebook.com/v19.0/'
        );

        $response = wp_remote_get($endpoint, [
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        if (!$body) {
            return null;
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            return null;
        }

        $engagement = $data['engagement'] ?? [];
        $share      = isset($engagement['share_count']) ? (int) $engagement['share_count'] : 0;
        $reaction   = isset($engagement['reaction_count']) ? (int) $engagement['reaction_count'] : 0;
        $comment    = isset($engagement['comment_count']) ? (int) $engagement['comment_count'] : 0;

        $total = max(0, $share) + max(0, $reaction) + max(0, $comment);

        return $total;
    }

    private function fetch_reddit_count(string $share_url, array $settings): ?int
    {
        $endpoint = add_query_arg(
            [
                'url' => $share_url,
            ],
            'https://www.reddit.com/api/info.json'
        );

        $headers = [
            'User-Agent' => 'YourShare/' . Plugin::VERSION,
        ];

        if (!empty($settings['counts_reddit_app_id'])) {
            $headers['User-Agent'] .= ' (' . sanitize_text_field($settings['counts_reddit_app_id']) . ')';
        }

        $response = wp_remote_get($endpoint, [
            'timeout' => 10,
            'headers' => $headers,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        if (!$body) {
            return null;
        }

        $payload = json_decode($body, true);
        if (!is_array($payload) || empty($payload['data']['children'])) {
            return 0;
        }

        $total = 0;
        foreach ($payload['data']['children'] as $child) {
            if (!is_array($child) || empty($child['data'])) {
                continue;
            }

            $entry = $child['data'];
            if (isset($entry['score'])) {
                $total += max(0, (int) $entry['score']);
            } elseif (isset($entry['ups'])) {
                $total += max(0, (int) $entry['ups']);
            }
        }

        return $total;
    }

    private function get_cached_count(int $post_id, string $network): ?array
    {
        global $wpdb;

        if (!$wpdb instanceof wpdb) {
            return null;
        }

        $table = $wpdb->prefix . 'yourshare_counts_cache';

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT share_count, retrieved_at FROM {$table} WHERE post_id = %d AND network = %s",
                $post_id,
                $network
            ),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        $timestamp = strtotime($row['retrieved_at'] ?? '') ?: current_time('timestamp');

        return [
            'count'     => (int) $row['share_count'],
            'timestamp' => $timestamp,
        ];
    }

    private function store_cached_count(int $post_id, string $network, int $count, int $timestamp): void
    {
        global $wpdb;

        if (!$wpdb instanceof wpdb) {
            return;
        }

        $table = $wpdb->prefix . 'yourshare_counts_cache';

        $wpdb->replace(
            $table,
            [
                'post_id'      => $post_id,
                'network'      => $network,
                'share_count'  => $count,
                'retrieved_at' => wp_date('Y-m-d H:i:s', $timestamp),
            ],
            ['%d', '%s', '%d', '%s']
        );
    }

    private function get_local_interactions(int $post_id, string $network): int
    {
        if ($post_id <= 0) {
            return 0;
        }

        global $wpdb;

        if (!$wpdb instanceof wpdb) {
            return 0;
        }

        $table = $wpdb->prefix . 'yourshare_events';

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE post_id = %d AND network = %s",
                $post_id,
                $network
            )
        );

        return $count ? (int) $count : 0;
    }

    private function default_networks(array $settings): array
    {
        $defaults = $settings['share_networks_default'] ?? [];
        if (!is_array($defaults)) {
            $defaults = [];
        }

        return $this->normalize_networks($defaults);
    }

    private function verify_rest_nonce(WP_REST_Request $request): bool
    {
        $nonce = $request->get_header('X-WP-Nonce');
        if (!$nonce) {
            $nonce = $request->get_param('_wpnonce');
        }

        if (!$nonce) {
            return false;
        }

        return wp_verify_nonce($nonce, 'wp_rest') !== false;
    }
}
