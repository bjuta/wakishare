<?php

namespace YourShare;

if (!defined('ABSPATH')) {
    exit;
}

class Icons
{
    /** @var string */
    private $icons_path;

    /** @var array<string, string> */
    private $cache = [];

    public function __construct(string $icons_path)
    {
        $this->icons_path = trailingslashit($icons_path);
    }

    public function svg(string $network): string
    {
        if (!array_key_exists($network, $this->cache)) {
            $this->cache[$network] = $this->load_svg($network);
        }

        return $this->cache[$network];
    }

    private function load_svg(string $network): string
    {
        $map = [
            'facebook'      => 'facebook',
            'x'             => 'x',
            'threads'       => 'threads',
            'bluesky'       => 'bluesky',
            'whatsapp'      => 'whatsapp',
            'telegram'      => 'telegram',
            'line'          => 'line',
            'linkedin'      => 'linkedin',
            'pinterest'     => 'pinterest',
            'reddit'        => 'reddit',
            'tumblr'        => 'tumblr',
            'mastodon'      => 'mastodon',
            'vk'            => 'vk',
            'weibo'         => 'weibo',
            'odnoklassniki' => 'odnoklassniki',
            'xing'          => 'xing',
            'pocket'        => 'pocket',
            'flipboard'     => 'flipboard',
            'buffer'        => 'buffer',
            'mix'           => 'mix',
            'evernote'      => 'evernote',
            'diaspora'      => 'diaspora',
            'hacker-news'   => 'ycombinator',
            'email'         => 'email',
            'copy'          => 'copy',
            'native'        => 'native',
            'share-toggle'  => 'share',
            'instagram'     => 'instagram',
            'tiktok'        => 'tiktok',
            'youtube'       => 'youtube',
            'facebook-page' => 'facebook',
        ];

        if (!isset($map[$network])) {
            return $this->fallback();
        }

        $file = $this->icons_path . $map[$network] . '.svg';

        if (!is_readable($file)) {
            return $this->fallback();
        }

        $svg = trim((string) file_get_contents($file));

        if ($svg === '') {
            return $this->fallback();
        }

        $svg = preg_replace('/<\?xml[^>]*>/i', '', $svg);
        $svg = $this->normalize_svg_markup((string) $svg);

        $allowed = [
            'svg'  => [
                'xmlns'       => true,
                'viewBox'     => true,
                'viewbox'     => true,
                'fill'        => true,
                'class'       => true,
                'aria-hidden' => true,
                'focusable'   => true,
                'role'        => true,
            ],
            'path' => [
                'd'    => true,
                'fill' => true,
            ],
        ];

        if (function_exists('wp_kses')) {
            $svg = wp_kses($svg, $allowed);
        }

        return $svg ?: $this->fallback();
    }

    private function fallback(): string
    {
        return '<svg class="waki-icon__svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="currentColor"><path d="M12 3l5 5h-3v6h-4V8H7l5-5z"/><path d="M5 17h14v4H5z"/></svg>';
    }

    private function normalize_svg_markup(string $svg): string
    {
        if ($svg === '') {
            return $svg;
        }

        return (string) preg_replace_callback(
            '/<svg\b([^>]*)>/i',
            function (array $matches): string {
                $attributes = $matches[1];

                $attributes = $this->ensure_svg_attribute($attributes, 'class', 'waki-icon__svg', true);
                $attributes = $this->ensure_svg_attribute($attributes, 'fill', 'currentColor');
                $attributes = $this->ensure_svg_attribute($attributes, 'aria-hidden', 'true');
                $attributes = $this->ensure_svg_attribute($attributes, 'focusable', 'false');

                $attributes = trim($attributes);

                return '<svg' . ($attributes !== '' ? ' ' . $attributes : '') . '>';
            },
            $svg,
            1
        );
    }

    private function ensure_svg_attribute(string $attributes, string $name, string $value, bool $append = false): string
    {
        $pattern = '/\b' . preg_quote($name, '/') . '\s*=\s*("|\')([^"\']*)\1/i';

        if (preg_match($pattern, $attributes, $match)) {
            if ($append) {
                $existing = trim($match[2]);
                $classes  = $existing !== '' ? preg_split('/\s+/', $existing) : [];

                if (!is_array($classes)) {
                    $classes = [];
                }

                $classes = array_values(array_filter($classes, static function ($class): bool {
                    return $class !== '';
                }));

                if (!in_array($value, $classes, true)) {
                    $classes[]   = $value;
                    $replacement = $name . '=' . $match[1] . implode(' ', $classes) . $match[1];
                    $attributes  = preg_replace($pattern, $replacement, $attributes, 1) ?? $attributes;
                }
            }

            return $attributes;
        }

        $attributes = trim($attributes);

        if ($attributes !== '') {
            $attributes .= ' ';
        }

        return $attributes . sprintf('%s="%s"', $name, $value);
    }
}
