<?php

namespace YourShare;

if (!defined('ABSPATH')) {
    exit;
}

class Asset_Loader
{
    /** @var string */
    private $plugin_file;

    /** @var string */
    private $plugin_url;

    /** @var string */
    private $version;

    /** @var string */
    private $text_domain;

    /** @var string */
    private $admin_slug;

    /** @var Options */
    private $options;

    public function __construct(string $plugin_file, string $plugin_url, string $version, string $text_domain, string $admin_slug, Options $options)
    {
        $this->plugin_file = $plugin_file;
        $this->plugin_url  = $plugin_url;
        $this->version     = $version;
        $this->text_domain = $text_domain;
        $this->admin_slug  = $admin_slug;
        $this->options     = $options;
    }

    public function register_hooks(): void
    {
        add_action('init', [$this, 'register_public_assets'], 9);
        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_public']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin']);
    }

    public function register_public_assets(): void
    {
        $style_handle  = 'your-share';
        $script_handle = 'your-share';

        wp_register_style(
            $style_handle,
            $this->plugin_url . 'assets/share.css',
            [],
            $this->version
        );

        wp_register_script(
            $script_handle,
            $this->plugin_url . 'assets/share.js',
            [],
            $this->version,
            true
        );

        $this->localize_public_scripts($script_handle);
    }

    public function maybe_enqueue_public(): void
    {
        // Assets are conditionally enqueued by blocks and shortcodes. The method
        // remains to preserve backward compatibility with previous hooks.
    }

    private function localize_public_scripts(string $script_handle): void
    {
        wp_localize_script(
            $script_handle,
            'yourShareMessages',
            [
                'copySuccess'     => __('Link copied', $this->text_domain),
                'shareUnsupported'=> __('Sharing not supported', $this->text_domain),
            ]
        );

        $options = $this->options->all();

        wp_localize_script(
            $script_handle,
            'yourShareCountsConfig',
            [
                'enabled'         => !empty($options['counts_enabled']),
                'restUrl'         => esc_url_raw(rest_url('your-share/v1/counts')),
                'nonce'           => wp_create_nonce('wp_rest'),
                'refreshInterval' => max(0, (int) ($options['counts_refresh_interval'] ?? 0)),
            ]
        );

        wp_localize_script(
            $script_handle,
            'yourShareAnalytics',
            [
                'store'   => !empty($options['analytics_events']),
                'console' => !empty($options['analytics_console']),
                'ga4'     => !empty($options['analytics_ga4']),
                'rest'    => [
                    'root'  => trailingslashit(rest_url('your-share/v1')),
                    'nonce' => wp_create_nonce('wp_rest'),
                ],
            ]
        );
    }

    public function enqueue_admin(string $hook): void
    {
        if ($hook !== 'settings_page_' . $this->admin_slug) {
            return;
        }

        wp_register_script(
            'your-share-chart',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
            [],
            '4.4.0',
            true
        );

        wp_enqueue_style(
            'your-share-admin',
            $this->plugin_url . 'assets/admin.css',
            [],
            $this->version
        );

        wp_enqueue_script(
            'your-share-admin',
            $this->plugin_url . 'assets/admin.js',
            ['your-share-chart'],
            $this->version,
            true
        );

        $options = $this->options->all();

        wp_localize_script(
            'your-share-admin',
            'yourShareAdmin',
            [
                'analytics' => [
                    'enabled' => !empty($options['analytics_events']),
                    'rest'    => [
                        'root'  => trailingslashit(rest_url('your-share/v1')),
                        'nonce' => wp_create_nonce('wp_rest'),
                    ],
                    'i18n'    => [
                        'disabled' => __('Event logging is disabled. Enable tracking to populate analytics.', $this->text_domain),
                        'error'    => __('Unable to load analytics data. Please try again.', $this->text_domain),
                        'updated'  => __('Last updated %s', $this->text_domain),
                        'range'    => __('Showing %s', $this->text_domain),
                        'share'    => __('Shares', $this->text_domain),
                        'reaction' => __('Reactions', $this->text_domain),
                    ],
                ],
            ]
        );
    }
}
