<?php

namespace YourShare;

if (!defined('ABSPATH')) {
    exit;
}

class Options
{
    /** @var string */
    private $option_key;

    public function __construct(string $option_key)
    {
        $this->option_key = $option_key;
    }

    public function key(): string
    {
        return $this->option_key;
    }

    public function defaults(): array
    {
        $defaults = [
            'share_brand_colors'        => 1,
            'share_style'               => 'solid',
            'share_size'                => 'md',
            'share_labels'              => 'auto',
            'share_align'               => 'left',
            'share_gap'                 => 8,
            'share_radius'              => 9999,
            'share_inline_auto_enabled' => 0,
            'share_inline_post_types'   => ['post', 'page'],
            'share_inline_position'     => 'after',
            'share_networks_default'    => ['facebook', 'x', 'whatsapp', 'telegram', 'linkedin', 'reddit', 'email', 'copy'],
            'share_inline_networks'     => [],
            'follow_networks'           => ['x', 'instagram', 'facebook-page', 'tiktok', 'youtube', 'linkedin'],
            'follow_profiles'           => [
                'x'             => '',
                'instagram'     => '',
                'facebook-page' => '',
                'tiktok'        => '',
                'youtube'       => '',
                'linkedin'      => '',
            ],
            'sticky_enabled'            => 0,
            'sticky_position'           => 'left',
            'sticky_breakpoint'         => 1024,
            'sticky_gap'                => 8,
            'sticky_radius'             => 9999,
            'sticky_selectors'          => '.entry-content',
            'reactions_inline_enabled'  => 0,
            'reactions_sticky_enabled'  => 0,
            'reactions_enabled'         => $this->default_reaction_toggles(),
            'smart_share_enabled'       => 0,
            'smart_share_selectors'     => ".entry-content h2, .entry-content h3",
            'smart_share_matrix'        => [
                'US' => ['facebook', 'x', 'linkedin', 'reddit', 'email'],
                'GB' => ['facebook', 'x', 'linkedin', 'email', 'copy'],
                'CA' => ['facebook', 'x', 'linkedin', 'email', 'copy'],
                'AU' => ['facebook', 'x', 'linkedin', 'email', 'copy'],
                'IN' => ['whatsapp', 'facebook', 'telegram', 'email', 'copy'],
                'BR' => ['whatsapp', 'facebook', 'telegram', 'email', 'copy'],
                'DE' => ['whatsapp', 'facebook', 'linkedin', 'email', 'copy'],
            ],
            'media_overlay_selectors'   => ".entry-content img, .entry-content video, .entry-content iframe[src*='youtube'], .entry-content iframe[src*='vimeo']",
            'media_overlay_position'    => 'top-end',
            'media_overlay_trigger'     => 'hover',
            'geo_source'                => 'auto',
            'enable_utm'                => 1,
            'utm_medium'                => 'social',
            'utm_campaign'              => '',
            'utm_term'                  => '',
            'utm_content'               => 'post-{ID}-share',
            'analytics_events'          => 1,
            'analytics_console'         => 0,
            'analytics_ga4'             => 0,
            'counts_enabled'            => 0,
            'counts_show_badges'        => 1,
            'counts_show_total'         => 1,
            'counts_refresh_interval'   => 60,
            'counts_badge_radius'       => 9999,
            'counts_facebook_app_id'    => '',
            'counts_facebook_app_secret'=> '',
            'counts_reddit_app_id'      => '',
            'counts_reddit_app_secret'  => '',
        ];

        // Backwards compatibility aliases for legacy code paths.
        $defaults['brand_colors']        = $defaults['share_brand_colors'];
        $defaults['style']               = $defaults['share_style'];
        $defaults['size']                = $defaults['share_size'];
        $defaults['labels']              = $defaults['share_labels'];
        $defaults['share_inline_networks'] = $defaults['share_networks_default'];
        $defaults['networks']              = implode(',', $defaults['share_networks_default']);
        $defaults['floating_enabled']    = $defaults['sticky_enabled'];
        $defaults['floating_position']   = $defaults['sticky_position'];
        $defaults['floating_breakpoint'] = $defaults['sticky_breakpoint'];
        $defaults['gap']                 = (string) $defaults['share_gap'];
        $defaults['radius']              = (string) $defaults['share_radius'];

        return $defaults;
    }

