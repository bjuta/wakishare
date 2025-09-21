<?php

namespace YourShare;

if (!defined('ABSPATH')) {
    exit;
}

class Emoji_Library
{
    /**
     * @var array<string, array{
     *     emoji: string,
     *     label: string,
     *     image?: string,
     *     image_width?: int,
     *     image_height?: int
     * }>|null
     */
    private static $cache = null;

    /**
     * Retrieve the complete emoji dataset bundled with the plugin.
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $file = __DIR__ . '/data/emoji.php';

        if (!is_readable($file)) {
            self::$cache = [];

            return self::$cache;
        }

        $data = include $file;

        if (!is_array($data)) {
            $data = [];
        }

        $normalized = [];

        foreach ($data as $slug => $info) {
            if (!is_array($info)) {
                continue;
            }

            $slug = sanitize_key((string) $slug);

            if ($slug === '') {
                continue;
            }

            $emoji = isset($info['emoji']) ? (string) $info['emoji'] : '';
            $label = isset($info['label']) ? (string) $info['label'] : '';
            $image = isset($info['image']) ? (string) $info['image'] : '';
            $width = isset($info['image_width']) ? (int) $info['image_width'] : (isset($info['width']) ? (int) $info['width'] : 0);
            $height = isset($info['image_height']) ? (int) $info['image_height'] : (isset($info['height']) ? (int) $info['height'] : 0);

            if ($width < 0) {
                $width = 0;
            }

            if ($height < 0) {
                $height = 0;
            }

            if ($emoji === '' && $image === '') {
                continue;
            }

            if ($label === '') {
                $label = ucfirst(str_replace('-', ' ', $slug));
            }

            $normalized[$slug] = [
                'emoji' => $emoji,
                'label' => $label,
            ];

            if ($image !== '') {
                if (strpos($image, 'data:') === 0 || preg_match('#^(https?:)?//#', $image) === 1) {
                    $normalized[$slug]['image'] = $image;
                } else {
                    $normalized[$slug]['image'] = ltrim($image, '/');
                }
            }

            if ($width > 0) {
                $normalized[$slug]['image_width'] = $width;
            }

            if ($height > 0) {
                $normalized[$slug]['image_height'] = $height;
            }
        }

        self::$cache = $normalized;

        return self::$cache;
    }

    /**
     * Default emoji slugs to enable for new installations.
     */
    public static function defaults(): array
    {
        return ['like', 'love', 'celebrate', 'insightful', 'support'];
    }

    /**
     * Ensure a list of reaction slugs only contains valid emojis.
     *
     * @param array<int|string, mixed> $slugs Raw slug list.
     *
     * @return string[]
     */
    public static function sanitize_slugs($slugs): array
    {
        if (!is_array($slugs)) {
            $slugs = [];
        }

        $allowed = self::all();
        $output  = [];

        foreach ($slugs as $key => $value) {
            $slug = is_int($key) ? (string) $value : (string) $key;
            $slug = sanitize_key($slug);

            if ($slug === '' || !isset($allowed[$slug])) {
                continue;
            }

            $enabled = is_int($value) || is_bool($value) ? !empty($value) : true;

            if (!$enabled) {
                continue;
            }

            if (!in_array($slug, $output, true)) {
                $output[] = $slug;
            }
        }

        return $output;
    }
}
