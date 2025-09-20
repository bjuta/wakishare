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

    public function __construct(string $plugin_file, string $plugin_url, string $version, string $text_domain, string $admin_slug)
    {
        $this->plugin_file = $plugin_file;
        $this->plugin_url  = $plugin_url;
        $this->version     = $version;
        $this->text_domain = $text_domain;
        $this->admin_slug  = $admin_slug;
    }

    public function register_hooks(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_public']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin']);
    }

    public function enqueue_public(): void
    {
        $style_handle  = 'your-share';
        $script_handle = 'your-share';

        wp_enqueue_style(
            $style_handle,
            $this->plugin_url . 'assets/share.css',
            [],
            $this->version
        );

        wp_enqueue_script(
            $script_handle,
            $this->plugin_url . 'assets/share.js',
            [],
            $this->version,
            true
        );

        wp_localize_script(
            $script_handle,
            'yourShareMessages',
            [
                'copySuccess'     => __('Link copied', $this->text_domain),
                'shareUnsupported'=> __('Sharing not supported', $this->text_domain),
            ]
        );
    }

    public function enqueue_admin(string $hook): void
    {
        if ($hook !== 'settings_page_' . $this->admin_slug) {
            return;
        }

        wp_enqueue_style(
            'your-share-admin',
            $this->plugin_url . 'assets/admin.css',
            [],
            $this->version
        );

        wp_enqueue_script(
            'your-share-admin',
            $this->plugin_url . 'assets/admin.js',
            [],
            $this->version,
            true
        );
    }
}
