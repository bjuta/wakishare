<?php

namespace YourShare;

use wpdb;

if (!defined('ABSPATH')) {
    exit;
}

class Reactions
{
    private const COOKIE_PREFIX       = 'yourshare_reacted_';
    private const STORAGE_KEY         = 'yourShareReactions';
    private const DEFAULT_COOKIE_TTL  = 31536000; // One year.

    /** @var Options */
    private $options;

    /** @var string */
    private $text_domain;

    public function __construct(Options $options, string $text_domain)
    {
        $this->options     = $options;
        $this->text_domain = $text_domain;
    }

    public function register_hooks(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'localize_script'], 20);
        add_action('wp_footer', [$this, 'render_sticky']);
    }

    public function localize_script(): void
    {
        if (!wp_script_is('your-share', 'enqueued')) {
            return;
        }

        $settings = $this->script_settings();

        if (empty($settings['emojis'])) {
            return;
        }

        wp_localize_script('your-share', 'yourShareReactions', $settings);
    }

    public function emojis(): array
    {
        return Emoji_Library::all();
    }

    public function active_emojis(): array
    {
        $enabled = $this->options->all()['reactions_enabled'] ?? [];
        $library = $this->emojis();
        $active  = [];

        if (!is_array($enabled)) {
            $enabled = [];
        }

        foreach ($enabled as $slug => $flag) {
            $slug = sanitize_key((string) $slug);

            if ($slug === '' || empty($flag)) {
                continue;
            }

            if (isset($library[$slug])) {
                $active[$slug] = $library[$slug];
            }
        }

        if (empty($active)) {
            foreach (Emoji_Library::defaults() as $slug) {
                if (isset($library[$slug])) {
                    $active[$slug] = $library[$slug];
                }
            }
        }

        return $active;
    }

    public function is_enabled(string $placement): bool
    {
        $options = $this->options->all();

        if ($placement === 'inline') {
            return !empty($options['reactions_inline_enabled']);
        }

        if ($placement === 'sticky') {
            return !empty($options['reactions_sticky_enabled']);
        }

        return false;
    }

    public function is_available(): bool
    {
        return $this->is_enabled('inline') || $this->is_enabled('sticky');
    }

    public function is_valid_slug(string $slug): bool
    {
        $slug = sanitize_key($slug);

        return $slug !== '' && isset($this->emojis()[$slug]);
    }

    public function is_active_slug(string $slug): bool
    {
        $slug = sanitize_key($slug);

        if ($slug === '') {
            return false;
        }

        $active = $this->active_emojis();

        return isset($active[$slug]);
    }

    public function get_counts(int $post_id): array
    {
        $active = $this->active_emojis();
        $counts = [];

        foreach (array_keys($active) as $slug) {
            $counts[$slug] = 0;
        }

        if ($post_id <= 0 || empty($active)) {
            return $counts;
        }

        global $wpdb;

        if (!$wpdb instanceof wpdb) {
            return $counts;
        }

        $table = $this->table_name($wpdb);
        $rows  = $wpdb->get_results(
            $wpdb->prepare("SELECT reaction, total FROM {$table} WHERE post_id = %d", $post_id),
            ARRAY_A
        );

        if (!is_array($rows)) {
            return $counts;
        }

        foreach ($rows as $row) {
            $slug = $row['reaction'] ?? '';
            if (isset($counts[$slug])) {
                $counts[$slug] = max(0, intval($row['total']));
            }
        }

        return $counts;
    }

    public function increment(int $post_id, string $reaction): array
    {
        if ($post_id <= 0 || !$this->is_active_slug($reaction)) {
            return $this->get_counts($post_id);
        }

        global $wpdb;

        if ($wpdb instanceof wpdb) {
            $table = $this->table_name($wpdb);
            $now   = current_time('mysql');

            $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$table} (post_id, reaction, total, updated_at) VALUES (%d, %s, 1, %s) ON DUPLICATE KEY UPDATE total = total + 1, updated_at = %s",
                    $post_id,
                    $reaction,
                    $now,
                    $now
                )
            );
        }

        return $this->get_counts($post_id);
    }

    public function decrement(int $post_id, string $reaction): array
    {
        if ($post_id <= 0 || !$this->is_active_slug($reaction)) {
            return $this->get_counts($post_id);
        }

        global $wpdb;

        if ($wpdb instanceof wpdb) {
            $table = $this->table_name($wpdb);
            $now   = current_time('mysql');

            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET total = CASE WHEN total > 0 THEN total - 1 ELSE 0 END, updated_at = %s WHERE post_id = %d AND reaction = %s",
                    $now,
                    $post_id,
                    $reaction
                )
            );

            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$table} WHERE post_id = %d AND reaction = %s AND total <= 0",
                    $post_id,
                    $reaction
                )
            );
        }

        return $this->get_counts($post_id);
    }

    public function reset_counts(?int $post_id = null): void
    {
        global $wpdb;

        if (!$wpdb instanceof wpdb) {
            return;
        }

        $table = $this->table_name($wpdb);

        if ($post_id !== null && $post_id > 0) {
            $wpdb->delete($table, ['post_id' => $post_id], ['%d']);
            return;
        }

        $wpdb->query("DELETE FROM {$table}");
    }

    public function render_inline(int $post_id): string
    {
        return $this->render_block('inline', $post_id);
    }

    public function render_sticky(): void
    {
        if (is_admin() || wp_doing_ajax() || !$this->is_enabled('sticky')) {
            return;
        }

        if (!is_singular()) {
            return;
        }

        $post_id = get_queried_object_id();
        if (!$post_id) {
            return;
        }

        $markup = $this->render_block('sticky', $post_id);

        if ($markup === '') {
            return;
        }

        echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function render_block(string $placement = 'inline', ?int $post_id = null): string
    {
        $placement = $placement === 'sticky' ? 'sticky' : 'inline';

        if ($placement === 'inline' && !$this->is_enabled('inline')) {
            return '';
        }

        if ($placement === 'sticky' && !$this->is_enabled('sticky')) {
            return '';
        }

        if ($post_id === null || $post_id <= 0) {
            $post = get_post();
            if ($post instanceof \WP_Post) {
                $post_id = (int) $post->ID;
            } else {
                $post_id = 0;
            }
        }

        if ($post_id <= 0) {
            return '';
        }

        wp_enqueue_style('your-share');
        wp_enqueue_script('your-share');

        return $this->render_markup($post_id, $placement);
    }

    public function current_user_reaction(int $post_id): string
    {
        $reactions = $this->current_user_reactions($post_id);

        return $reactions[0] ?? '';
    }

    /**
     * Retrieve the reactions stored for the current visitor.
     *
     * @return string[]
     */
    public function current_user_reactions(int $post_id): array
    {
        if ($post_id <= 0) {
            return [];
        }

        $name  = $this->cookie_name($post_id);
        $value = isset($_COOKIE[$name]) ? wp_unslash($_COOKIE[$name]) : '';

        return $this->normalize_reaction_list($value);
    }

    public function is_throttled(int $post_id): bool
    {
        // Users can now react with multiple emojis, so throttling is disabled.
        return false;
    }

    public function mark_reacted(int $post_id, string $reaction): void
    {
        if ($post_id <= 0 || !$this->is_valid_slug($reaction)) {
            return;
        }

        $current = $this->current_user_reactions($post_id);

        if (!in_array($reaction, $current, true)) {
            $current[] = $reaction;
        }

        $this->store_user_reactions($post_id, $current);
    }

    public function store_user_reactions(int $post_id, array $reactions): void
    {
        if ($post_id <= 0) {
            return;
        }

        $sanitized = $this->normalize_reaction_list($reactions);

        $name    = $this->cookie_name($post_id);
        $secure  = is_ssl();
        $path    = defined('COOKIEPATH') ? COOKIEPATH : '/';
        $domain  = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';
        $ttl     = $this->cookie_lifetime();

        if (empty($sanitized)) {
            setcookie($name, '', time() - 3600, $path, $domain, $secure, true);
            unset($_COOKIE[$name]);

            return;
        }

        $value   = wp_json_encode($sanitized);
        $expires = time() + $ttl;

        setcookie($name, $value, $expires, $path, $domain, $secure, true);
        $_COOKIE[$name] = $value;
    }

    public function cookie_name(int $post_id): string
    {
        return self::COOKIE_PREFIX . absint($post_id);
    }

    public function cookie_lifetime(): int
    {
        return defined('YEAR_IN_SECONDS') ? YEAR_IN_SECONDS : self::DEFAULT_COOKIE_TTL;
    }

    public function storage_key(): string
    {
        return self::STORAGE_KEY;
    }

    private function render_markup(int $post_id, string $placement): string
    {
        $active = $this->active_emojis();

        if ($post_id <= 0 || empty($active)) {
            return '';
        }

        $options   = $this->options->all();
        $defaults  = $this->options->defaults();
        $gap       = absint($options['share_gap'] ?? $defaults['share_gap']);
        $radius    = absint($options['share_radius'] ?? $defaults['share_radius']);
        $sticky_gap    = absint($options['sticky_gap'] ?? $defaults['sticky_gap']);
        $sticky_radius = absint($options['sticky_radius'] ?? $defaults['sticky_radius']);
        $style     = '';
        $classes   = ['waki-reactions'];
        $user_reactions = $this->current_user_reactions($post_id);

        if ($placement === 'sticky') {
            $classes[] = 'waki-reactions-floating';
            $position  = $options['sticky_position'] ?? $defaults['sticky_position'];
            $classes[] = 'pos-' . sanitize_html_class($position ?: 'left');
            $style    .= sprintf('--waki-reaction-gap:%dpx;', $sticky_gap ?: $defaults['sticky_gap']);
            $style    .= sprintf('--waki-reaction-radius:%dpx;', $sticky_radius ?: $defaults['sticky_radius']);
            $style    .= sprintf('--waki-breakpoint:%dpx;', intval($options['sticky_breakpoint'] ?? $defaults['sticky_breakpoint']));
        } else {
            $classes[] = 'waki-reactions-inline';
            $style    .= sprintf('--waki-reaction-gap:%dpx;', $gap ?: $defaults['share_gap']);
            $style    .= sprintf('--waki-reaction-radius:%dpx;', $radius ?: $defaults['share_radius']);
        }

        $attributes = [
            'class'                     => implode(' ', array_filter($classes)),
            'data-your-share-reactions' => '1',
            'data-post'                 => absint($post_id),
            'data-placement'            => $placement,
            'style'                     => $style,
        ];

        if (!empty($user_reactions)) {
            $attributes['data-user'] = implode(',', $user_reactions);
        }

        $attributes = $this->format_attributes($attributes);
        $counts     = $this->get_counts($post_id);

        ob_start();
        ?>
        <div<?php echo $attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <?php foreach ($active as $slug => $data) :
                $label      = $data['label'];
                $emoji      = $data['emoji'];
                $count      = $counts[$slug] ?? 0;
                $is_current = in_array($slug, $user_reactions, true);
                ?>
                <button
                    type="button"
                    class="waki-reaction<?php echo $is_current ? ' is-active' : ''; ?>"
                    data-reaction="<?php echo esc_attr($slug); ?>"
                    aria-pressed="<?php echo $is_current ? 'true' : 'false'; ?>"
                    aria-label="<?php echo esc_attr(sprintf(__('React with %s', $this->text_domain), $label)); ?>"
                >
                    <span class="waki-reaction-emoji" aria-hidden="true"><?php echo esc_html($emoji); ?></span>
                    <span class="waki-reaction-label"><?php echo esc_html($label); ?></span>
                    <span class="waki-reaction-count" data-your-share-reaction-count><?php echo esc_html((string) $count); ?></span>
                </button>
            <?php endforeach; ?>
        </div>
        <?php
        $markup = trim((string) ob_get_clean());

        return (string) apply_filters('your_share_reactions_markup', $markup, $post_id, $placement, $active, $counts);
    }

    /**
     * Normalise a list of reactions coming from cookies, storage, or user input.
     *
     * @param mixed $value Raw reaction list.
     *
     * @return string[]
     */
    private function normalize_reaction_list($value): array
    {
        $library = $this->emojis();
        $output  = [];

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                $value = $decoded;
            }
        }

        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (!is_array($value)) {
            $value = [];
        }

        foreach ($value as $key => $item) {
            $slug = is_int($key) ? (string) $item : (string) $key;
            $slug = sanitize_key($slug);

            if ($slug === '' || !isset($library[$slug])) {
                continue;
            }

            $enabled = true;

            if (is_bool($item) || is_int($item)) {
                $enabled = !empty($item);
            }

            if (!$enabled) {
                continue;
            }

            if (!in_array($slug, $output, true)) {
                $output[] = $slug;
            }
        }

        return $output;
    }

    private function script_settings(): array
    {
        $active = $this->active_emojis();
        $data   = [];

        foreach ($active as $slug => $info) {
            $data[] = [
                'slug'  => $slug,
                'emoji' => $info['emoji'],
                'label' => $info['label'],
            ];
        }

        return [
            'enabled' => [
                'inline' => $this->is_enabled('inline'),
                'sticky' => $this->is_enabled('sticky'),
            ],
            'emojis'   => $data,
            'rest'     => [
                'root'  => trailingslashit(rest_url('your-share/v1')),
                'nonce' => wp_create_nonce('wp_rest'),
            ],
            'throttle' => [
                'cookiePrefix' => self::COOKIE_PREFIX,
                'storageKey'   => self::STORAGE_KEY,
                'cookieTtl'    => $this->cookie_lifetime(),
            ],
        ];
    }

    private function table_name(wpdb $wpdb): string
    {
        return $wpdb->prefix . 'yourshare_reactions';
    }

    private function format_attributes(array $attributes): string
    {
        $output = '';

        foreach ($attributes as $key => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            $output .= sprintf(' %s="%s"', esc_attr($key), esc_attr((string) $value));
        }

        return $output;
    }
}
