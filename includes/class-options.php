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
            'share_networks_default'    => ['facebook', 'x', 'whatsapp', 'telegram', 'linkedin', 'reddit', 'email', 'copy'],
            'sticky_enabled'            => 0,
            'sticky_position'           => 'left',
            'sticky_breakpoint'         => 1024,
            'sticky_gap'                => 8,
            'sticky_radius'             => 9999,
            'sticky_selectors'          => '.entry-content',
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
        ];

        // Backwards compatibility aliases for legacy code paths.
        $defaults['brand_colors']        = $defaults['share_brand_colors'];
        $defaults['style']               = $defaults['share_style'];
        $defaults['size']                = $defaults['share_size'];
        $defaults['labels']              = $defaults['share_labels'];
        $defaults['networks']            = implode(',', $defaults['share_networks_default']);
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

        $options = wp_parse_args($stored, $this->defaults());

        $options['share_networks_default'] = $this->normalize_networks($options['share_networks_default']);
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

        $output['share_gap']    = max(0, intval($input['share_gap'] ?? $defaults['share_gap']));
        $output['share_radius'] = max(0, intval($input['share_radius'] ?? $defaults['share_radius']));

        $output['sticky_enabled']     = !empty($input['sticky_enabled']) ? 1 : 0;
        $output['sticky_position']    = in_array($input['sticky_position'] ?? '', ['left', 'right'], true)
            ? $input['sticky_position'] : $defaults['sticky_position'];
        $output['sticky_breakpoint']  = max(0, intval($input['sticky_breakpoint'] ?? $defaults['sticky_breakpoint']));
        $output['sticky_gap']         = max(0, intval($input['sticky_gap'] ?? $defaults['sticky_gap']));
        $output['sticky_radius']      = max(0, intval($input['sticky_radius'] ?? $defaults['sticky_radius']));
        $output['sticky_selectors']   = sanitize_text_field($input['sticky_selectors'] ?? $defaults['sticky_selectors']);

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
