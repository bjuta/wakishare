<?php

namespace YourShare;

if (!defined('ABSPATH')) {
    exit;
}

class Blocks
{
    /** @var string */
    private $plugin_file;

    /** @var Render */
    private $renderer;

    public function __construct(string $plugin_file, Render $renderer)
    {
        $this->plugin_file = $plugin_file;
        $this->renderer    = $renderer;
    }

    public function register_hooks(): void
    {
        add_action('init', [$this, 'register_blocks']);
    }

    public function register_blocks(): void
    {
        $base    = trailingslashit(plugin_dir_path($this->plugin_file)) . 'blocks/';
        $entries = [
            'share'          => [$this, 'render_share_block'],
            'sticky-toggle'  => [$this, 'render_sticky_block'],
            'follow'         => [$this, 'render_follow_block'],
            'reactions'      => [$this, 'render_reactions_block'],
            'media-selector' => [$this, 'render_media_selector_block'],
        ];

        foreach ($entries as $directory => $callback) {
            $path = $base . $directory;

            if (!file_exists($path . '/block.json')) {
                continue;
            }

            register_block_type($path, [
                'render_callback' => $callback,
            ]);
        }
    }

    public function render_share_block($attributes, string $content = '', $block = null): string
    {
        $attributes = is_array($attributes) ? $attributes : [];

        return $this->renderer->render_share_suite($attributes);
    }

    public function render_sticky_block($attributes, string $content = '', $block = null): string
    {
        $attributes = is_array($attributes) ? $attributes : [];

        return $this->renderer->render_share_floating($attributes);
    }

    public function render_follow_block($attributes, string $content = '', $block = null): string
    {
        $attributes = is_array($attributes) ? $attributes : [];

        return $this->renderer->render_follow($attributes);
    }

    public function render_reactions_block($attributes, string $content = '', $block = null): string
    {
        $attributes = is_array($attributes) ? $attributes : [];

        return $this->renderer->render_reactions($attributes);
    }

    public function render_media_selector_block($attributes, string $content = '', $block = null): string
    {
        $attributes = is_array($attributes) ? $attributes : [];
        $tag        = isset($attributes['tagName']) && is_string($attributes['tagName']) ? strtolower($attributes['tagName']) : 'figure';
        $allowed    = ['div', 'figure', 'section'];

        if (!in_array($tag, $allowed, true)) {
            $tag = 'figure';
        }

        $extra = [
            'data-your-share-media' => '1',
        ];

        if (!empty($attributes['overlayLabel']) && is_string($attributes['overlayLabel'])) {
            $extra['data-your-share-media-label'] = sanitize_text_field($attributes['overlayLabel']);
        }

        if (function_exists('get_block_wrapper_attributes')) {
            $wrapper = get_block_wrapper_attributes($extra);
        } else {
            $wrapper = '';
        }

        wp_enqueue_style('your-share');
        wp_enqueue_script('your-share');

        if (trim((string) $content) === '') {
            return '';
        }

        $markup = sprintf(
            '<%1$s %2$s>%3$s</%1$s>',
            tag_escape($tag),
            $wrapper,
            $content
        );

        return (string) apply_filters('your_share_media_selector_markup', $markup, $attributes, $block);
    }
}
