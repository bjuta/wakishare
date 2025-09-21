<?php

namespace YourShare;

if (!defined('ABSPATH')) {
    exit;
}

class Plugin
{
    public const VERSION     = '1.0.0';
    public const SLUG        = 'your-share';
    public const OPTION_KEY  = 'your_share_options';
    public const TEXT_DOMAIN = 'your-share';

    /** @var string */
    private $plugin_file;

    /** @var Container */
    private $container;

    public function __construct(string $plugin_file)
    {
        $this->plugin_file = $plugin_file;
        $this->container   = new Container();

        $this->register_services();
    }

    public function boot(): void
    {
        add_action('plugins_loaded', [$this, 'load_textdomain']);

        $this->container->get(Asset_Loader::class)->register_hooks();
        $this->container->get(Counts::class)->register_hooks();
        $this->container->get(Admin::class)->register_hooks();
        $this->container->get(Reactions::class)->register_hooks();
        $this->container->get(Rest::class)->register_hooks();
        $this->container->get(Analytics::class)->register_hooks();
        $this->container->get(Shortcode::class)->register_hooks();

        do_action('your_share_plugin_booted', $this);
    }

    public function load_textdomain(): void
    {
        load_plugin_textdomain(self::TEXT_DOMAIN, false, dirname(plugin_basename($this->plugin_file)) . '/languages');
    }

    public function container(): Container
    {
        return $this->container;
    }

    private function register_services(): void
    {
        $plugin_file = $this->plugin_file;
        $plugin_dir  = plugin_dir_path($plugin_file);
        $plugin_url  = plugin_dir_url($plugin_file);

        $this->container->set(Options::class, function (): Options {
            return new Options(self::OPTION_KEY);
        });

        $this->container->set(Networks::class, function (): Networks {
            return new Networks(self::TEXT_DOMAIN);
        });

        $this->container->set(Icons::class, function () use ($plugin_dir): Icons {
            return new Icons($plugin_dir . 'assets/icons');
        });

        $this->container->set(UTM::class, function (Container $c): UTM {
            return new UTM($c->get(Options::class));
        });

        $this->container->set(Reactions::class, function (Container $c): Reactions {
            return new Reactions($c->get(Options::class), self::TEXT_DOMAIN);
        });

        $this->container->set(Rest::class, function (Container $c): Rest {
            return new Rest($c->get(Reactions::class), self::TEXT_DOMAIN);
        });

        $this->container->set(Analytics::class, function (Container $c): Analytics {
            return new Analytics($c->get(Options::class), self::TEXT_DOMAIN, self::SLUG);
        });

        $this->container->set(Render::class, function (Container $c): Render {
            return new Render(
                $c->get(Options::class),
                $c->get(Networks::class),
                $c->get(UTM::class),
                $c->get(Icons::class),
                $c->get(Reactions::class),
                self::TEXT_DOMAIN,
                $c->get(Counts::class)
            );
        });

        $this->container->set(Asset_Loader::class, function (Container $c) use ($plugin_file, $plugin_url): Asset_Loader {
            return new Asset_Loader($plugin_file, $plugin_url, self::VERSION, self::TEXT_DOMAIN, self::SLUG, $c->get(Options::class));
        });

        $this->container->set(Admin::class, function (Container $c): Admin {
            return new Admin(
                $c->get(Options::class),
                $c->get(Networks::class),
                $c->get(Reactions::class),
                $c->get(Analytics::class),
                self::SLUG,
                self::TEXT_DOMAIN
            );
        });

        $this->container->set(Shortcode::class, function (Container $c): Shortcode {
            return new Shortcode($c->get(Options::class), $c->get(Render::class));
        });

        $this->container->set(Counts::class, function (Container $c): Counts {
            return new Counts($c->get(Options::class), $c->get(Networks::class));
        });
    }
}
