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
        register_rest_route(
            'your-share/v1',
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

        register_rest_route(
            'your-share/v1',
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
                'post_id'       => $post_id,
                'reaction'      => $reaction,
                'counts'        => $counts,
                'user_reactions'=> $current,
                'user_reaction' => $current[0] ?? '',
                'status'        => $status,
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

        $counts   = $this->reactions->get_counts($post_id);
        $reactions = $this->reactions->current_user_reactions($post_id);

        return new WP_REST_Response(
            [
                'post_id'       => $post_id,
                'counts'        => $counts,
                'user_reactions'=> $reactions,
                'user_reaction' => $reactions[0] ?? '',
            ],
            200
        );
    }
}
