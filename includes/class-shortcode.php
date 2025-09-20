<?php

namespace YourShare;

if (!defined('ABSPATH')) {
    exit;
}

class Shortcode
{
    /** @var Options */
    private $options;

    /** @var Render */
    private $renderer;

    public function __construct(Options $options, Render $renderer)
    {
        $this->options  = $options;
        $this->renderer = $renderer;
    }

    public function register_hooks(): void
    {
        add_action('init', [$this, 'register_shortcode']);
        add_action('wp_footer', [$this, 'maybe_render_floating']);
    }

    public function register_shortcode(): void
    {
        add_shortcode('your_share', [$this, 'handle_shortcode']);
        add_shortcode('waki_share', [$this, 'handle_shortcode']);
    }

    public function handle_shortcode($atts, $content = '', string $tag = 'your_share'): string
    {
        $options = $this->options->all();

        if (!is_array($atts)) {
            $atts = [];
        }

        $networks_default = $options['share_networks_default'];
        if (is_array($networks_default)) {
            $networks_default = implode(',', $networks_default);
        }

        $atts = shortcode_atts([
            'networks'     => $networks_default,
            'labels'       => $options['share_labels'],
            'style'        => $options['share_style'],
            'size'         => $options['share_size'],
            'align'        => $options['share_align'],
            'brand'        => $options['share_brand_colors'] ? '1' : '0',
            'utm_campaign' => '',
            'url'          => '',
            'title'        => '',
        ], $atts, $tag);

        $context = [
            'placement' => 'inline',
            'align'     => $atts['align'],
        ];

        return $this->renderer->render($context, $atts);
    }

    public function maybe_render_floating(): void
    {
        $options = $this->options->all();

        if (empty($options['sticky_enabled'])) {
            return;
        }

        $networks_default = $options['share_networks_default'];
        if (is_array($networks_default)) {
            $networks_default = implode(',', $networks_default);
        }

        $atts = [
            'networks'     => $networks_default,
            'labels'       => 'hide',
            'style'        => $options['share_style'],
            'size'         => 'sm',
            'align'        => 'left',
            'brand'        => $options['share_brand_colors'] ? '1' : '0',
            'utm_campaign' => '',
            'url'          => '',
            'title'        => '',
        ];

        $context = [
            'placement'  => 'floating',
            'position'   => $options['sticky_position'],
            'breakpoint' => intval($options['sticky_breakpoint']),
        ];

        echo $this->renderer->render($context, $atts); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}