    public function all(): array
    {
        $stored = get_option($this->option_key, []);

        if (!is_array($stored)) {
            $stored = [];
        }

        $defaults = $this->defaults();
        $options  = wp_parse_args($stored, $defaults);

        $options['share_networks_default'] = $this->normalize_networks($options['share_networks_default']);
        $inline_defaults = $defaults['share_inline_networks'] ?? $options['share_networks_default'];
        $inline_networks = $options['share_inline_networks'] ?? $inline_defaults;
        $inline_networks = $this->normalize_networks($inline_networks);
        if (empty($inline_networks)) {
            $inline_networks = $options['share_networks_default'];
        }
        $options['share_inline_networks'] = $inline_networks;
        $options['networks']               = implode(',', $options['share_networks_default']);
        $options['brand_colors']           = (int) $options['share_brand_colors'];
        $options['smart_share_matrix']     = $this->normalize_matrix($options['smart_share_matrix']);
        $options['style']                  = $options['share_style'];
        $options['size']                   = $options['share_size'];
        $options['labels']                 = $options['share_labels'];
        $options['floating_enabled']       = (int) $options['sticky_enabled'];
        $options['floating_position']      = $options['sticky_position'];
        $options['floating_breakpoint']    = $options['sticky_breakpoint'];
        $options['gap']                    = (string) $options['share_gap'];
        $options['radius']                 = (string) $options['share_radius'];
        $options['share_inline_auto_enabled'] = !empty($options['share_inline_auto_enabled']) ? 1 : 0;
        $options['share_inline_post_types']   = $this->normalize_post_types(
            $options['share_inline_post_types'] ?? $defaults['share_inline_post_types'],
            $defaults['share_inline_post_types']
        );
        $position = sanitize_key($options['share_inline_position'] ?? $defaults['share_inline_position']);
        $options['share_inline_position'] = in_array($position, ['after', 'before', 'both'], true)
            ? $position : $defaults['share_inline_position'];
        $options['reactions_inline_enabled'] = !empty($options['reactions_inline_enabled']) ? 1 : 0;
        $options['reactions_sticky_enabled'] = !empty($options['reactions_sticky_enabled']) ? 1 : 0;
        $options['reactions_enabled']        = $this->normalize_reaction_toggles($options['reactions_enabled'] ?? [], true);
        $options['counts_enabled']         = !empty($options['counts_enabled']) ? 1 : 0;
        $options['counts_show_badges']     = !empty($options['counts_show_badges']) ? 1 : 0;
        $options['counts_show_total']      = !empty($options['counts_show_total']) ? 1 : 0;
        $options['counts_refresh_interval']= max(0, (int) $options['counts_refresh_interval']);
        $options['counts_badge_radius']    = max(0, (int) ($options['counts_badge_radius'] ?? $defaults['counts_badge_radius']));
        $options['follow_networks']        = $this->normalize_follow_networks($options['follow_networks'], $defaults['follow_networks']);
        $options['follow_profiles']        = $this->normalize_follow_profiles($options['follow_profiles'], $defaults['follow_profiles']);
        return $options;
    }

