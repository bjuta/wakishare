<?php

namespace YourShare;

if (!defined('ABSPATH')) {
    exit;
}

class Admin
{
    /** @var Options */
    private $options;

    /** @var Networks */
    private $networks;

    /** @var Reactions */
    private $reactions;

    /** @var Analytics */
    private $analytics;

    /** @var string */
    private $slug;

    /** @var string */
    private $text_domain;

    /** @var array|null */
    private $cached_values = null;

    public function __construct(Options $options, Networks $networks, Reactions $reactions, Analytics $analytics, string $slug, string $text_domain)
    {
        $this->options     = $options;
        $this->networks    = $networks;
        $this->reactions   = $reactions;
        $this->analytics   = $analytics;
        $this->slug        = $slug;
        $this->text_domain = $text_domain;
    }

    public function register_hooks(): void
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_filter('redirect_post_location', [$this, 'preserve_tab'], 10, 2);
        add_action('load-settings_page_' . $this->slug, [$this, 'register_help_tabs']);
    }

    public function register_menu(): void
    {
        add_options_page(
            __('Your Share', $this->text_domain),
            __('Your Share', $this->text_domain),
            'manage_options',
            $this->slug,
            [$this, 'render_page']
        );
    }

    public function register_settings(): void
    {
        register_setting(
            $this->options->key(),
            $this->options->key(),
            [
                'sanitize_callback' => [$this, 'sanitize_settings'],
            ]
        );

        $this->register_share_settings();
        $this->register_follow_settings();
        $this->register_sticky_settings();
        $this->register_smart_settings();
        $this->register_reaction_settings();
        $this->register_counts_settings();
        $this->register_analytics_settings();
    }

    public function sanitize_settings($input): array
    {
        if (!is_array($input)) {
            $input = [];
        }

        if (!empty($_POST['your_share_reset_reactions'])) {
            $nonce = isset($_POST['your_share_reset_reactions_nonce']) ? wp_unslash($_POST['your_share_reset_reactions_nonce']) : '';

            if (wp_verify_nonce($nonce, 'your_share_reset_reactions')) {
                if (current_user_can('manage_options')) {
                    $this->reactions->reset_counts();
                    add_settings_error($this->options->key(), 'reactions_reset', __('Reaction counts have been reset.', $this->text_domain), 'updated');
                }
            } else {
                add_settings_error($this->options->key(), 'reactions_reset_failed', __('Security check failed. Reaction counts were not reset.', $this->text_domain), 'error');
            }
        }

        unset($input['current_tab']);
        $this->cached_values = null;

        return $this->options->sanitize($input);
    }

    public function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $tabs        = $this->tabs();
        $current_tab = $this->current_tab();

        ?>
        <div class="wrap your-share-settings" data-your-share-admin>
            <h1><?php esc_html_e('Your Share settings', $this->text_domain); ?></h1>
            <p class="description your-share-settings__intro">
                <?php esc_html_e('Tune the defaults that power your inline, floating, and smart share experiences.', $this->text_domain); ?>
            </p>
            <?php if ($this->analytics->consume_reset_notice()) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Analytics events have been cleared.', $this->text_domain); ?></p>
                </div>
            <?php endif; ?>
            <?php settings_errors($this->options->key()); ?>
            <h2 class="nav-tab-wrapper" role="tablist">
                <?php foreach ($tabs as $key => $label) :
                    $is_active = ($key === $current_tab);
                    $tab_url   = add_query_arg([
                        'page' => $this->slug,
                        'tab'  => $key,
                    ], admin_url('options-general.php'));
                    ?>
                    <a
                        href="<?php echo esc_url($tab_url); ?>"
                        class="nav-tab<?php echo $is_active ? ' nav-tab-active' : ''; ?>"
                        role="tab"
                        aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
                        aria-controls="<?php echo esc_attr($this->panel_id($key)); ?>"
                        data-your-share-tab="<?php echo esc_attr($key); ?>"
                    >
                        <?php echo esc_html($label); ?>
                    </a>
                <?php endforeach; ?>
            </h2>
            <form method="post" action="options.php" data-your-share-form>
                <?php settings_fields($this->options->key()); ?>
                <input type="hidden" name="<?php echo esc_attr($this->options->key()); ?>[current_tab]" value="<?php echo esc_attr($current_tab); ?>" data-your-share-current-tab>
                <div class="your-share-tab-panels">
                    <?php foreach ($tabs as $key => $label) :
                        $is_active = ($key === $current_tab);
                        ?>
                        <div
                            id="<?php echo esc_attr($this->panel_id($key)); ?>"
                            class="your-share-tab-panel<?php echo $is_active ? ' is-active' : ''; ?>"
                            role="tabpanel"
                            aria-hidden="<?php echo $is_active ? 'false' : 'true'; ?>"
                            data-your-share-panel="<?php echo esc_attr($key); ?>"
                        >
                            <?php do_settings_sections($this->page_id($key)); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php
                $referer_base  = add_query_arg('page', $this->slug, admin_url('options-general.php'));
                $referer_value = $referer_base;

                if (!empty($current_tab)) {
                    $referer_value = add_query_arg('tab', $current_tab, $referer_value);
                }

                $referer_base  = wp_make_link_relative($referer_base);
                $referer_value = wp_make_link_relative($referer_value);
                ?>
                <input
                    type="hidden"
                    name="_wp_http_referer"
                    value="<?php echo esc_attr($referer_value); ?>"
                    data-your-share-referer
                    data-base="<?php echo esc_attr($referer_base); ?>"
                >
                <?php submit_button(__('Save settings', $this->text_domain)); ?>
            </form>
        </div>
        <?php
    }

    public function register_help_tabs(): void
    {
        $screen = get_current_screen();

        if (!$screen) {
            return;
        }

        $screen->add_help_tab([
            'id'      => 'your-share-shortcodes',
            'title'   => __('Blocks & shortcodes', $this->text_domain),
            'content' => $this->help_tab_markup(),
        ]);

        $sidebar  = '<p><strong>' . esc_html__('Quick reference', $this->text_domain) . '</strong></p>';
        $sidebar .= '<p><code>[your_share]</code> &mdash; ' . esc_html__('Inline share buttons.', $this->text_domain) . '</p>';
        $sidebar .= '<p><code>[share_suite]</code> &mdash; ' . esc_html__('Composite share suite.', $this->text_domain) . '</p>';
        $sidebar .= '<p><code>[share_follow]</code> &mdash; ' . esc_html__('Profile follow buttons.', $this->text_domain) . '</p>';
        $sidebar .= '<p><code>[share_reactions]</code> &mdash; ' . esc_html__('Emoji reactions.', $this->text_domain) . '</p>';

        $screen->set_help_sidebar($sidebar);
    }

    private function help_tab_markup(): string
    {
        ob_start();
        ?>
        <p><?php esc_html_e('Use the blocks or shortcodes below to embed sharing interfaces anywhere on your site.', $this->text_domain); ?></p>
        <ul>
            <li><strong><?php esc_html_e('Share Suite block', $this->text_domain); ?></strong> &mdash; <?php esc_html_e('Combine share buttons, follow links, reactions, and the floating toggle.', $this->text_domain); ?></li>
            <li><strong><?php esc_html_e('Sticky Share Toggle block', $this->text_domain); ?></strong> &mdash; <?php esc_html_e('Outputs the floating share bar with per-page controls.', $this->text_domain); ?></li>
            <li><strong><?php esc_html_e('Follow Buttons block', $this->text_domain); ?></strong> &mdash; <?php esc_html_e('Uses the profile URLs saved on the Follow tab.', $this->text_domain); ?></li>
            <li><strong><?php esc_html_e('Reactions block', $this->text_domain); ?></strong> &mdash; <?php esc_html_e('Adds the emoji reaction bar inline or as a sticky widget.', $this->text_domain); ?></li>
            <li><strong><?php esc_html_e('Share Overlay block', $this->text_domain); ?></strong> &mdash; <?php esc_html_e('Wrap media that should display a share overlay.', $this->text_domain); ?></li>
        </ul>
        <p><?php esc_html_e('Shortcodes mirror the block functionality and can be added to classic editor content:', $this->text_domain); ?></p>
        <ul>
            <li><code>[your_share]</code> &mdash; <?php esc_html_e('Inline share buttons.', $this->text_domain); ?></li>
            <li><code>[share_suite]</code> &mdash; <?php esc_html_e('Composite suite with optional follow and reactions.', $this->text_domain); ?></li>
            <li><code>[share_follow]</code> &mdash; <?php esc_html_e('Follow buttons linked to your profiles.', $this->text_domain); ?></li>
            <li><code>[share_reactions]</code> &mdash; <?php esc_html_e('Emoji reactions bar.', $this->text_domain); ?></li>
        </ul>
        <?php
        return (string) ob_get_clean();
    }

    public function preserve_tab(string $location, int $status): string
    {
        if (empty($_POST[$this->options->key()]['current_tab'])) {
            return $location;
        }

        $tab = sanitize_key(wp_unslash($_POST[$this->options->key()]['current_tab']));
        $tabs = array_keys($this->tabs());

        if (!in_array($tab, $tabs, true)) {
            return $location;
        }

        return add_query_arg('tab', $tab, $location);
    }

    private function register_share_settings(): void
    {
        $page = $this->page_id('share');

        add_settings_section(
            'your_share_share_defaults',
            __('Button defaults', $this->text_domain),
            function (): void {
                echo '<p>' . esc_html__('Control the default inline share buttons used by shortcodes, hooks, and templates.', $this->text_domain) . '</p>';
            },
            $page
        );

        add_settings_field(
            'share_networks_default',
            __('Default networks', $this->text_domain),
            [$this, 'field_share_networks'],
            $page,
            'your_share_share_defaults'
        );

        add_settings_field(
            'share_design',
            __('Appearance', $this->text_domain),
            [$this, 'field_share_design'],
            $page,
            'your_share_share_defaults'
        );

        add_settings_field(
            'share_layout',
            __('Layout', $this->text_domain),
            [$this, 'field_share_layout'],
            $page,
            'your_share_share_defaults'
        );

        add_settings_field(
            'share_shortcode_preview',
            __('Shortcode preview', $this->text_domain),
            [$this, 'field_share_shortcode_preview'],
            $page,
            'your_share_share_defaults'
        );

        add_settings_section(
            'your_share_share_reference',
            __('Network reference', $this->text_domain),
            function (): void {
                echo '<p>' . esc_html__('A quick look at the bundled networks, their slugs, and brand colours.', $this->text_domain) . '</p>';
            },
            $page
        );

        add_settings_field(
            'share_network_matrix',
            __('Available networks', $this->text_domain),
            [$this, 'field_share_network_matrix'],
            $page,
            'your_share_share_reference'
        );
    }

    private function register_follow_settings(): void
    {
        $page = $this->page_id('follow');

        add_settings_section(
            'your_share_follow_profiles',
            __('Follow buttons', $this->text_domain),
            function (): void {
                echo '<p>' . esc_html__('Connect your social profiles and control how follow buttons appear.', $this->text_domain) . '</p>';
            },
            $page
        );

        add_settings_field(
            'follow_profiles',
            __('Profile links', $this->text_domain),
            [$this, 'field_follow_profiles'],
            $page,
            'your_share_follow_profiles'
        );

        add_settings_field(
            'follow_order',
            __('Display order', $this->text_domain),
            [$this, 'field_follow_order'],
            $page,
            'your_share_follow_profiles'
        );

        add_settings_field(
            'follow_shortcode_preview',
            __('Shortcode preview', $this->text_domain),
            [$this, 'field_follow_shortcode_preview'],
            $page,
            'your_share_follow_profiles'
        );
    }

    private function register_sticky_settings(): void
    {
        $page = $this->page_id('sticky');

        add_settings_section(
            'your_share_sticky_settings',
            __('Floating share bar', $this->text_domain),
            function (): void {
                echo '<p>' . esc_html__('Enable a persistent share bar that follows the reader along the page.', $this->text_domain) . '</p>';
            },
            $page
        );

        add_settings_field(
            'sticky_enabled',
            __('Activation', $this->text_domain),
            [$this, 'field_sticky_enabled'],
            $page,
            'your_share_sticky_settings'
        );

        add_settings_field(
            'sticky_position',
            __('Behaviour', $this->text_domain),
            [$this, 'field_sticky_behaviour'],
            $page,
            'your_share_sticky_settings'
        );

        add_settings_field(
            'sticky_spacing',
            __('Sizing', $this->text_domain),
            [$this, 'field_sticky_spacing'],
            $page,
            'your_share_sticky_settings'
        );

        add_settings_field(
            'sticky_selectors',
            __('Auto-insert selectors', $this->text_domain),
            [$this, 'field_sticky_selectors'],
            $page,
            'your_share_sticky_settings'
        );

        add_settings_field(
            'sticky_summary',
            __('Summary', $this->text_domain),
            [$this, 'field_sticky_summary'],
            $page,
            'your_share_sticky_settings'
        );
    }

    private function register_smart_settings(): void
    {
        $page = $this->page_id('smart');

        add_settings_section(
            'your_share_smart_settings',
            __('Smart Share', $this->text_domain),
            function (): void {
                echo '<p>' . esc_html__('Dynamically highlight share CTAs based on reading position and visitor context.', $this->text_domain) . '</p>';
            },
            $page
        );

        add_settings_field(
            'smart_share_enabled',
            __('Activation', $this->text_domain),
            [$this, 'field_smart_share_enabled'],
            $page,
            'your_share_smart_settings'
        );

        add_settings_field(
            'smart_share_selectors',
            __('Trigger selectors', $this->text_domain),
            [$this, 'field_smart_share_selectors'],
            $page,
            'your_share_smart_settings'
        );

        add_settings_field(
            'smart_share_matrix',
            __('Country networks', $this->text_domain),
            [$this, 'field_smart_share_matrix'],
            $page,
            'your_share_smart_settings'
        );

        add_settings_field(
            'geo_source',
            __('Geo source', $this->text_domain),
            [$this, 'field_geo_source'],
            $page,
            'your_share_smart_settings'
        );

        add_settings_field(
            'smart_share_summary',
            __('Summary', $this->text_domain),
            [$this, 'field_smart_share_summary'],
            $page,
            'your_share_smart_settings'
        );
    }

    private function register_reaction_settings(): void
    {
        $page = $this->page_id('reactions');

        add_settings_section(
            'your_share_reactions_settings',
            __('Reaction bar', $this->text_domain),
            function (): void {
                echo '<p>' . esc_html__('Offer quick emoji feedback alongside your share buttons.', $this->text_domain) . '</p>';
            },
            $page
        );

        add_settings_field(
            'reactions_display',
            __('Display options', $this->text_domain),
            [$this, 'field_reactions_display'],
            $page,
            'your_share_reactions_settings'
        );

        add_settings_field(
            'reactions_emojis',
            __('Available emojis', $this->text_domain),
            [$this, 'field_reactions_emojis'],
            $page,
            'your_share_reactions_settings'
        );

        add_settings_section(
            'your_share_reactions_maintenance',
            __('Maintenance', $this->text_domain),
            function (): void {
                echo '<p>' . esc_html__('Reset stored counts when you want to start fresh.', $this->text_domain) . '</p>';
            },
            $page
        );

        add_settings_field(
            'reactions_reset',
            __('Reset totals', $this->text_domain),
            [$this, 'field_reactions_reset'],
            $page,
            'your_share_reactions_maintenance'
        );
    }

    private function register_counts_settings(): void
    {
        $page = $this->page_id('counts');

        add_settings_section(
            'your_share_counts_general',
            __('Caching & status', $this->text_domain),
            function (): void {
                echo '<p>' . esc_html__('Control if share counts are collected and how long cached values are retained.', $this->text_domain) . '</p>';
            },
            $page
        );

        add_settings_field(
            'counts_general',
            __('Status', $this->text_domain),
            [$this, 'field_counts_general'],
            $page,
            'your_share_counts_general'
        );

        add_settings_section(
            'your_share_counts_display',
            __('Display', $this->text_domain),
            function (): void {
                echo '<p>' . esc_html__('Choose how share counts are surfaced alongside your buttons.', $this->text_domain) . '</p>';
            },
            $page
        );

        add_settings_field(
            'counts_display',
            __('Front end', $this->text_domain),
            [$this, 'field_counts_display'],
            $page,
            'your_share_counts_display'
        );

        add_settings_section(
            'your_share_counts_credentials',
            __('API credentials', $this->text_domain),
            function (): void {
                echo '<p>' . esc_html__('Provide access tokens for networks that require authentication to return share counts.', $this->text_domain) . '</p>';
            },
            $page
        );

        add_settings_field(
            'counts_credentials',
            __('Providers', $this->text_domain),
            [$this, 'field_counts_credentials'],
            $page,
            'your_share_counts_credentials'
        );
    }

    private function register_analytics_settings(): void
    {
        $page = $this->page_id('analytics');

        add_settings_section(
            'your_share_analytics_reports',
            __('Performance', $this->text_domain),
            function (): void {
                echo '<p>' . esc_html__('Monitor how visitors engage with share and reaction surfaces over time.', $this->text_domain) . '</p>';
            },
            $page
        );

        add_settings_field(
            'analytics_reports',
            __('Engagement overview', $this->text_domain),
            [$this, 'field_analytics_reports'],
            $page,
            'your_share_analytics_reports'
        );

        add_settings_section(
            'your_share_analytics_utm',
            __('UTM defaults', $this->text_domain),
            function (): void {
                echo '<p>' . esc_html__('Set the tagging rules that apply to every generated share URL.', $this->text_domain) . '</p>';
            },
            $page
        );

        add_settings_field(
            'utm_defaults',
            __('UTM parameters', $this->text_domain),
            [$this, 'field_utm_settings'],
            $page,
            'your_share_analytics_utm'
        );

        add_settings_field(
            'utm_preview',
            __('Preview', $this->text_domain),
            [$this, 'field_utm_preview'],
            $page,
            'your_share_analytics_utm'
        );

        add_settings_section(
            'your_share_analytics_toggles',
            __('Analytics toggles', $this->text_domain),
            function (): void {
                echo '<p>' . esc_html__('Choose how interactions are tracked in analytics platforms.', $this->text_domain) . '</p>';
            },
            $page
        );

        add_settings_field(
            'analytics_toggles',
            __('Event tracking', $this->text_domain),
            [$this, 'field_analytics_toggles'],
            $page,
            'your_share_analytics_toggles'
        );
    }

    public function field_share_networks(): void
    {
        $values    = $this->values();
        $active    = $values['share_networks_default'];
        $available = $this->networks->all();
        $order     = array_unique(array_merge($active, array_keys($available)));
        $input_id  = $this->field_id('share_networks_default');
        ?>
        <div class="your-share-network-picker" data-your-share-networks>
            <input type="hidden" id="<?php echo esc_attr($input_id); ?>" name="<?php echo esc_attr($this->name('share_networks_default')); ?>" value="<?php echo esc_attr(implode(',', $active)); ?>" data-your-share-network-input>
            <div class="your-share-network-selected">
                <p class="description"><?php esc_html_e('Drag to reorder or remove default networks. The first network is used most prominently.', $this->text_domain); ?></p>
                <ul class="your-share-network-list" data-your-share-network-list>
                    <?php foreach ($active as $slug) :
                        $label        = $available[$slug][0] ?? ucwords(str_replace('-', ' ', $slug));
                        $color        = $available[$slug][1] ?? '#111827';
                        $remove_label = sprintf(__('Remove %s', $this->text_domain), $label);
                        ?>
                        <li class="your-share-network-item" data-value="<?php echo esc_attr($slug); ?>" draggable="true">
                            <span class="your-share-network-handle" aria-hidden="true">⋮⋮</span>
                            <span class="your-share-network-swatch" style="--your-share-network-color: <?php echo esc_attr($color); ?>"></span>
                            <span class="your-share-network-label"><?php echo esc_html($label); ?></span>
                            <button type="button" class="your-share-network-remove" data-action="remove" aria-label="<?php echo esc_attr($remove_label); ?>">&times;</button>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="description"><span data-your-share-network-count><?php echo esc_html(count($active)); ?></span> <?php esc_html_e('active networks.', $this->text_domain); ?></p>
            </div>
            <div class="your-share-network-available">
                <p class="description"><?php esc_html_e('Add additional services:', $this->text_domain); ?></p>
                <div class="your-share-network-buttons">
                    <?php foreach ($order as $slug) :
                        $label        = $available[$slug][0] ?? ucwords(str_replace('-', ' ', $slug));
                        $color        = $available[$slug][1] ?? '#111827';
                        $remove_label = sprintf(__('Remove %s', $this->text_domain), $label);
                        $is_active    = in_array($slug, $active, true);
                        ?>
                        <button
                            type="button"
                            class="button button-small your-share-network-add<?php echo $is_active ? ' is-active' : ''; ?>"
                            data-value="<?php echo esc_attr($slug); ?>"
                            data-label="<?php echo esc_attr($label); ?>"
                            data-color="<?php echo esc_attr($color); ?>"
                            data-remove-label="<?php echo esc_attr($remove_label); ?>"
                            <?php disabled($is_active); ?>
                        >
                            <?php echo esc_html($label); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    }

    public function field_follow_profiles(): void
    {
        $values      = $this->values();
        $profiles    = $values['follow_profiles'];
        $networks    = $this->networks->follow();
        $placeholders = [
            'x'             => __('https://x.com/your-handle', $this->text_domain),
            'instagram'     => __('https://instagram.com/your-handle', $this->text_domain),
            'facebook-page' => __('https://www.facebook.com/your-page', $this->text_domain),
            'tiktok'        => __('https://www.tiktok.com/@your-handle', $this->text_domain),
            'youtube'       => __('https://www.youtube.com/@your-channel', $this->text_domain),
            'linkedin'      => __('https://www.linkedin.com/company/your-page', $this->text_domain),
        ];
        ?>
        <div class="your-share-field-grid">
            <?php foreach ($networks as $slug => $data) :
                [$label] = $data;
                $input_id = $this->field_id('follow_' . $slug);
                $value    = $profiles[$slug] ?? '';
                $placeholder = $placeholders[$slug] ?? '';
                ?>
                <label for="<?php echo esc_attr($input_id); ?>">
                    <span><?php echo esc_html($label); ?></span>
                    <input
                        type="url"
                        id="<?php echo esc_attr($input_id); ?>"
                        name="<?php echo esc_attr($this->name('follow_profiles') . '[' . $slug . ']'); ?>"
                        value="<?php echo esc_attr($value); ?>"
                        placeholder="<?php echo esc_attr($placeholder); ?>"
                        inputmode="url"
                    >
                </label>
            <?php endforeach; ?>
        </div>
        <p class="description"><?php esc_html_e('Provide full profile URLs. Leave blank to hide a network.', $this->text_domain); ?></p>
        <?php
    }

    public function field_follow_order(): void
    {
        $values    = $this->values();
        $order     = $values['follow_networks'];
        $available = $this->networks->follow();
        $input_id  = $this->field_id('follow_networks');
        ?>
        <div class="your-share-network-picker" data-your-share-networks>
            <input
                type="hidden"
                id="<?php echo esc_attr($input_id); ?>"
                name="<?php echo esc_attr($this->name('follow_networks')); ?>"
                value="<?php echo esc_attr(implode(',', $order)); ?>"
                data-your-share-network-input
                data-your-share-follow-networks
                data-your-share-follow-prop="networks"
            >
            <div class="your-share-network-selected">
                <p class="description"><?php esc_html_e('Drag to control the display order. Only networks with profile links will render.', $this->text_domain); ?></p>
                <ul class="your-share-network-list" data-your-share-network-list>
                    <?php foreach ($order as $slug) :
                        if (!isset($available[$slug])) {
                            continue;
                        }
                        [$label, $color] = $available[$slug];
                        ?>
                        <li class="your-share-network-item" data-value="<?php echo esc_attr($slug); ?>" draggable="true">
                            <span class="your-share-network-handle" aria-hidden="true">⋮⋮</span>
                            <span class="your-share-network-swatch" style="--your-share-network-color: <?php echo esc_attr($color); ?>"></span>
                            <span class="your-share-network-label"><?php echo esc_html($label); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php
    }

    public function field_follow_shortcode_preview(): void
    {
        $values   = $this->values();
        $networks = implode(',', $values['follow_networks']);
        $style    = $values['share_style'];
        $size     = $values['share_size'];
        $align    = $values['share_align'];
        $brand    = $values['share_brand_colors'] ? '1' : '0';
        ?>
        <div
            class="your-share-shortcode-preview"
            data-your-share-follow-shortcode
            data-default-networks="<?php echo esc_attr($networks); ?>"
            data-default-style="<?php echo esc_attr($style); ?>"
            data-default-size="<?php echo esc_attr($size); ?>"
            data-default-align="<?php echo esc_attr($align); ?>"
            data-default-brand="<?php echo esc_attr($brand); ?>"
            data-default-labels="show"
        >
            <code data-your-share-follow-output></code>
            <p class="description"><?php esc_html_e('Use the [share_follow] shortcode or block to place follow buttons. Attributes include networks, style, size, align, brand, and labels.', $this->text_domain); ?></p>
        </div>
        <?php
    }

    public function field_share_design(): void
    {
        $values = $this->values();
        ?>
        <div class="your-share-field-grid">
            <label for="<?php echo esc_attr($this->field_id('share_style')); ?>">
                <span><?php esc_html_e('Style', $this->text_domain); ?></span>
                <select id="<?php echo esc_attr($this->field_id('share_style')); ?>" name="<?php echo esc_attr($this->name('share_style')); ?>" data-your-share-shortcode-prop="style" data-your-share-follow-prop="style">
                    <option value="solid" <?php selected($values['share_style'], 'solid'); ?>><?php esc_html_e('Solid', $this->text_domain); ?></option>
                    <option value="outline" <?php selected($values['share_style'], 'outline'); ?>><?php esc_html_e('Outline', $this->text_domain); ?></option>
                    <option value="ghost" <?php selected($values['share_style'], 'ghost'); ?>><?php esc_html_e('Ghost', $this->text_domain); ?></option>
                </select>
            </label>
            <label for="<?php echo esc_attr($this->field_id('share_size')); ?>">
                <span><?php esc_html_e('Size', $this->text_domain); ?></span>
                <select id="<?php echo esc_attr($this->field_id('share_size')); ?>" name="<?php echo esc_attr($this->name('share_size')); ?>" data-your-share-shortcode-prop="size" data-your-share-follow-prop="size">
                    <option value="sm" <?php selected($values['share_size'], 'sm'); ?>><?php esc_html_e('Small', $this->text_domain); ?></option>
                    <option value="md" <?php selected($values['share_size'], 'md'); ?>><?php esc_html_e('Medium', $this->text_domain); ?></option>
                    <option value="lg" <?php selected($values['share_size'], 'lg'); ?>><?php esc_html_e('Large', $this->text_domain); ?></option>
                </select>
            </label>
            <label for="<?php echo esc_attr($this->field_id('share_labels')); ?>">
                <span><?php esc_html_e('Label display', $this->text_domain); ?></span>
                <select id="<?php echo esc_attr($this->field_id('share_labels')); ?>" name="<?php echo esc_attr($this->name('share_labels')); ?>" data-your-share-shortcode-prop="labels" data-your-share-follow-prop="labels">
                    <option value="auto" <?php selected($values['share_labels'], 'auto'); ?>><?php esc_html_e('Auto', $this->text_domain); ?></option>
                    <option value="show" <?php selected($values['share_labels'], 'show'); ?>><?php esc_html_e('Show', $this->text_domain); ?></option>
                    <option value="hide" <?php selected($values['share_labels'], 'hide'); ?>><?php esc_html_e('Hide', $this->text_domain); ?></option>
                </select>
            </label>
            <label>
                <input type="checkbox" name="<?php echo esc_attr($this->name('share_brand_colors')); ?>" value="1" <?php checked($values['share_brand_colors'], 1); ?> data-your-share-shortcode-prop="brand" data-your-share-follow-prop="brand">
                <?php esc_html_e('Use brand colours', $this->text_domain); ?>
            </label>
        </div>
        <?php
    }

    public function field_share_layout(): void
    {
        $values = $this->values();
        ?>
        <div class="your-share-field-grid">
            <label for="<?php echo esc_attr($this->field_id('share_align')); ?>">
                <span><?php esc_html_e('Alignment', $this->text_domain); ?></span>
                <select id="<?php echo esc_attr($this->field_id('share_align')); ?>" name="<?php echo esc_attr($this->name('share_align')); ?>" data-your-share-follow-prop="align">
                    <option value="left" <?php selected($values['share_align'], 'left'); ?>><?php esc_html_e('Left', $this->text_domain); ?></option>
                    <option value="center" <?php selected($values['share_align'], 'center'); ?>><?php esc_html_e('Center', $this->text_domain); ?></option>
                    <option value="right" <?php selected($values['share_align'], 'right'); ?>><?php esc_html_e('Right', $this->text_domain); ?></option>
                    <option value="space-between" <?php selected($values['share_align'], 'space-between'); ?>><?php esc_html_e('Justify', $this->text_domain); ?></option>
                </select>
            </label>
            <label for="<?php echo esc_attr($this->field_id('share_gap')); ?>">
                <span><?php esc_html_e('Gap', $this->text_domain); ?></span>
                <div class="your-share-input-suffix">
                    <input type="number" min="0" id="<?php echo esc_attr($this->field_id('share_gap')); ?>" name="<?php echo esc_attr($this->name('share_gap')); ?>" value="<?php echo esc_attr($values['share_gap']); ?>">
                    <span>px</span>
                </div>
            </label>
            <label for="<?php echo esc_attr($this->field_id('share_radius')); ?>">
                <span><?php esc_html_e('Corner radius', $this->text_domain); ?></span>
                <div class="your-share-input-suffix">
                    <input type="number" min="0" id="<?php echo esc_attr($this->field_id('share_radius')); ?>" name="<?php echo esc_attr($this->name('share_radius')); ?>" value="<?php echo esc_attr($values['share_radius']); ?>">
                    <span>px</span>
                </div>
            </label>
        </div>
        <?php
    }

    public function field_share_shortcode_preview(): void
    {
        $values   = $this->values();
        $networks = implode(',', $values['share_networks_default']);
        $brand    = $values['share_brand_colors'] ? '1' : '0';
        $shortcode = sprintf('[your_share networks="%s" style="%s" size="%s" labels="%s" brand="%s"]', $networks, $values['share_style'], $values['share_size'], $values['share_labels'], $brand);
        ?>
        <div class="your-share-shortcode-preview">
            <code data-your-share-shortcode><?php echo esc_html($shortcode); ?></code>
            <p class="description"><?php esc_html_e('Use this shortcode anywhere to pull in the defaults above. Override attributes per placement as needed.', $this->text_domain); ?></p>
        </div>
        <?php
    }

    public function field_share_network_matrix(): void
    {
        $networks = $this->networks->all();
        ?>
        <table class="widefat striped your-share-network-matrix">
            <thead>
                <tr>
                    <th scope="col"><?php esc_html_e('Network', $this->text_domain); ?></th>
                    <th scope="col"><?php esc_html_e('Slug', $this->text_domain); ?></th>
                    <th scope="col"><?php esc_html_e('Brand colour', $this->text_domain); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($networks as $slug => $data) :
                    [$label, $color] = $data;
                    ?>
                    <tr>
                        <td><?php echo esc_html($label); ?></td>
                        <td><code><?php echo esc_html($slug); ?></code></td>
                        <td>
                            <span class="your-share-color-chip" style="--your-share-network-color: <?php echo esc_attr($color); ?>"></span>
                            <code><?php echo esc_html($color); ?></code>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    public function field_sticky_enabled(): void
    {
        $values = $this->values();
        ?>
        <label class="your-share-toggle">
            <input type="checkbox" name="<?php echo esc_attr($this->name('sticky_enabled')); ?>" value="1" <?php checked($values['sticky_enabled'], 1); ?> data-your-share-sticky-prop="enabled">
            <?php esc_html_e('Enable floating share bar on posts', $this->text_domain); ?>
        </label>
        <p class="description"><?php esc_html_e('The floating bar inherits the default networks unless overridden via template tags.', $this->text_domain); ?></p>
        <?php
    }

    public function field_sticky_behaviour(): void
    {
        $values = $this->values();
        ?>
        <div class="your-share-field-grid">
            <label for="<?php echo esc_attr($this->field_id('sticky_position')); ?>">
                <span><?php esc_html_e('Position', $this->text_domain); ?></span>
                <select id="<?php echo esc_attr($this->field_id('sticky_position')); ?>" name="<?php echo esc_attr($this->name('sticky_position')); ?>" data-your-share-sticky-prop="position">
                    <option value="left" <?php selected($values['sticky_position'], 'left'); ?>><?php esc_html_e('Left', $this->text_domain); ?></option>
                    <option value="right" <?php selected($values['sticky_position'], 'right'); ?>><?php esc_html_e('Right', $this->text_domain); ?></option>
                </select>
            </label>
            <label for="<?php echo esc_attr($this->field_id('sticky_breakpoint')); ?>">
                <span><?php esc_html_e('Show on widths ≥', $this->text_domain); ?></span>
                <div class="your-share-input-suffix">
                    <input type="number" min="0" id="<?php echo esc_attr($this->field_id('sticky_breakpoint')); ?>" name="<?php echo esc_attr($this->name('sticky_breakpoint')); ?>" value="<?php echo esc_attr($values['sticky_breakpoint']); ?>" data-your-share-sticky-prop="breakpoint">
                    <span>px</span>
                </div>
            </label>
        </div>
        <?php
    }

    public function field_sticky_spacing(): void
    {
        $values = $this->values();
        ?>
        <div class="your-share-field-grid">
            <label for="<?php echo esc_attr($this->field_id('sticky_gap')); ?>">
                <span><?php esc_html_e('Button gap', $this->text_domain); ?></span>
                <div class="your-share-input-suffix">
                    <input type="number" min="0" id="<?php echo esc_attr($this->field_id('sticky_gap')); ?>" name="<?php echo esc_attr($this->name('sticky_gap')); ?>" value="<?php echo esc_attr($values['sticky_gap']); ?>">
                    <span>px</span>
                </div>
            </label>
            <label for="<?php echo esc_attr($this->field_id('sticky_radius')); ?>">
                <span><?php esc_html_e('Corner radius', $this->text_domain); ?></span>
                <div class="your-share-input-suffix">
                    <input type="number" min="0" id="<?php echo esc_attr($this->field_id('sticky_radius')); ?>" name="<?php echo esc_attr($this->name('sticky_radius')); ?>" value="<?php echo esc_attr($values['sticky_radius']); ?>">
                    <span>px</span>
                </div>
            </label>
        </div>
        <?php
    }

    public function field_sticky_selectors(): void
    {
        $values = $this->values();
        ?>
        <textarea rows="3" class="large-text" name="<?php echo esc_attr($this->name('sticky_selectors')); ?>" id="<?php echo esc_attr($this->field_id('sticky_selectors')); ?>" placeholder=".entry-content"><?php echo esc_textarea($values['sticky_selectors']); ?></textarea>
        <p class="description"><?php esc_html_e('Comma-separated CSS selectors where the floating bar should auto-inject. Leave blank to manage output manually.', $this->text_domain); ?></p>
        <?php
    }

    public function field_sticky_summary(): void
    {
        $values     = $this->values();
        $status     = $values['sticky_enabled'] ? __('enabled', $this->text_domain) : __('disabled', $this->text_domain);
        $position   = $values['sticky_position'] === 'right' ? __('right', $this->text_domain) : __('left', $this->text_domain);
        $breakpoint = intval($values['sticky_breakpoint']);
        ?>
        <div class="your-share-summary" data-your-share-sticky-summary>
            <p>
                <?php
                printf(
                    esc_html__('Floating bar is %1$s, showing on screens ≥ %2$dpx on the %3$s edge.', $this->text_domain),
                    esc_html($status),
                    esc_html($breakpoint),
                    esc_html($position)
                );
                ?>
            </p>
        </div>
        <?php
    }

    public function field_smart_share_enabled(): void
    {
        $values = $this->values();
        ?>
        <label class="your-share-toggle">
            <input type="checkbox" name="<?php echo esc_attr($this->name('smart_share_enabled')); ?>" value="1" <?php checked($values['smart_share_enabled'], 1); ?> data-your-share-smart-prop="enabled">
            <?php esc_html_e('Enable Smart Share prompts', $this->text_domain); ?>
        </label>
        <p class="description"><?php esc_html_e('Promote contextual share prompts as visitors scroll through long-form content.', $this->text_domain); ?></p>
        <?php
    }

    public function field_smart_share_selectors(): void
    {
        $values = $this->values();
        ?>
        <textarea rows="4" class="large-text" name="<?php echo esc_attr($this->name('smart_share_selectors')); ?>" id="<?php echo esc_attr($this->field_id('smart_share_selectors')); ?>" placeholder=".entry-content h2, .entry-content h3"><?php echo esc_textarea($values['smart_share_selectors']); ?></textarea>
        <p class="description"><?php esc_html_e('Enter selectors that should receive inline Smart Share prompts. One selector per line or comma.', $this->text_domain); ?></p>
        <?php
    }

    public function field_smart_share_matrix(): void
    {
        $values = $this->values();
        $matrix = $values['smart_share_matrix'];
        $rows   = [];

        foreach ($matrix as $country => $networks) {
            $rows[] = $country . ': ' . implode(', ', $networks);
        }

        $value = implode("\n", $rows);
        ?>
        <textarea rows="6" class="large-text code" name="<?php echo esc_attr($this->name('smart_share_matrix')); ?>" id="<?php echo esc_attr($this->field_id('smart_share_matrix')); ?>" placeholder="US: facebook, x, linkedin"><?php echo esc_textarea($value); ?></textarea>
        <p class="description"><?php esc_html_e('Map two-letter country codes to preferred networks. Format each line as CC: network-one, network-two.', $this->text_domain); ?></p>
        <?php
    }

    public function field_geo_source(): void
    {
        $values = $this->values();
        ?>
        <select id="<?php echo esc_attr($this->field_id('geo_source')); ?>" name="<?php echo esc_attr($this->name('geo_source')); ?>" data-your-share-smart-prop="geo">
            <option value="auto" <?php selected($values['geo_source'], 'auto'); ?>><?php esc_html_e('Auto detect (recommended)', $this->text_domain); ?></option>
            <option value="ip" <?php selected($values['geo_source'], 'ip'); ?>><?php esc_html_e('IP address lookup', $this->text_domain); ?></option>
            <option value="manual" <?php selected($values['geo_source'], 'manual'); ?>><?php esc_html_e('Manual override via filters', $this->text_domain); ?></option>
        </select>
        <p class="description"><?php esc_html_e('Determines how Smart Share chooses the best networks for a visitor based on location.', $this->text_domain); ?></p>
        <p class="description"><?php esc_html_e('When Cloudflare headers are unavailable, auto detection falls back to Accept-Language only—no IP lookups required.', $this->text_domain); ?></p>
        <?php
    }

    public function field_smart_share_summary(): void
    {
        $values   = $this->values();
        $status   = $values['smart_share_enabled'] ? __('enabled', $this->text_domain) : __('disabled', $this->text_domain);
        $geo      = $values['geo_source'];
        $geo_text = [
            'auto'   => __('auto-detected by device locale', $this->text_domain),
            'ip'     => __('resolved via visitor IP', $this->text_domain),
            'manual' => __('controlled manually', $this->text_domain),
        ];
        $geo_label = $geo_text[$geo] ?? $geo_text['auto'];
        ?>
        <div class="your-share-summary" data-your-share-smart-summary>
            <p>
                <?php
                printf(
                    esc_html__('Smart Share is %1$s with audiences %2$s.', $this->text_domain),
                    esc_html($status),
                    esc_html($geo_label)
                );
                ?>
            </p>
        </div>
        <?php
    }

    public function field_counts_general(): void
    {
        $values   = $this->values();
        $field_id = $this->field_id('counts_refresh_interval');
        ?>
        <div class="your-share-field-stack">
            <label class="your-share-toggle">
                <input type="checkbox" name="<?php echo esc_attr($this->name('counts_enabled')); ?>" value="1" <?php checked($values['counts_enabled'], 1); ?>>
                <?php esc_html_e('Enable share counts collection', $this->text_domain); ?>
            </label>
            <label for="<?php echo esc_attr($field_id); ?>">
                <span><?php esc_html_e('Refresh interval', $this->text_domain); ?></span>
                <div class="your-share-input-suffix">
                    <input type="number" min="0" id="<?php echo esc_attr($field_id); ?>" name="<?php echo esc_attr($this->name('counts_refresh_interval')); ?>" value="<?php echo esc_attr($values['counts_refresh_interval']); ?>">
                    <span><?php esc_html_e('minutes', $this->text_domain); ?></span>
                </div>
                <p class="description"><?php esc_html_e('Cached counts older than this threshold will be refreshed. Use 0 to bypass caching.', $this->text_domain); ?></p>
            </label>
        </div>
        <?php
    }

    public function field_counts_display(): void
    {
        $values = $this->values();
        ?>
        <div class="your-share-field-stack">
            <label class="your-share-toggle">
                <input type="checkbox" name="<?php echo esc_attr($this->name('counts_show_badges')); ?>" value="1" <?php checked($values['counts_show_badges'], 1); ?>>
                <?php esc_html_e('Show per-network badges beside each button', $this->text_domain); ?>
            </label>
            <label class="your-share-toggle">
                <input type="checkbox" name="<?php echo esc_attr($this->name('counts_show_total')); ?>" value="1" <?php checked($values['counts_show_total'], 1); ?>>
                <?php esc_html_e('Display the combined total above the button list', $this->text_domain); ?>
            </label>
            <p class="description"><?php esc_html_e('Counts reuse the last successful response if a provider is unavailable.', $this->text_domain); ?></p>
        </div>
        <?php
    }

    public function field_counts_credentials(): void
    {
        $values = $this->values();
        ?>
        <div class="your-share-field-grid">
            <label for="<?php echo esc_attr($this->field_id('counts_facebook_app_id')); ?>">
                <span><?php esc_html_e('Facebook App ID', $this->text_domain); ?></span>
                <input type="text" id="<?php echo esc_attr($this->field_id('counts_facebook_app_id')); ?>" name="<?php echo esc_attr($this->name('counts_facebook_app_id')); ?>" value="<?php echo esc_attr($values['counts_facebook_app_id']); ?>" autocomplete="off">
            </label>
            <label for="<?php echo esc_attr($this->field_id('counts_facebook_app_secret')); ?>">
                <span><?php esc_html_e('Facebook App Secret', $this->text_domain); ?></span>
                <input type="text" id="<?php echo esc_attr($this->field_id('counts_facebook_app_secret')); ?>" name="<?php echo esc_attr($this->name('counts_facebook_app_secret')); ?>" value="<?php echo esc_attr($values['counts_facebook_app_secret']); ?>" autocomplete="off">
            </label>
            <label for="<?php echo esc_attr($this->field_id('counts_reddit_app_id')); ?>">
                <span><?php esc_html_e('Reddit Client ID', $this->text_domain); ?></span>
                <input type="text" id="<?php echo esc_attr($this->field_id('counts_reddit_app_id')); ?>" name="<?php echo esc_attr($this->name('counts_reddit_app_id')); ?>" value="<?php echo esc_attr($values['counts_reddit_app_id']); ?>" autocomplete="off">
            </label>
            <label for="<?php echo esc_attr($this->field_id('counts_reddit_app_secret')); ?>">
                <span><?php esc_html_e('Reddit Client Secret', $this->text_domain); ?></span>
                <input type="text" id="<?php echo esc_attr($this->field_id('counts_reddit_app_secret')); ?>" name="<?php echo esc_attr($this->name('counts_reddit_app_secret')); ?>" value="<?php echo esc_attr($values['counts_reddit_app_secret']); ?>" autocomplete="off">
            </label>
        </div>
        <p class="description"><?php esc_html_e('Leave any field blank to rely on unauthenticated requests when supported by the provider.', $this->text_domain); ?></p>
        <?php
    }

    public function field_utm_settings(): void
    {
        $values = $this->values();
        ?>
        <div class="your-share-utm-settings">
            <label class="your-share-toggle">
                <input type="checkbox" name="<?php echo esc_attr($this->name('enable_utm')); ?>" value="1" <?php checked($values['enable_utm'], 1); ?> data-your-share-utm-prop="enabled">
                <?php esc_html_e('Append UTM parameters to share URLs', $this->text_domain); ?>
            </label>
            <div class="your-share-field-grid">
                <label for="<?php echo esc_attr($this->field_id('utm_medium')); ?>">
                    <span><?php esc_html_e('utm_medium', $this->text_domain); ?></span>
                    <input type="text" id="<?php echo esc_attr($this->field_id('utm_medium')); ?>" name="<?php echo esc_attr($this->name('utm_medium')); ?>" value="<?php echo esc_attr($values['utm_medium']); ?>" data-your-share-utm-prop="medium">
                </label>
                <label for="<?php echo esc_attr($this->field_id('utm_campaign')); ?>">
                    <span><?php esc_html_e('utm_campaign (default)', $this->text_domain); ?></span>
                    <input type="text" id="<?php echo esc_attr($this->field_id('utm_campaign')); ?>" name="<?php echo esc_attr($this->name('utm_campaign')); ?>" value="<?php echo esc_attr($values['utm_campaign']); ?>" data-your-share-utm-prop="campaign">
                </label>
                <label for="<?php echo esc_attr($this->field_id('utm_term')); ?>">
                    <span><?php esc_html_e('utm_term (default)', $this->text_domain); ?></span>
                    <input type="text" id="<?php echo esc_attr($this->field_id('utm_term')); ?>" name="<?php echo esc_attr($this->name('utm_term')); ?>" value="<?php echo esc_attr($values['utm_term']); ?>" data-your-share-utm-prop="term">
                </label>
                <label for="<?php echo esc_attr($this->field_id('utm_content')); ?>" class="your-share-field-wide">
                    <span><?php esc_html_e('utm_content template', $this->text_domain); ?></span>
                    <input type="text" id="<?php echo esc_attr($this->field_id('utm_content')); ?>" name="<?php echo esc_attr($this->name('utm_content')); ?>" value="<?php echo esc_attr($values['utm_content']); ?>" data-your-share-utm-prop="content">
                </label>
            </div>
            <p class="description"><?php esc_html_e('Supports template tags like {ID}, {slug}, and {post_type}. Shortcodes can override the campaign per placement.', $this->text_domain); ?></p>
        </div>
        <?php
    }

    public function field_utm_preview(): void
    {
        $values    = $this->values();
        $medium    = $values['utm_medium'];
        $campaign  = $values['utm_campaign'] ?: __('campaign-name', $this->text_domain);
        $term      = $values['utm_term'] ?: __('optional-term', $this->text_domain);
        $content   = $values['utm_content'];
        $base_url  = home_url('/your-post/');
        $preview   = add_query_arg([
            'utm_source'   => 'x',
            'utm_medium'   => $medium,
            'utm_campaign' => $campaign,
            'utm_term'     => $term,
            'utm_content'  => $content,
        ], $base_url);
        ?>
        <div class="your-share-shortcode-preview" data-your-share-utm-preview data-base="<?php echo esc_attr($base_url); ?>" data-network="x">
            <code data-your-share-utm-output><?php echo esc_html($preview); ?></code>
            <p class="description"><?php esc_html_e('Preview of a shared URL for the X network. Term/content are omitted if left empty.', $this->text_domain); ?></p>
        </div>
        <?php
    }

    public function field_analytics_reports(): void
    {
        $values   = $this->values();
        $enabled  = !empty($values['analytics_events']);
        $export_label = __('Export CSV', $this->text_domain);
        $reset_label  = __('Reset analytics data', $this->text_domain);
        $reset_warning = __('Reset all analytics events? This action cannot be undone.', $this->text_domain);
        ?>
        <div class="your-share-analytics" data-your-share-analytics data-enabled="<?php echo esc_attr($enabled ? '1' : '0'); ?>">
            <div class="your-share-analytics__chart">
                <div class="your-share-analytics__toolbar">
                    <div class="your-share-analytics__ranges" data-your-share-analytics-ranges>
                        <button type="button" class="button button-secondary is-active" data-range="7"><?php esc_html_e('7 days', $this->text_domain); ?></button>
                        <button type="button" class="button button-secondary" data-range="30"><?php esc_html_e('30 days', $this->text_domain); ?></button>
                        <button type="button" class="button button-secondary" data-range="90"><?php esc_html_e('90 days', $this->text_domain); ?></button>
                    </div>
                    <div class="your-share-analytics__summary" data-your-share-analytics-summary>
                        <div>
                            <span class="your-share-analytics__summary-label"><?php esc_html_e('Shares', $this->text_domain); ?></span>
                            <span data-your-share-analytics-total="share">0</span>
                        </div>
                        <div>
                            <span class="your-share-analytics__summary-label"><?php esc_html_e('Reactions', $this->text_domain); ?></span>
                            <span data-your-share-analytics-total="reaction">0</span>
                        </div>
                        <div class="your-share-analytics__updated" data-your-share-analytics-updated></div>
                    </div>
                </div>
                <div class="your-share-analytics__canvas">
                    <canvas width="640" height="320" data-your-share-analytics-chart aria-label="<?php esc_attr_e('Analytics event trend', $this->text_domain); ?>" role="img"></canvas>
                    <p class="description" data-your-share-analytics-empty hidden><?php esc_html_e('No events recorded for the selected range yet.', $this->text_domain); ?></p>
                </div>
            </div>
            <div class="your-share-analytics__lists">
                <div class="your-share-analytics__card">
                    <h4><?php esc_html_e('Top posts (30 days)', $this->text_domain); ?></h4>
                    <ol data-your-share-analytics-top="posts"></ol>
                    <p class="description" data-your-share-analytics-top-empty="posts" hidden><?php esc_html_e('No posts have recorded events in this window.', $this->text_domain); ?></p>
                </div>
                <div class="your-share-analytics__card">
                    <h4><?php esc_html_e('Top networks (30 days)', $this->text_domain); ?></h4>
                    <ol data-your-share-analytics-top="networks"></ol>
                    <p class="description" data-your-share-analytics-top-empty="networks" hidden><?php esc_html_e('Events have not been attributed to share networks yet.', $this->text_domain); ?></p>
                </div>
                <div class="your-share-analytics__card">
                    <h4><?php esc_html_e('Top devices (30 days)', $this->text_domain); ?></h4>
                    <ol data-your-share-analytics-top="devices"></ol>
                    <p class="description" data-your-share-analytics-top-empty="devices" hidden><?php esc_html_e('Device insights will appear after visitors interact with share buttons.', $this->text_domain); ?></p>
                </div>
            </div>
            <div class="your-share-analytics__tools" data-your-share-analytics-tools>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="your_share_export_events">
                    <?php wp_nonce_field('your_share_export_events', 'your_share_export_events_nonce'); ?>
                    <button type="submit" class="button"><?php echo esc_html($export_label); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="your_share_reset_events">
                    <?php wp_nonce_field('your_share_reset_events', 'your_share_reset_events_nonce'); ?>
                    <button type="submit" class="button button-secondary" data-your-share-analytics-reset onclick="return confirm('<?php echo esc_js($reset_warning); ?>');"><?php echo esc_html($reset_label); ?></button>
                </form>
                <?php if (!$enabled) : ?>
                    <p class="description your-share-analytics__notice"><?php esc_html_e('Enable event storage below to populate analytics and unlock exports.', $this->text_domain); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public function field_analytics_toggles(): void
    {
        $values = $this->values();
        ?>
        <div class="your-share-field-stack">
            <label class="your-share-toggle">
                <input type="checkbox" name="<?php echo esc_attr($this->name('analytics_events')); ?>" value="1" <?php checked($values['analytics_events'], 1); ?>>
                <?php esc_html_e('Store share and reaction events in the analytics log', $this->text_domain); ?>
            </label>
            <label class="your-share-toggle">
                <input type="checkbox" name="<?php echo esc_attr($this->name('analytics_ga4')); ?>" value="1" <?php checked($values['analytics_ga4'], 1); ?>>
                <?php esc_html_e('Forward interactions to Google Analytics 4 when configured', $this->text_domain); ?>
            </label>
            <label class="your-share-toggle">
                <input type="checkbox" name="<?php echo esc_attr($this->name('analytics_console')); ?>" value="1" <?php checked($values['analytics_console'], 1); ?>>
                <?php esc_html_e('Log analytics payloads to the browser console for debugging', $this->text_domain); ?>
            </label>
        </div>
        <?php
    }

    public function field_reactions_display(): void
    {
        $values = $this->values();
        ?>
        <div class="your-share-field-stack">
            <label class="your-share-toggle">
                <input type="checkbox" name="<?php echo esc_attr($this->name('reactions_inline_enabled')); ?>" value="1" <?php checked($values['reactions_inline_enabled'] ?? 0, 1); ?>>
                <?php esc_html_e('Show reactions below inline share bars', $this->text_domain); ?>
            </label>
            <label class="your-share-toggle">
                <input type="checkbox" name="<?php echo esc_attr($this->name('reactions_sticky_enabled')); ?>" value="1" <?php checked($values['reactions_sticky_enabled'] ?? 0, 1); ?>>
                <?php esc_html_e('Enable a floating reaction bar on singular content', $this->text_domain); ?>
            </label>
        </div>
        <p class="description"><?php esc_html_e('Inline reactions appear directly beneath rendered share shortcodes. The floating bar is printed in the footer when viewing singular posts or pages.', $this->text_domain); ?></p>
        <?php
    }

    public function field_reactions_emojis(): void
    {
        $values       = $this->values();
        $enabled_map  = $values['reactions_enabled'] ?? [];
        $emojis       = $this->reactions->emojis();
        $search_id    = 'your-share-reaction-search-' . wp_generate_uuid4();
        $enabled_slugs = [];

        if (is_array($enabled_map)) {
            foreach ($enabled_map as $slug => $flag) {
                if (!empty($flag)) {
                    $enabled_slugs[] = sanitize_key((string) $slug);
                }
            }
        }

        $enabled_lookup = array_fill_keys($enabled_slugs, true);

        $keys = array_keys($emojis);
        usort(
            $keys,
            static function ($a, $b) use ($emojis, $enabled_lookup) {
                $a_active = !empty($enabled_lookup[$a]);
                $b_active = !empty($enabled_lookup[$b]);

                if ($a_active !== $b_active) {
                    return $a_active ? -1 : 1;
                }

                $a_label = isset($emojis[$a]['label']) ? $emojis[$a]['label'] : $a;
                $b_label = isset($emojis[$b]['label']) ? $emojis[$b]['label'] : $b;

                return strcasecmp((string) $a_label, (string) $b_label);
            }
        );
        ?>
        <div class="your-share-reaction-picker" data-your-share-reaction-picker>
            <label for="<?php echo esc_attr($search_id); ?>" class="your-share-reaction-picker__label"><?php esc_html_e('Search reactions', $this->text_domain); ?></label>
            <input
                type="search"
                id="<?php echo esc_attr($search_id); ?>"
                class="your-share-reaction-search"
                placeholder="<?php esc_attr_e('Filter by emoji or name…', $this->text_domain); ?>"
                data-your-share-reaction-search
            >
        </div>
        <div class="your-share-field-grid your-share-reaction-grid" data-your-share-reaction-list>
            <?php foreach ($keys as $slug) :
                if (!isset($emojis[$slug])) {
                    continue;
                }
                $emoji      = $emojis[$slug];
                $label      = $emoji['label'] ?? $slug;
                $symbol     = $emoji['emoji'] ?? '';
                $is_enabled = !empty($enabled_lookup[$slug]);
                $filter_key = function_exists('mb_strtolower') ? mb_strtolower((string) $label) : strtolower((string) $label);
                ?>
                <label
                    class="your-share-reaction-option<?php echo $is_enabled ? ' is-active' : ''; ?>"
                    data-reaction-slug="<?php echo esc_attr($slug); ?>"
                    data-reaction-label="<?php echo esc_attr(wp_strip_all_tags(wp_specialchars_decode($filter_key, ENT_QUOTES))); ?>"
                >
                    <input type="checkbox" name="<?php echo esc_attr($this->name('reactions_enabled') . '[' . $slug . ']'); ?>" value="1" <?php checked($is_enabled, true); ?>>
                    <span class="your-share-reaction-symbol" aria-hidden="true"><?php echo esc_html($symbol); ?></span>
                    <span class="your-share-reaction-text"><?php echo esc_html($label); ?></span>
                </label>
            <?php endforeach; ?>
        </div>
        <p class="description"><?php esc_html_e('Toggle the emoji set available to readers. At least one reaction must remain enabled.', $this->text_domain); ?></p>
        <?php
    }

    public function field_reactions_reset(): void
    {
        ?>
        <p>
            <button type="submit" class="button button-secondary" name="your_share_reset_reactions" value="1" onclick="return confirm('<?php echo esc_js(__('Reset all reaction counts? This action cannot be undone.', $this->text_domain)); ?>');">
                <?php esc_html_e('Reset reaction counts', $this->text_domain); ?>
            </button>
        </p>
        <?php wp_nonce_field('your_share_reset_reactions', 'your_share_reset_reactions_nonce'); ?>
        <p class="description"><?php esc_html_e('Clears totals stored in the reactions table for every post.', $this->text_domain); ?></p>
        <?php
    }

    private function tabs(): array
    {
        return [
            'share'     => __('Share', $this->text_domain),
            'follow'    => __('Follow', $this->text_domain),
            'sticky'    => __('Sticky Bar', $this->text_domain),
            'smart'     => __('Smart Share', $this->text_domain),
            'reactions' => __('Reactions', $this->text_domain),
            'counts'    => __('Share Counts', $this->text_domain),
            'analytics' => __('Analytics & UTM', $this->text_domain),
        ];
    }

    private function current_tab(): string
    {
        $tabs    = array_keys($this->tabs());
        $default = reset($tabs) ?: 'share';
        $tab     = $default;

        if (isset($_GET['tab'])) {
            $tab = sanitize_key(wp_unslash($_GET['tab']));
        } elseif (!empty($_POST[$this->options->key()]['current_tab'])) {
            $tab = sanitize_key(wp_unslash($_POST[$this->options->key()]['current_tab']));
        }

        if (!in_array($tab, $tabs, true)) {
            $tab = $default;
        }

        return $tab;
    }

    private function page_id(string $tab): string
    {
        return $this->slug . '_' . $tab;
    }

    private function panel_id(string $tab): string
    {
        return 'your-share-panel-' . $tab;
    }

    private function values(): array
    {
        if (null === $this->cached_values) {
            $this->cached_values = $this->options->all();
        }

        return $this->cached_values;
    }

    private function name(string $field): string
    {
        return $this->options->key() . '[' . $field . ']';
    }

    private function field_id(string $field): string
    {
        return 'your-share-' . str_replace('_', '-', $field);
    }
}
