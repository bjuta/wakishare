<?php

namespace YourShare;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

class Rest
{
    private const NAMESPACES = [
        'your-share/v1',
        'waki/v1',
    ];

    private const SHORTCODE_ALIASES = [
        'share'            => 'your_share',
        'your_share'       => 'your_share',
        'waki_share'       => 'waki_share',
        'suite'            => 'share_suite',
        'share_suite'      => 'share_suite',
        'waki_share_suite' => 'waki_share_suite',
        'follow'           => 'share_follow',
        'share_follow'     => 'share_follow',
        'waki_follow'      => 'waki_follow',
        'reactions'        => 'share_reactions',
        'share_reactions'  => 'share_reactions',
        'waki_reactions'   => 'waki_reactions',
    ];

    private const ALLOWED_SHORTCODES = [
        'your_share',
        'waki_share',
        'share_suite',
        'waki_share_suite',
        'share_follow',
        'waki_follow',
        'share_reactions',
        'waki_reactions',
    ];

    /** @var Reactions */
    private $reactions;

    /** @var string */
    private $text_domain;

    public function __construct(Reactions $reactions, string $text_domain)
    {
        $this->reactions   = $reactions;
        $this->text_domain = $text_domain;
    }

    public function register_hooks(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        foreach (self::NAMESPACES as $namespace) {
            $this->register_react_route($namespace);
            $this->register_summary_route($namespace);
            $this->register_shortcode_preview_route($namespace);
        }
    }

    private function register_react_route(string $namespace): void
    {
        register_rest_route(
            $namespace,
            '/react',
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'handle_react'],
                'permission_callback' => [$this, 'check_nonce'],
                'args'                => [
                    'post_id'  => [
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                    'reaction' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_key',
                    ],
                    'intent'   => [
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ]
        );
    }

    private function register_summary_route(string $namespace): void
    {
        register_rest_route(
            $namespace,
            '/summary',
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'handle_summary'],
                'permission_callback' => [$this, 'check_nonce'],
                'args'                => [
                    'post_id' => [
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ]
        );
    }

