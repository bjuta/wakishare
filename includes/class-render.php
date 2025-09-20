<?php

namespace YourShare;

if (!defined('ABSPATH')) {
    exit;
}

class Render
{
    /** @var Options */
    private $options;

    /** @var Networks */
    private $networks;

    /** @var UTM */
    private $utm;

    /** @var Icons */
    private $icons;

    /** @var string */
    private $text_domain;

    /** @var Counts|null */
    private $counts;

    public function __construct(Options $options, Networks $networks, UTM $utm, Icons $icons, string $text_domain, ?Counts $counts = null)
    {
        $this->options     = $options;
        $this->networks    = $networks;
        $this->utm         = $utm;
        $this->icons       = $icons;
        $this->text_domain = $text_domain;
        $this->counts      = $counts;
    }

    public function render(array $context, array $atts): string
    {
        $opts      = $this->options->all();
        $map       = $this->networks->all();
        $share_ctx = $this->current_share_context($atts);
        $allowed   = array_keys($map);

        $geo       = $this->resolve_geo_country($opts, $context, $atts);
        $matrix    = $opts['smart_share_matrix'] ?? [];
        $networks  = $this->prepare_networks($atts['networks'] ?? '', $allowed);
        if (empty($networks)) {
            $networks = $this->networks_for_country($geo['country'], $matrix, $allowed);
        }

        if (empty($networks)) {
            $defaults = $opts['share_networks_default'];
            if (!is_array($defaults) || empty($defaults)) {
                $defaults = $this->options->defaults()['share_networks_default'];
            }
            $networks = $this->prepare_networks($defaults, $allowed);
        }

        if (!in_array('native', $networks, true)) {
            $networks[] = 'native';
        }

        $classes = [
            'waki-share',
            'waki-size-' . sanitize_html_class($atts['size'] ?? 'md'),
            'waki-style-' . sanitize_html_class($atts['style'] ?? 'solid'),
            'waki-labels-' . sanitize_html_class($atts['labels'] ?? 'auto'),
            !empty($atts['brand']) && (string) $atts['brand'] === '1' ? 'is-brand' : 'is-mono',
        ];

        $defaults = $this->options->defaults();
        $placement = $context['placement'] ?? 'inline';

        if ($placement === 'floating') {
            $gap    = absint($opts['sticky_gap'] ?? $defaults['sticky_gap']);
            $radius = absint($opts['sticky_radius'] ?? $defaults['sticky_radius']);
        } else {
            $gap    = absint($opts['share_gap'] ?? $defaults['share_gap']);
            $radius = absint($opts['share_radius'] ?? $defaults['share_radius']);
        }

        if ($gap <= 0) {
            $gap = absint($placement === 'floating' ? $defaults['sticky_gap'] : $defaults['share_gap']);
        }

        if ($radius <= 0) {
            $radius = absint($placement === 'floating' ? $defaults['sticky_radius'] : $defaults['share_radius']);
        }

        $style_inline = sprintf('--waki-gap:%dpx;--waki-radius:%dpx;', $gap, $radius);

        if ($placement === 'floating') {
            $classes[]    = 'waki-share-floating';
            $classes[]    = 'pos-' . sanitize_html_class($context['position'] ?? 'left');
            $style_inline .= sprintf('--waki-breakpoint:%dpx;', intval($context['breakpoint'] ?? 1024));
        } else {
            $classes[] = 'waki-share-inline';
            $classes[] = 'align-' . sanitize_html_class($context['align'] ?? 'left');
        }

        $title = $share_ctx['title'];
        $base  = $share_ctx['url'];

        $counts_state = $this->prepare_counts_state($opts, $networks, $share_ctx);

        if ($counts_state['enabled']) {
            $classes[] = 'waki-has-counts';
        }

        ob_start();
        ?>
        <?php $data_attrs = $this->format_attributes([
            'data-your-share-country'        => $geo['country'],
            'data-your-share-country-source' => $geo['source'],
            'data-your-share-counts'         => $counts_state['enabled'] ? '1' : '',
            'data-your-share-post'           => $counts_state['post'],
            'data-your-share-networks'       => $counts_state['network_list'],
            'data-your-share-count-url'      => $counts_state['url'],
            'data-your-share-count-ttl'      => $counts_state['ttl'],
        ]); ?>
        <div class="<?php echo esc_attr(implode(' ', $classes)); ?>" style="<?php echo esc_attr($style_inline); ?>"<?php echo $data_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <?php if ($counts_state['enabled'] && !empty($opts['counts_show_total'])) : ?>
                <div class="waki-share-total" data-your-share-total>
                    <span class="waki-total-label"><?php esc_html_e('Total shares', $this->text_domain); ?></span>
                    <span class="waki-total-value" data-your-share-total-value data-value="<?php echo esc_attr((string) $counts_state['total']); ?>"><?php echo esc_html($this->format_count($counts_state['total'])); ?></span>
                </div>
            <?php endif; ?>
            <?php foreach ($networks as $network) :
                if (!isset($map[$network])) {
                    continue;
                }

                [$label] = $map[$network];

                if ($network === 'copy' || $network === 'native') {
                    $href = '#';
                } else {
                    $utm_url = $this->utm->append($base, $network, $share_ctx['post'], $atts);
                    $href    = $this->build_share_url($network, $utm_url, $title);
                }

                $count_value = $counts_state['networks'][$network]['total'] ?? 0;
                $attr = [
                    'class'      => 'waki-btn',
                    'data-net'   => esc_attr($network),
                    'aria-label' => esc_attr(sprintf(__('Share on %s', $this->text_domain), $label)),
                    'role'       => 'link',
                ];

                if ($network !== 'copy' && $network !== 'native') {
                    $attr['href']   = esc_url($href);
                    $attr['target'] = '_blank';
                    $attr['rel']    = 'noopener noreferrer';
                } else {
                    $attr['href'] = '#';
                }

                $attr = apply_filters('your_share_button_attributes', $attr, $network, $context, $atts, $share_ctx);
                ?>
                <a <?php foreach ($attr as $key => $value) { echo esc_attr($key) . '="' . esc_attr($value) . '" '; } ?>>
                    <?php echo $this->icons->svg($network); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <span class="waki-label"><?php echo esc_html($label); ?></span>
                    <?php if ($counts_state['enabled'] && !empty($opts['counts_show_badges'])) : ?>
                        <span class="waki-count" data-your-share-count="<?php echo esc_attr($network); ?>" data-value="<?php echo esc_attr((string) $count_value); ?>"><?php echo esc_html($this->format_count($count_value)); ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php
        return trim((string) ob_get_clean());
    }

