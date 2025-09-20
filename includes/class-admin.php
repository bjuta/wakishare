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

    /** @var string */
    private $slug;

    /** @var string */
    private $text_domain;

    /** @var array|null */
    private $cached_values = null;

    public function __construct(Options $options, Networks $networks, string $slug, string $text_domain)
    {
        $this->options     = $options;
        $this->networks    = $networks;
        $this->slug        = $slug;
        $this->text_domain = $text_domain;
    }

    public function register_hooks(): void
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_filter('redirect_post_location', [$this, 'preserve_tab'], 10, 2);
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
        $this->register_sticky_settings();
        $this->register_smart_settings();
        $this->register_analytics_settings();
    }

    public function sanitize_settings($input): array
    {
        if (!is_array($input)) {
            $input = [];
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
                <?php submit_button(__('Save settings', $this->text_domain)); ?>
            </form>
        </div>
        <?php
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

    private function register_analytics_settings(): void
    {
        $page = $this->page_id('analytics');

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

    public function field_share_design(): void
    {
        $values = $this->values();
        ?>
        <div class="your-share-field-grid">
            <label for="<?php echo esc_attr($this->field_id('share_style')); ?>">
                <span><?php esc_html_e('Style', $this->text_domain); ?></span>
                <select id="<?php echo esc_attr($this->field_id('share_style')); ?>" name="<?php echo esc_attr($this->name('share_style')); ?>" data-your-share-shortcode-prop="style">
                    <option value="solid" <?php selected($values['share_style'], 'solid'); ?>><?php esc_html_e('Solid', $this->text_domain); ?></option>
                    <option value="outline" <?php selected($values['share_style'], 'outline'); ?>><?php esc_html_e('Outline', $this->text_domain); ?></option>
                    <option value="ghost" <?php selected($values['share_style'], 'ghost'); ?>><?php esc_html_e('Ghost', $this->text_domain); ?></option>
                </select>
            </label>
            <label for="<?php echo esc_attr($this->field_id('share_size')); ?>">
                <span><?php esc_html_e('Size', $this->text_domain); ?></span>
                <select id="<?php echo esc_attr($this->field_id('share_size')); ?>" name="<?php echo esc_attr($this->name('share_size')); ?>" data-your-share-shortcode-prop="size">
                    <option value="sm" <?php selected($values['share_size'], 'sm'); ?>><?php esc_html_e('Small', $this->text_domain); ?></option>
                    <option value="md" <?php selected($values['share_size'], 'md'); ?>><?php esc_html_e('Medium', $this->text_domain); ?></option>
                    <option value="lg" <?php selected($values['share_size'], 'lg'); ?>><?php esc_html_e('Large', $this->text_domain); ?></option>
                </select>
            </label>
            <label for="<?php echo esc_attr($this->field_id('share_labels')); ?>">
                <span><?php esc_html_e('Label display', $this->text_domain); ?></span>
                <select id="<?php echo esc_attr($this->field_id('share_labels')); ?>" name="<?php echo esc_attr($this->name('share_labels')); ?>" data-your-share-shortcode-prop="labels">
                    <option value="auto" <?php selected($values['share_labels'], 'auto'); ?>><?php esc_html_e('Auto', $this->text_domain); ?></option>
                    <option value="show" <?php selected($values['share_labels'], 'show'); ?>><?php esc_html_e('Show', $this->text_domain); ?></option>
                    <option value="hide" <?php selected($values['share_labels'], 'hide'); ?>><?php esc_html_e('Hide', $this->text_domain); ?></option>
                </select>
            </label>
            <label>
                <input type="checkbox" name="<?php echo esc_attr($this->name('share_brand_colors')); ?>" value="1" <?php checked($values['share_brand_colors'], 1); ?> data-your-share-shortcode-prop="brand">
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
                <select id="<?php echo esc_attr($this->field_id('share_align')); ?>" name="<?php echo esc_attr($this->name('share_align')); ?>">
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

    public function field_analytics_toggles(): void
    {
        $values = $this->values();
        ?>
        <div class="your-share-field-stack">
            <label class="your-share-toggle">
                <input type="checkbox" name="<?php echo esc_attr($this->name('analytics_events')); ?>" value="1" <?php checked($values['analytics_events'], 1); ?>>
                <?php esc_html_e('Dispatch share events to the browser dataLayer', $this->text_domain); ?>
            </label>
            <label class="your-share-toggle">
                <input type="checkbox" name="<?php echo esc_attr($this->name('analytics_ga4')); ?>" value="1" <?php checked($values['analytics_ga4'], 1); ?>>
                <?php esc_html_e('Emit Google Analytics 4 compatible events', $this->text_domain); ?>
            </label>
            <label class="your-share-toggle">
                <input type="checkbox" name="<?php echo esc_attr($this->name('analytics_console')); ?>" value="1" <?php checked($values['analytics_console'], 1); ?>>
                <?php esc_html_e('Log debug output to the browser console', $this->text_domain); ?>
            </label>
        </div>
        <?php
    }

    private function tabs(): array
    {
        return [
            'share'     => __('Share', $this->text_domain),
            'sticky'    => __('Sticky Bar', $this->text_domain),
            'smart'     => __('Smart Share', $this->text_domain),
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
