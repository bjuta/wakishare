<?php

namespace YourShare;

if (!defined('ABSPATH')) {
    exit;
}

class UTM
{
    /** @var Options */
    private $options;

    public function __construct(Options $options)
    {
        $this->options = $options;
    }

    public function append(string $url, string $network, ?\WP_Post $post, array $atts): string
    {
        $options = $this->options->all();

        if (empty($options['enable_utm'])) {
            return $url;
        }

        $campaign = $atts['utm_campaign'] ?? '';
        if ($campaign === '') {
            $campaign = $options['utm_campaign'];
        }

        if ($campaign === '' && $post) {
            $campaign = sanitize_title(get_post_type($post)) . '-' . date_i18n('Ymd');
        }

        $content_template = $options['utm_content'];

        if ($post) {
            $replacements = [
                '{ID}'        => (string) $post->ID,
                '{slug}'      => $post->post_name,
                '{post_type}' => get_post_type($post),
            ];
            $content_template = strtr($content_template, $replacements);
        }

        $args = [
            'utm_source'   => $network,
            'utm_medium'   => $options['utm_medium'],
            'utm_campaign' => $campaign,
        ];

        if (!empty($options['utm_term'])) {
            $args['utm_term'] = $options['utm_term'];
        }

        if (!empty($options['utm_content'])) {
            $args['utm_content'] = $content_template;
        }

        return add_query_arg($args, $url);
    }
}