    private function current_share_context(array $atts): array
    {
        $post  = get_post();
        $title = $atts['title'] ?? '';

        if ($title === '') {
            $title = $post ? get_the_title($post) : get_bloginfo('name');
        }

        $url = $atts['url'] ?? '';

        if ($url === '') {
            $url = $post ? (function_exists('wp_get_canonical_url') ? wp_get_canonical_url($post) : get_permalink($post)) : '';
        }

        if (!$url) {
            $url = home_url('/');
        }

        return [
            'post'  => $post,
            'title' => wp_strip_all_tags($title),
            'url'   => $url,
        ];
    }

    private function prepare_networks($list, array $allowed): array
    {
        if (is_array($list)) {
            $nets = $list;
        } else {
            $nets = explode(',', (string) $list);
        }

        $nets = array_filter(array_map(static function ($value) {
            return sanitize_key($value);
        }, $nets));

        $nets = array_values(array_intersect($nets, $allowed));

        return $nets;
    }

    private function networks_for_country(string $country, array $matrix, array $allowed): array
    {
        $country = strtoupper($country);

        if ($country === '' || empty($matrix[$country])) {
            return [];
        }

        return $this->prepare_networks($matrix[$country], $allowed);
    }

    private function resolve_geo_country(array $options, array $context, array $atts): array
    {
        $geo_source = $options['geo_source'] ?? 'auto';
        $country    = '';
        $source     = '';

        if ($geo_source !== 'manual') {
            $cf_header = $_SERVER['HTTP_CF_IPCOUNTRY'] ?? '';
            if (is_string($cf_header)) {
                $cf_header = strtoupper(substr(sanitize_text_field(wp_unslash($cf_header)), 0, 2));
            } else {
                $cf_header = '';
            }

            if (preg_match('/^[A-Z]{2}$/', $cf_header) && $cf_header !== 'XX') {
                $country = $cf_header;
                $source  = 'cloudflare';
            }
        }

        if ($country === '' && $geo_source === 'auto') {
            $accept_language = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
            if (is_string($accept_language)) {
                $accept_language = sanitize_text_field(wp_unslash($accept_language));
            } else {
                $accept_language = '';
            }

            $guessed = $this->country_from_accept_language($accept_language);
            if ($guessed !== '') {
                $country = $guessed;
                $source  = 'accept-language';
            }
        }

        $country = apply_filters('your_share_geo_country', $country, $geo_source, $options, $context, $atts);
        if (!is_string($country)) {
            $country = '';
        }
        $country = strtoupper(substr($country, 0, 2));

        if (!preg_match('/^[A-Z]{2}$/', $country)) {
            $country = '';
        }

        if ($country === '') {
            $source = $source ?: 'unknown';
        }

        return [
            'country' => $country,
            'source'  => $source ?: 'unknown',
        ];
    }