    public function sanitize(array $input): array
    {
        $defaults = $this->defaults();

        $output = [];
        $output['share_brand_colors'] = !empty($input['share_brand_colors']) ? 1 : 0;
        $output['share_style']        = in_array($input['share_style'] ?? '', ['solid', 'outline', 'ghost'], true)
            ? $input['share_style'] : $defaults['share_style'];
        $output['share_size']         = in_array($input['share_size'] ?? '', ['sm', 'md', 'lg'], true)
            ? $input['share_size'] : $defaults['share_size'];
        $output['share_labels']       = in_array($input['share_labels'] ?? '', ['auto', 'show', 'hide'], true)
            ? $input['share_labels'] : $defaults['share_labels'];
        $output['share_align']        = in_array($input['share_align'] ?? '', ['left', 'center', 'right', 'space-between'], true)
            ? $input['share_align'] : $defaults['share_align'];

        $networks = $input['share_networks_default'] ?? $defaults['share_networks_default'];
        if (is_string($networks)) {
            $networks = explode(',', $networks);
        }
        if (!is_array($networks)) {
            $networks = [];
        }
        $networks = $this->normalize_networks($networks);
        if (empty($networks)) {
            $networks = $defaults['share_networks_default'];
        }
        $output['share_networks_default'] = $networks;

        $inline_networks = $input['share_inline_networks'] ?? $defaults['share_inline_networks'];
        if (is_string($inline_networks)) {
            $inline_networks = explode(',', $inline_networks);
        }
        if (!is_array($inline_networks)) {
            $inline_networks = [];
        }
        $inline_networks = $this->normalize_networks($inline_networks);
        if (empty($inline_networks)) {
            $inline_networks = $networks;
        }
        $output['share_inline_networks'] = $inline_networks;

        $output['share_gap']    = max(0, intval($input['share_gap'] ?? $defaults['share_gap']));
        $output['share_radius'] = max(0, intval($input['share_radius'] ?? $defaults['share_radius']));

        $output['share_inline_auto_enabled'] = !empty($input['share_inline_auto_enabled']) ? 1 : 0;
        $output['share_inline_post_types']   = $this->normalize_post_types(
            $input['share_inline_post_types'] ?? $defaults['share_inline_post_types'],
            $defaults['share_inline_post_types']
        );
        $position = sanitize_key($input['share_inline_position'] ?? $defaults['share_inline_position']);
        $allowed_positions = ['after', 'before', 'both'];
        if (!in_array($position, $allowed_positions, true)) {
            $position = $defaults['share_inline_position'];
        }
        $output['share_inline_position'] = $position;

        $output['follow_networks'] = $this->normalize_follow_networks($input['follow_networks'] ?? $defaults['follow_networks'], $defaults['follow_networks']);
        $output['follow_profiles'] = $this->normalize_follow_profiles($input['follow_profiles'] ?? [], $defaults['follow_profiles']);

        $output['sticky_enabled']     = !empty($input['sticky_enabled']) ? 1 : 0;
        $output['sticky_position']    = in_array($input['sticky_position'] ?? '', ['left', 'right'], true)
            ? $input['sticky_position'] : $defaults['sticky_position'];
        $output['sticky_breakpoint']  = max(0, intval($input['sticky_breakpoint'] ?? $defaults['sticky_breakpoint']));
        $output['sticky_gap']         = max(0, intval($input['sticky_gap'] ?? $defaults['sticky_gap']));
        $output['sticky_radius']      = max(0, intval($input['sticky_radius'] ?? $defaults['sticky_radius']));
        $output['sticky_selectors']   = sanitize_text_field($input['sticky_selectors'] ?? $defaults['sticky_selectors']);

        $output['reactions_inline_enabled'] = !empty($input['reactions_inline_enabled']) ? 1 : 0;
        $output['reactions_sticky_enabled'] = !empty($input['reactions_sticky_enabled']) ? 1 : 0;
        $output['reactions_enabled']        = $this->normalize_reaction_toggles($input['reactions_enabled'] ?? [], false);
        if (!array_filter($output['reactions_enabled'])) {
            $output['reactions_enabled'] = $this->default_reaction_toggles();
        }

        $output['smart_share_enabled']   = !empty($input['smart_share_enabled']) ? 1 : 0;
        $output['smart_share_selectors'] = sanitize_textarea_field($input['smart_share_selectors'] ?? $defaults['smart_share_selectors']);
        $output['smart_share_matrix']    = $this->normalize_matrix($input['smart_share_matrix'] ?? $defaults['smart_share_matrix']);
        if (empty($output['smart_share_matrix'])) {
            $output['smart_share_matrix'] = $this->normalize_matrix($defaults['smart_share_matrix']);
        }

        $output['media_overlay_selectors'] = sanitize_textarea_field($input['media_overlay_selectors'] ?? $defaults['media_overlay_selectors']);

        $position = sanitize_key($input['media_overlay_position'] ?? $defaults['media_overlay_position']);
        $allowed_positions = ['top-start', 'top-end', 'bottom-start', 'bottom-end', 'center'];
        if (!in_array($position, $allowed_positions, true)) {
            $position = $defaults['media_overlay_position'];
        }
        $output['media_overlay_position'] = $position;

        $trigger = sanitize_key($input['media_overlay_trigger'] ?? $defaults['media_overlay_trigger']);
        $allowed_triggers = ['hover', 'always'];
        if (!in_array($trigger, $allowed_triggers, true)) {
            $trigger = $defaults['media_overlay_trigger'];
        }
        $output['media_overlay_trigger'] = $trigger;

        $geo_source = sanitize_key($input['geo_source'] ?? $defaults['geo_source']);
        if (!in_array($geo_source, ['auto', 'ip', 'manual'], true)) {
            $geo_source = $defaults['geo_source'];
        }
        $output['geo_source'] = $geo_source;

        $output['enable_utm']  = !empty($input['enable_utm']) ? 1 : 0;
        $output['utm_medium']  = sanitize_text_field($input['utm_medium'] ?? $defaults['utm_medium']);
        $output['utm_campaign'] = sanitize_text_field($input['utm_campaign'] ?? '');
        $output['utm_term']    = sanitize_text_field($input['utm_term'] ?? '');
        $output['utm_content'] = sanitize_text_field($input['utm_content'] ?? $defaults['utm_content']);

        $output['analytics_events']  = !empty($input['analytics_events']) ? 1 : 0;
        $output['analytics_console'] = !empty($input['analytics_console']) ? 1 : 0;
        $output['analytics_ga4']     = !empty($input['analytics_ga4']) ? 1 : 0;
        $output['counts_enabled']    = !empty($input['counts_enabled']) ? 1 : 0;
        $output['counts_show_badges'] = !empty($input['counts_show_badges']) ? 1 : 0;
        $output['counts_show_total'] = !empty($input['counts_show_total']) ? 1 : 0;
        $output['counts_refresh_interval'] = max(0, intval($input['counts_refresh_interval'] ?? $defaults['counts_refresh_interval']));
        $output['counts_badge_radius'] = max(0, intval($input['counts_badge_radius'] ?? $defaults['counts_badge_radius']));
        $output['counts_facebook_app_id']     = sanitize_text_field($input['counts_facebook_app_id'] ?? '');
        $output['counts_facebook_app_secret'] = sanitize_text_field($input['counts_facebook_app_secret'] ?? '');
        $output['counts_reddit_app_id']       = sanitize_text_field($input['counts_reddit_app_id'] ?? '');
        $output['counts_reddit_app_secret']   = sanitize_text_field($input['counts_reddit_app_secret'] ?? '');

        // Legacy aliases to keep existing rendering logic functioning.
        $output['brand_colors']        = $output['share_brand_colors'];
        $output['style']               = $output['share_style'];
        $output['size']                = $output['share_size'];
        $output['labels']              = $output['share_labels'];
        $output['networks']            = implode(',', $output['share_networks_default']);
        $output['floating_enabled']    = $output['sticky_enabled'];
        $output['floating_position']   = $output['sticky_position'];
        $output['floating_breakpoint'] = $output['sticky_breakpoint'];
        $output['gap']                 = (string) $output['share_gap'];
        $output['radius']              = (string) $output['share_radius'];

        unset($output['current_tab']);

        return $output;
    }

