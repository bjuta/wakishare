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
        add_shortcode('share_suite', [$this, 'handle_suite_shortcode']);
        add_shortcode('waki_share_suite', [$this, 'handle_suite_shortcode']);
        add_shortcode('share_follow', [$this, 'handle_follow_shortcode']);
        add_shortcode('waki_follow', [$this, 'handle_follow_shortcode']);
        add_shortcode('share_reactions', [$this, 'handle_reactions_shortcode']);
        add_shortcode('waki_reactions', [$this, 'handle_reactions_shortcode']);
    }

    public function handle_shortcode($atts, $content = '', string $tag = 'your_share'): string
    {
        if (!is_array($atts)) {
            $atts = [];
        }

        return $this->renderer->render_share_inline($atts);
    }

    public function handle_follow_shortcode($atts, $content = '', string $tag = 'share_follow'): string
    {
        if (!is_array($atts)) {
            $atts = [];
        }

        return $this->renderer->render_follow($atts);
    }

    public function handle_suite_shortcode($atts, $content = '', string $tag = 'share_suite'): string
    {
        if (!is_array($atts)) {
            $atts = [];
        }

        return $this->renderer->render_share_suite($atts);
    }

    public function handle_reactions_shortcode($atts, $content = '', string $tag = 'share_reactions'): string
    {
        if (!is_array($atts)) {
            $atts = [];
        }

        return $this->renderer->render_reactions($atts);
    }

    public function maybe_render_floating(): void
    {
        $options = $this->options->all();

        if (empty($options['sticky_enabled'])) {
            return;
        }

        $atts = [
            'labels'           => 'hide',
            'style'            => $options['share_style'],
            'size'             => 'sm',
            'brand'            => $options['share_brand_colors'] ? '1' : '0',
            'sticky_position'  => $options['sticky_position'],
            'sticky_breakpoint'=> intval($options['sticky_breakpoint']),
        ];

        echo $this->renderer->render_share_floating($atts); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}