    private function country_from_accept_language(string $header): string
    {
        if ($header === '') {
            return '';
        }

        $locales = explode(',', $header);

        foreach ($locales as $locale) {
            $locale = trim($locale);

            if ($locale === '') {
                continue;
            }

            $token = explode(';', $locale)[0];
            $token = trim($token);

            $country = $this->country_from_locale_token($token);

            if ($country !== '') {
                return $country;
            }
        }

        return '';
    }

    private function country_from_locale_token(string $token): string
    {
        if ($token === '') {
            return '';
        }

        $parts = preg_split('/[-_]/', $token);

        if (!$parts || count($parts) < 2) {
            return '';
        }

        $country = strtoupper(substr($parts[1], 0, 2));

        if (!preg_match('/^[A-Z]{2}$/', $country)) {
            return '';
        }

        return $country;
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

    private function prepare_counts_state(array $options, array $networks, array $share_ctx): array
    {
        $post_id = $share_ctx['post'] instanceof \WP_Post ? (int) $share_ctx['post']->ID : 0;
        $url     = $share_ctx['url'];

        $trackable = array_values(array_filter($networks, static function ($network) {
            return !in_array($network, ['copy', 'native'], true);
        }));

        $state = [
            'enabled'      => false,
            'total'        => 0,
            'ttl'          => '',
            'networks'     => [],
            'post'         => $post_id > 0 ? (string) $post_id : ($trackable ? '0' : ''),
            'network_list' => $trackable ? implode(',', $trackable) : '',
            'url'          => $trackable ? esc_url_raw($url) : '',
        ];

        if (!$this->counts instanceof Counts) {
            return $state;
        }

        if (empty($options['counts_enabled']) || empty($trackable)) {
            return $state;
        }

        $counts = $this->counts->get_counts($post_id, $url, $trackable);

        if (empty($counts['enabled'])) {
            return $state;
        }

        foreach ($trackable as $network) {
            if (!isset($counts['networks'][$network])) {
                $counts['networks'][$network] = [
                    'total' => 0,
                ];
            }
        }

        $state['enabled']  = true;
        $state['networks'] = $counts['networks'];
        $state['total']    = isset($counts['total']) ? (int) $counts['total'] : 0;
        $state['ttl']      = isset($counts['ttl']) ? (int) $counts['ttl'] : 0;

        return $state;
    }

    private function format_count(int $value): string
    {
        if ($value >= 1000000) {
            $formatted = round($value / 1000000, 1);
            if (abs($formatted - floor($formatted)) < 0.1) {
                $formatted = (int) $formatted;
            }

            return sprintf('%sM', $formatted);
        }

        if ($value >= 1000) {
            $formatted = round($value / 1000, 1);
            if (abs($formatted - floor($formatted)) < 0.1) {
                $formatted = (int) $formatted;
            }

            return sprintf('%sK', $formatted);
        }

        return number_format_i18n($value);
    }

    private function build_share_url(string $network, string $base_url, string $title): string
    {
        $url   = rawurlencode($base_url);
        $title = rawurlencode($title);

        switch ($network) {
            case 'facebook':
                return "https://www.facebook.com/sharer/sharer.php?u={$url}";
            case 'x':
                return "https://twitter.com/intent/tweet?text={$title}&url={$url}";
            case 'whatsapp':
                return "https://wa.me/?text={$title}%20{$url}";
            case 'telegram':
                return "https://t.me/share/url?url={$url}&text={$title}";
            case 'linkedin':
                return "https://www.linkedin.com/sharing/share-offsite/?url={$url}";
            case 'reddit':
                return "https://www.reddit.com/submit?url={$url}&title={$title}";
            case 'email':
                return "mailto:?subject={$title}&body={$title}%20—%20{$url}";
            default:
                return $base_url;
        }
    }
}