    private function default_reaction_toggles(): array
    {
        $defaults = [];

        foreach (Emoji_Library::defaults() as $slug) {
            $slug = sanitize_key((string) $slug);

            if ($slug === '') {
                continue;
            }

            $defaults[$slug] = 1;
        }

        if (empty($defaults)) {
            $library = Emoji_Library::all();
            $first   = array_key_first($library);

            if ($first !== null) {
                $defaults[$first] = 1;
            }
        }

        return $defaults;
    }

    private function normalize_reaction_toggles($value, bool $fallback_to_defaults): array
    {
        $enabled = Emoji_Library::sanitize_slugs($value);

        if (empty($enabled) && $fallback_to_defaults) {
            $enabled = Emoji_Library::defaults();
        }

        if (empty($enabled)) {
            $library = Emoji_Library::all();
            $first   = array_key_first($library);

            if ($first !== null) {
                $enabled = [$first];
            }
        }

        $normalized = [];

        foreach ($enabled as $slug) {
            $normalized[$slug] = 1;
        }

        return $normalized;
    }

    /**
     * Normalise the stored network list into a clean array of slugs.
     *
     * @param mixed $value Raw value from user input / database.
     */
    private function normalize_networks($value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (!is_array($value)) {
            $value = [];
        }

        $value = array_filter(array_map(static function ($item) {
            $item = sanitize_key((string) $item);

            return $item !== '' ? $item : null;
        }, $value));

        return array_values(array_unique($value));
    }

