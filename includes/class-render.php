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

    public function __construct(Options $options, Networks $networks, UTM $utm, Icons $icons, string $text_domain)
    {
        $this->options     = $options;
        $this->networks    = $networks;
        $this->utm         = $utm;
        $this->icons       = $icons;
        $this->text_domain = $text_domain;
    }

    public function render(array $context, array $atts): string
    {
        $opts      = $this->options->all();
        $map       = $this->networks->all();
        $share_ctx = $this->current_share_context($atts);
        $networks  = $this->prepare_networks($atts['networks'] ?? '', array_keys($map));

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

        $gap    = absint($opts['gap']);
        $radius = absint($opts['radius']);

        if ($gap <= 0) {
            $gap = absint($this->options->defaults()['gap']);
        }

        if ($radius <= 0) {
            $radius = absint($this->options->defaults()['radius']);
        }

        $style_inline = sprintf('--waki-gap:%dpx;--waki-radius:%dpx;', $gap, $radius);

        if (($context['placement'] ?? '') === 'floating') {
            $classes[]    = 'waki-share-floating';
            $classes[]    = 'pos-' . sanitize_html_class($context['position'] ?? 'left');
            $style_inline .= sprintf('--waki-breakpoint:%dpx;', intval($context['breakpoint'] ?? 1024));
        } else {
            $classes[] = 'waki-share-inline';
            $classes[] = 'align-' . sanitize_html_class($context['align'] ?? 'left');
        }

        $title = $share_ctx['title'];
        $base  = $share_ctx['url'];

        ob_start();
        ?>
        <div class="<?php echo esc_attr(implode(' ', $classes)); ?>" style="<?php echo esc_attr($style_inline); ?>">
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

    private function prepare_networks(string $list, array $allowed): array
    {
        $nets = array_filter(array_map('trim', explode(',', strtolower($list))));
        $nets = array_values(array_intersect($nets, $allowed));

        return $nets;
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
