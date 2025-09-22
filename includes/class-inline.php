<?php

namespace YourShare;

if (!defined('ABSPATH')) {
    exit;
}

class Inline
{
    private const META_DISPLAY       = '_your_share_inline_display';
    private const META_COUNTS_BADGES = '_your_share_inline_counts_badges';
    private const META_COUNTS_TOTAL  = '_your_share_inline_counts_total';
    private const META_RADIUS        = '_your_share_inline_radius';
    private const NONCE_FIELD        = 'your_share_inline_meta_nonce';

    /** @var Options */
    private $options;

    /** @var Render */
    private $renderer;

    /** @var string */
    private $text_domain;

    public function __construct(Options $options, Render $renderer, string $text_domain)
    {
        $this->options     = $options;
        $this->renderer    = $renderer;
        $this->text_domain = $text_domain;
    }

    public function register_hooks(): void
    {
        add_filter('the_content', [$this, 'maybe_inject_inline'], 20);
        add_action('add_meta_boxes', [$this, 'register_meta_boxes']);
        add_action('save_post', [$this, 'save_post_meta']);
    }

    public function register_meta_boxes(): void
    {
        $post_types = $this->eligible_post_types();

        foreach ($post_types as $post_type) {
            add_meta_box(
                'your-share-inline',
                __('Inline Share Buttons', $this->text_domain),
                [$this, 'render_meta_box'],
                $post_type,
                'side',
                'high'
            );
        }
    }

    public function render_meta_box(\WP_Post $post): void
    {
        $display       = $this->get_choice_meta($post->ID, self::META_DISPLAY);
        $counts_badges = $this->get_choice_meta($post->ID, self::META_COUNTS_BADGES);
        $counts_total  = $this->get_choice_meta($post->ID, self::META_COUNTS_TOTAL);
        $radius        = get_post_meta($post->ID, self::META_RADIUS, true);

        if (!is_string($radius) || $radius === '') {
            $radius = '';
        }

        wp_nonce_field(self::NONCE_FIELD, self::NONCE_FIELD);
        ?>
        <p><?php esc_html_e('Control whether inline share buttons appear automatically for this content.', $this->text_domain); ?></p>
        <fieldset class="your-share-meta-field">
            <legend class="screen-reader-text"><?php esc_html_e('Inline share buttons visibility', $this->text_domain); ?></legend>
            <label>
                <input type="radio" name="your_share_inline_display" value="default" <?php checked($display, 'default'); ?>>
                <?php esc_html_e('Use global default', $this->text_domain); ?>
            </label><br>
            <label>
                <input type="radio" name="your_share_inline_display" value="show" <?php checked($display, 'show'); ?>>
                <?php esc_html_e('Always show inline buttons', $this->text_domain); ?>
            </label><br>
            <label>
                <input type="radio" name="your_share_inline_display" value="hide" <?php checked($display, 'hide'); ?>>
                <?php esc_html_e('Hide inline buttons', $this->text_domain); ?>
            </label>
        </fieldset>
        <hr>
        <fieldset class="your-share-meta-field">
            <legend><?php esc_html_e('Share counts', $this->text_domain); ?></legend>
            <label>
                <span><?php esc_html_e('Button badges', $this->text_domain); ?></span><br>
                <select name="your_share_inline_counts_badges">
                    <option value="default" <?php selected($counts_badges, 'default'); ?>><?php esc_html_e('Use global default', $this->text_domain); ?></option>
                    <option value="show" <?php selected($counts_badges, 'show'); ?>><?php esc_html_e('Show badges', $this->text_domain); ?></option>
                    <option value="hide" <?php selected($counts_badges, 'hide'); ?>><?php esc_html_e('Hide badges', $this->text_domain); ?></option>
                </select>
            </label>
            <label>
                <span><?php esc_html_e('Total label', $this->text_domain); ?></span><br>
                <select name="your_share_inline_counts_total">
                    <option value="default" <?php selected($counts_total, 'default'); ?>><?php esc_html_e('Use global default', $this->text_domain); ?></option>
                    <option value="show" <?php selected($counts_total, 'show'); ?>><?php esc_html_e('Show total', $this->text_domain); ?></option>
                    <option value="hide" <?php selected($counts_total, 'hide'); ?>><?php esc_html_e('Hide total', $this->text_domain); ?></option>
                </select>
            </label>
        </fieldset>
        <hr>
        <label for="your-share-inline-radius">
            <span><?php esc_html_e('Corner radius (px)', $this->text_domain); ?></span><br>
            <input type="number" min="0" id="your-share-inline-radius" name="your_share_inline_radius" value="<?php echo esc_attr($radius); ?>" class="small-text">
        </label>
        <p class="description"><?php esc_html_e('Leave empty to inherit the default radius.', $this->text_domain); ?></p>
        <?php
    }

    public function save_post_meta(int $post_id): void
    {
        if (!isset($_POST[self::NONCE_FIELD])) {
            return;
        }

        $nonce = wp_unslash($_POST[self::NONCE_FIELD]);
        if (!is_string($nonce) || !wp_verify_nonce($nonce, self::NONCE_FIELD)) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($post_id)) {
            return;
        }

        $post = get_post($post_id);
        if (!$post instanceof \WP_Post) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $display = isset($_POST['your_share_inline_display']) ? sanitize_key(wp_unslash($_POST['your_share_inline_display'])) : 'default';
        $display = $this->sanitize_choice($display);