    private function normalize_post_types($post_types, array $fallback = []): array
    {
        if (is_string($post_types)) {
            $post_types = array_map('trim', explode(',', $post_types));
        }

        if (!is_array($post_types)) {
            $post_types = [];
        }

        $post_types = array_map('sanitize_key', $post_types);
        $post_types = array_filter($post_types, static function ($value) {
            return $value !== '';
        });

        $allowed = get_post_types(['public' => true], 'names');
        if (!is_array($allowed)) {
            $allowed = [];
        }

        $allowed = array_values($allowed);
        $post_types = array_values(array_unique(array_intersect($post_types, $allowed)));

        if (empty($post_types) && !empty($fallback)) {
            $post_types = array_values(array_unique(array_intersect($fallback, $allowed)));
        }

        return $post_types;
    }

    private function normalize_follow_networks($value, array $allowed): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (!is_array($value)) {
            $value = [];
        }

        $value = array_filter(array_map(static function ($item) {
            return sanitize_key((string) $item);
        }, $value));

        $value = array_values(array_intersect($value, $allowed));

        foreach ($allowed as $slug) {
            if (!in_array($slug, $value, true)) {
                $value[] = $slug;
            }
        }

        return $value;
    }

    private function normalize_follow_profiles($value, array $defaults): array
    {
        if (!is_array($value)) {
            $value = [];
        }

        $profiles = [];

        foreach ($defaults as $slug => $default_url) {
            $url = '';

            if (isset($value[$slug])) {
                $candidate = trim((string) $value[$slug]);
                $url       = $candidate !== '' ? esc_url_raw($candidate) : '';
            }

            $profiles[$slug] = $url;
        }

        return $profiles;
    }

    /**
     * Normalise smart share matrix entries to a country => networks map.
     *
     * @param mixed $value Raw value from user input / database.
     */
    private function normalize_matrix($value): array
    {
        if (is_string($value)) {
            $lines = preg_split('/[\r\n]+/', $value);
            $pairs = [];

            foreach ($lines as $line) {
                $line = trim($line);

                if ($line === '') {
                    continue;
                }

                if (strpos($line, ':') === false) {
                    continue;
                }

                [$country, $networks] = array_map('trim', explode(':', $line, 2));
                $country               = strtoupper(substr($country, 0, 2));

                if (!preg_match('/^[A-Z]{2}$/', $country)) {
                    continue;
                }

                $pairs[$country] = $networks;
            }

            $value = $pairs;
        }

        if (!is_array($value)) {
            return [];
        }

        $matrix = [];

        foreach ($value as $country => $networks) {
            if (is_int($country)) {
                continue;
            }

            $country = strtoupper(substr((string) $country, 0, 2));

            if (!preg_match('/^[A-Z]{2}$/', $country)) {
                continue;
            }

            $normalized = $this->normalize_networks($networks);

            if (empty($normalized)) {
                continue;
            }

            $matrix[$country] = $normalized;
        }

        return $matrix;
    }
}