    private function register_shortcode_preview_route(string $namespace): void
    {
        register_rest_route(
            $namespace,
            '/shortcode-preview',
            [
                'methods'             => ['GET', 'POST'],
                'callback'            => [$this, 'handle_shortcode_preview'],
                'permission_callback' => [$this, 'check_nonce'],
                'args'                => [
                    'shortcode' => [
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'wp_kses_post',
                    ],
                    'tag'       => [
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'type'      => [
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'attributes' => [
                        'required' => false,
                        'type'     => 'object',
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

    public function handle_react(WP_REST_Request $request)
    {
        $post_id  = absint($request->get_param('post_id'));
        $reaction = sanitize_key($request->get_param('reaction'));

        if ($post_id <= 0 || get_post_status($post_id) === false) {
            return new WP_Error('your_share_unknown_post', __('Unable to locate the requested content.', $this->text_domain), ['status' => 404]);
        }

        if (!$this->reactions->is_available()) {
            return new WP_Error('your_share_reactions_disabled', __('Reactions are disabled.', $this->text_domain), ['status' => 400]);
        }

        if (!$this->reactions->is_active_slug($reaction)) {
            return new WP_Error('your_share_invalid_reaction', __('Unknown reaction.', $this->text_domain), ['status' => 400]);
        }

        $intent = strtolower((string) $request->get_param('intent'));

        if (!in_array($intent, ['add', 'remove', 'toggle'], true)) {
            $intent = 'toggle';
        }

        $current = $this->reactions->current_user_reactions($post_id);
        $has     = in_array($reaction, $current, true);
        $counts  = $this->reactions->get_counts($post_id);
        $status  = 'unchanged';

        if ($intent === 'remove' || ($intent === 'toggle' && $has)) {
            if ($has) {
                $counts = $this->reactions->decrement($post_id, $reaction);
                $current = array_values(array_diff($current, [$reaction]));
                $status  = 'removed';
            }
        } else {
            if (!$has) {
                $counts   = $this->reactions->increment($post_id, $reaction);
                $current[] = $reaction;
                $status    = 'added';
            }
        }

        $this->reactions->store_user_reactions($post_id, $current);

        return new WP_REST_Response(
            [
                'post_id'        => $post_id,
                'reaction'       => $reaction,
                'counts'         => $counts,
                'user_reactions' => $current,
                'user_reaction'  => $current[0] ?? '',
                'status'         => $status,
            ],
            200
        );
    }

    public function handle_summary(WP_REST_Request $request)
    {
        $post_id = absint($request->get_param('post_id'));

        if ($post_id <= 0 || get_post_status($post_id) === false) {
            return new WP_Error('your_share_unknown_post', __('Unable to locate the requested content.', $this->text_domain), ['status' => 404]);
        }

        $counts    = $this->reactions->get_counts($post_id);
        $reactions = $this->reactions->current_user_reactions($post_id);

        return new WP_REST_Response(
            [
                'post_id'        => $post_id,
                'counts'         => $counts,
                'user_reactions' => $reactions,
                'user_reaction'  => $reactions[0] ?? '',
            ],
            200
        );
    }

    public function handle_shortcode_preview(WP_REST_Request $request)
    {
        if (!current_user_can('edit_posts')) {
            return new WP_Error('your_share_forbidden', __('You are not allowed to preview shortcodes.', $this->text_domain), ['status' => 403]);
        }

        $attributes = $this->sanitize_shortcode_attributes($request->get_param('attributes'));
        $shortcode  = $request->get_param('shortcode');
        $shortcode  = is_string($shortcode) ? trim(wp_unslash($shortcode)) : '';

        if ($shortcode !== '') {
            $normalized = $this->normalize_shortcode_string($shortcode);

            if (is_wp_error($normalized)) {
                return $normalized;
            }

            if (!empty($attributes)) {
                $normalized = $this->merge_shortcode_attributes($normalized, $attributes);
            }
        } else {
            $tag = $this->resolve_shortcode_tag([
                $request->get_param('tag'),
                $request->get_param('shortcode_tag'),
                $request->get_param('shortcodeTag'),
                $request->get_param('type'),
            ]);

            if ($tag === '') {
                return new WP_Error('your_share_missing_shortcode', __('Please choose a supported shortcode to preview.', $this->text_domain), ['status' => 400]);
            }

            $normalized = $this->build_shortcode($tag, $attributes);
        }

        $parsed = $this->parse_shortcode_parts($normalized);

        if (is_wp_error($parsed)) {
            return $parsed;
        }

        [$tag, $parsed_attributes] = $parsed;

        $rendered = do_shortcode($normalized);

        $response = [
            'tag'        => $tag,
            'shortcode'  => $normalized,
            'attributes' => $parsed_attributes,
            'rendered'   => $rendered,
            'html'       => $rendered,
        ];

        /**
         * Filter the shortcode preview REST response.
         *
         * @param array           $response          Response data for the preview.
         * @param string          $tag               Shortcode tag being previewed.
         * @param array           $parsed_attributes Sanitized shortcode attributes.
         * @param WP_REST_Request $request           The REST request instance.
         */
        $response = (array) apply_filters('your_share_shortcode_preview_response', $response, $tag, $parsed_attributes, $request);

        return new WP_REST_Response($response, 200);
    }

    private function resolve_shortcode_tag($candidates): string
    {
        $values = is_array($candidates) ? $candidates : [$candidates];

        foreach ($values as $value) {
            if (!is_string($value) || $value === '') {
                continue;
            }

            $normalized = strtolower(str_replace([' ', '-'], '_', trim(wp_unslash($value))));

            if (isset(self::SHORTCODE_ALIASES[$normalized])) {
                $normalized = self::SHORTCODE_ALIASES[$normalized];
            }

            if (in_array($normalized, self::ALLOWED_SHORTCODES, true)) {
                return $normalized;
            }
        }

        return '';
    }

    private function normalize_shortcode_string(string $shortcode)
    {
        $shortcode = trim(wp_unslash($shortcode));

        if ($shortcode === '') {
            return new WP_Error('your_share_missing_shortcode', __('No shortcode provided.', $this->text_domain), ['status' => 400]);
        }

        if ($shortcode[0] !== '[') {
            $shortcode = '[' . $shortcode;
        }

        if (substr($shortcode, -1) !== ']') {
            $shortcode .= ']';
        }

        $parsed = $this->parse_shortcode_parts($shortcode);

        if (is_wp_error($parsed)) {
            return $parsed;
        }

        [$tag, $attributes] = $parsed;

        return $this->build_shortcode($tag, $attributes);
    }

    private function parse_shortcode_parts(string $shortcode)
    {
        if (!preg_match('/^\[([A-Za-z0-9_-]+)(\s+[^\]]*)?\]$/', $shortcode, $matches)) {
            return new WP_Error('your_share_invalid_shortcode', __('Unsupported shortcode.', $this->text_domain), ['status' => 400]);
        }

        $tag = strtolower($matches[1]);

        if (!in_array($tag, self::ALLOWED_SHORTCODES, true)) {
            return new WP_Error('your_share_invalid_shortcode', __('Unsupported shortcode.', $this->text_domain), ['status' => 400]);
        }

        $atts_string = isset($matches[2]) ? trim($matches[2]) : '';
        $atts        = [];

        if ($atts_string !== '') {
            $parsed = shortcode_parse_atts($atts_string);
            if (is_array($parsed)) {
                $atts = $parsed;
            }
        }

        return [$tag, $this->sanitize_shortcode_attributes($atts)];
    }

    private function merge_shortcode_attributes(string $shortcode, array $overrides): string
    {
        if (empty($overrides)) {
            return $shortcode;
        }

        $parsed = $this->parse_shortcode_parts($shortcode);

        if (is_wp_error($parsed)) {
            return $shortcode;
        }

        [$tag, $attributes] = $parsed;

        $attributes = array_merge($attributes, $overrides);

        return $this->build_shortcode($tag, $attributes);
    }

    private function build_shortcode(string $tag, array $attributes): string
    {
        $shortcode = '[' . $tag;

        foreach ($attributes as $key => $value) {
            $value = str_replace('"', '\"', $value);
            $shortcode .= sprintf(' %s="%s"', $key, $value);
        }

        $shortcode .= ']';

        return $shortcode;
    }

    private function sanitize_shortcode_attributes($attributes): array
    {
        if (!is_array($attributes)) {
            return [];
        }

        $sanitized = [];
        $url_keys  = ['url', 'share_url', 'shareUrl', 'link', 'image', 'media', 'poster'];

        foreach ($attributes as $key => $value) {
            if (!is_string($key) && !is_int($key)) {
                continue;
            }

            $key = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $key);

            if ($key === '') {
                continue;
            }

            if (is_array($value)) {
                $value = implode(',', array_map('sanitize_text_field', array_map('wp_unslash', $value)));
            } elseif (is_bool($value)) {
                $value = $value ? '1' : '0';
            } else {
                $value = wp_unslash((string) $value);
            }

            if (in_array($key, $url_keys, true)) {
                $value = esc_url_raw($value);
            } else {
                $value = sanitize_text_field($value);
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }
}