        $counts_badges = isset($_POST['your_share_inline_counts_badges']) ? sanitize_key(wp_unslash($_POST['your_share_inline_counts_badges'])) : 'default';
        $counts_badges = $this->sanitize_choice($counts_badges);

        $counts_total = isset($_POST['your_share_inline_counts_total']) ? sanitize_key(wp_unslash($_POST['your_share_inline_counts_total'])) : 'default';
        $counts_total = $this->sanitize_choice($counts_total);

        $radius = isset($_POST['your_share_inline_radius']) ? wp_unslash($_POST['your_share_inline_radius']) : '';
        $radius = is_scalar($radius) ? (string) $radius : '';
        $radius = trim($radius);

        if ($display === 'default') {
            delete_post_meta($post_id, self::META_DISPLAY);
        } else {
            update_post_meta($post_id, self::META_DISPLAY, $display);
        }

        if ($counts_badges === 'default') {
            delete_post_meta($post_id, self::META_COUNTS_BADGES);
        } else {
            update_post_meta($post_id, self::META_COUNTS_BADGES, $counts_badges);
        }

        if ($counts_total === 'default') {
            delete_post_meta($post_id, self::META_COUNTS_TOTAL);
        } else {
            update_post_meta($post_id, self::META_COUNTS_TOTAL, $counts_total);
        }

        if ($radius === '') {
            delete_post_meta($post_id, self::META_RADIUS);
        } else {
            $radius_value = max(0, intval($radius));
            update_post_meta($post_id, self::META_RADIUS, (string) $radius_value);
        }
    }

    public function maybe_inject_inline($content)
    {
        if (!is_string($content)) {
            return $content;
        }

        if (is_admin() || is_feed() || is_embed()) {
            return $content;
        }

        if (!is_singular()) {
            return $content;
        }

        if (!in_the_loop() || !is_main_query()) {
            return $content;
        }

        $post = get_post();
        if (!$post instanceof \WP_Post) {
            return $content;
        }

        if (post_password_required($post)) {
            return $content;
        }

        $options = $this->options->all();
        $display = $this->get_choice_meta($post->ID, self::META_DISPLAY);
        $force_show = ($display === 'show');

        if ($display === 'hide') {
            return $content;
        }

        if (!$force_show && empty($options['share_inline_auto_enabled'])) {
            return $content;
        }

        if (!$force_show && !$this->is_post_type_enabled($post->post_type, $options['share_inline_post_types'] ?? [])) {
            return $content;
        }

        if ($this->has_explicit_share_markup($post, $content)) {
            return $content;
        }

        $atts = [];

        $counts_badges = $this->get_choice_meta($post->ID, self::META_COUNTS_BADGES);
        if ($counts_badges === 'show') {
            $atts['counts_enabled']      = 1;
            $atts['counts_show_badges']  = 1;
        } elseif ($counts_badges === 'hide') {
            $atts['counts_show_badges'] = 0;
        }

        $counts_total = $this->get_choice_meta($post->ID, self::META_COUNTS_TOTAL);
        if ($counts_total === 'show') {
            $atts['counts_enabled']     = 1;
            $atts['counts_show_total']  = 1;
        } elseif ($counts_total === 'hide') {
            $atts['counts_show_total'] = 0;
        }

        if ((isset($atts['counts_show_badges']) && (int) $atts['counts_show_badges'] === 0)
            && (isset($atts['counts_show_total']) && (int) $atts['counts_show_total'] === 0)
        ) {
            $atts['counts_enabled'] = 0;
        }

        $radius = get_post_meta($post->ID, self::META_RADIUS, true);
        if (is_string($radius) && $radius !== '') {
            $atts['share_radius'] = max(0, intval($radius));
        }

        $markup = $this->renderer->render_share_inline($atts);

        if ($markup === '') {
            return $content;
        }

        $position = $options['share_inline_position'] ?? 'after';

        if ($position === 'before') {
            return $markup . $content;
        }

        if ($position === 'both') {
            return $markup . $content . $markup;
        }

        return $content . $markup;
    }

    private function sanitize_choice(string $value): string
    {
        $allowed = ['default', 'show', 'hide'];

        if (!in_array($value, $allowed, true)) {
            return 'default';
        }

        return $value;
    }

    private function get_choice_meta(int $post_id, string $key): string
    {
        $value = get_post_meta($post_id, $key, true);

        if (!is_string($value) || $value === '') {
            return 'default';
        }

        return $this->sanitize_choice($value);
    }

    private function eligible_post_types(): array
    {
        $post_types = get_post_types(['public' => true, 'show_ui' => true], 'names');

        if (!is_array($post_types)) {
            return [];
        }

        return array_values($post_types);
    }

    private function is_post_type_enabled(string $post_type, array $selected): bool
    {
        if (empty($selected)) {
            return true;
        }

        return in_array($post_type, $selected, true);
    }

    private function has_explicit_share_markup(\WP_Post $post, string $content): bool
    {
        $shortcodes = ['your_share', 'waki_share', 'share_suite', 'waki_share_suite'];

        foreach ($shortcodes as $shortcode) {
            if (has_shortcode($content, $shortcode)) {
                return true;
            }
        }

        if (function_exists('has_block')) {
            if (has_block('your-share/share', $post) || has_block('your-share/share-suite', $post)) {
                return true;
            }
        }

        if (strpos($content, 'data-your-share') !== false) {
            return true;
        }

        return false;
    }
}
