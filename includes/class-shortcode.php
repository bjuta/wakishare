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

        $atts = shortcode_atts([
            'networks'     => $options['networks'],
            'labels'       => $options['labels'],
            'style'        => $options['style'],
            'size'         => $options['size'],
            'align'        => 'left',
            'brand'        => $options['brand_colors'] ? '1' : '0',
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

        if (empty($options['floating_enabled'])) {
            return;
        }

        $atts = [
            'networks'     => $options['networks'],
            'labels'       => 'hide',
            'style'        => $options['style'],
            'size'         => 'sm',
            'align'        => 'left',
            'brand'        => $options['brand_colors'] ? '1' : '0',
            'utm_campaign' => '',
            'url'          => '',
            'title'        => '',
        ];

        $context = [
            'placement'  => 'floating',
            'position'   => $options['floating_position'],
            'breakpoint' => intval($options['floating_breakpoint']),
        ];

        echo $this->renderer->render($context, $atts); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}
