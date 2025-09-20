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
            'facebook' => 'facebook',
            'x'        => 'x',
            'whatsapp' => 'whatsapp',
            'telegram' => 'telegram',
            'linkedin' => 'linkedin',
            'reddit'   => 'reddit',
            'email'    => 'email',
            'copy'     => 'copy',
            'native'   => 'native',
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
        return '<svg class="waki-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="currentColor"><path d="M12 3l5 5h-3v6h-4V8H7l5-5z"/><path d="M5 17h14v4H5z"/></svg>';
    }
}
