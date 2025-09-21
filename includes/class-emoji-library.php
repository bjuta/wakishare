<?php

namespace YourShare;

if (!defined('ABSPATH')) {
    exit;
}

class Emoji_Library
{
    /** @var array<string, array{emoji: string, label: string}>|null */
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

            if ($emoji === '') {
                continue;
            }

            if ($label === '') {
                $label = ucfirst(str_replace('-', ' ', $slug));
            }

            $normalized[$slug] = [
                'emoji' => $emoji,
                'label' => $label,
            ];
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
