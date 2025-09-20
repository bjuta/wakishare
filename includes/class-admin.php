<?php

namespace YourShare;

if (!defined('ABSPATH')) {
    exit;
}

class Admin
{
    /** @var Options */
    private $options;

    /** @var string */
    private $slug;

    /** @var string */
    private $text_domain;

    public function __construct(Options $options, string $slug, string $text_domain)
    {
        $this->options     = $options;
        $this->slug        = $slug;
        $this->text_domain = $text_domain;
    }

    public function register_hooks(): void
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'register_settings']);
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
            [$this, 'sanitize_settings']
        );
    }

    public function sanitize_settings($input): array
    {
        if (!is_array($input)) {
            $input = [];
        }

        return $this->options->sanitize($input);
    }

    public function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $opts = $this->options->all();
        ?>
        <div class="wrap your-share-settings">
            <h1><?php esc_html_e('Your Share Settings', $this->text_domain); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields($this->options->key()); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable UTM parameters', $this->text_domain); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->options->key()); ?>[enable_utm]" <?php checked($opts['enable_utm']); ?>>
                                <?php esc_html_e('Append utm_* parameters to shared URLs.', $this->text_domain); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('UTM Medium', $this->text_domain); ?></th>
                        <td>
                            <input type="text" class="regular-text" name="<?php echo esc_attr($this->options->key()); ?>[utm_medium]" value="<?php echo esc_attr($opts['utm_medium']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('UTM Campaign (default)', $this->text_domain); ?></th>
                        <td>
                            <input type="text" class="regular-text" name="<?php echo esc_attr($this->options->key()); ?>[utm_campaign]" value="<?php echo esc_attr($opts['utm_campaign']); ?>">
                            <p class="description"><?php esc_html_e('Shortcode can override via the utm_campaign attribute.', $this->text_domain); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('UTM Term (default)', $this->text_domain); ?></th>
                        <td>
                            <input type="text" class="regular-text" name="<?php echo esc_attr($this->options->key()); ?>[utm_term]" value="<?php echo esc_attr($opts['utm_term']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('UTM Content template', $this->text_domain); ?></th>
                        <td>
                            <input type="text" class="regular-text" name="<?php echo esc_attr($this->options->key()); ?>[utm_content]" value="<?php echo esc_attr($opts['utm_content']); ?>">
                            <p class="description"><?php esc_html_e('Supports tokens: {ID}, {slug}, {post_type}.', $this->text_domain); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Brand colours', $this->text_domain); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->options->key()); ?>[brand_colors]" <?php checked($opts['brand_colors']); ?>>
                                <?php esc_html_e('Use official network colours.', $this->text_domain); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Button style, size, and labels', $this->text_domain); ?></th>
                        <td>
                            <select name="<?php echo esc_attr($this->options->key()); ?>[style]">
                                <option value="solid" <?php selected($opts['style'], 'solid'); ?>><?php esc_html_e('Solid', $this->text_domain); ?></option>
                                <option value="outline" <?php selected($opts['style'], 'outline'); ?>><?php esc_html_e('Outline', $this->text_domain); ?></option>
                                <option value="ghost" <?php selected($opts['style'], 'ghost'); ?>><?php esc_html_e('Ghost', $this->text_domain); ?></option>
                            </select>
                            <select name="<?php echo esc_attr($this->options->key()); ?>[size]">
                                <option value="sm" <?php selected($opts['size'], 'sm'); ?>><?php esc_html_e('Small', $this->text_domain); ?></option>
                                <option value="md" <?php selected($opts['size'], 'md'); ?>><?php esc_html_e('Medium', $this->text_domain); ?></option>
                                <option value="lg" <?php selected($opts['size'], 'lg'); ?>><?php esc_html_e('Large', $this->text_domain); ?></option>
                            </select>
                            <select name="<?php echo esc_attr($this->options->key()); ?>[labels]">
                                <option value="auto" <?php selected($opts['labels'], 'auto'); ?>><?php esc_html_e('Auto', $this->text_domain); ?></option>
                                <option value="show" <?php selected($opts['labels'], 'show'); ?>><?php esc_html_e('Show', $this->text_domain); ?></option>
                                <option value="hide" <?php selected($opts['labels'], 'hide'); ?>><?php esc_html_e('Hide', $this->text_domain); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Default networks', $this->text_domain); ?></th>
                        <td>
                            <input type="text" data-your-share-networks class="regular-text" name="<?php echo esc_attr($this->options->key()); ?>[networks]" value="<?php echo esc_attr($opts['networks']); ?>">
                            <p class="description">
                                <?php esc_html_e('Comma separated list such as facebook,x,whatsapp,telegram,linkedin,reddit,email,copy', $this->text_domain); ?>
                                <span class="your-share-count" data-your-share-networks-count></span>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Floating bar', $this->text_domain); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->options->key()); ?>[floating_enabled]" <?php checked($opts['floating_enabled']); ?>>
                                <?php esc_html_e('Enable floating share bar', $this->text_domain); ?>
                            </label>
                            <div class="your-share-floating-controls">
                                <label>
                                    <?php esc_html_e('Position', $this->text_domain); ?>
                                    <select name="<?php echo esc_attr($this->options->key()); ?>[floating_position]">
                                        <option value="left" <?php selected($opts['floating_position'], 'left'); ?>><?php esc_html_e('Left', $this->text_domain); ?></option>
                                        <option value="right" <?php selected($opts['floating_position'], 'right'); ?>><?php esc_html_e('Right', $this->text_domain); ?></option>
                                    </select>
                                </label>
                                <label>
                                    <?php esc_html_e('Show on widths ≥', $this->text_domain); ?>
                                    <input type="number" min="0" step="1" name="<?php echo esc_attr($this->options->key()); ?>[floating_breakpoint]" value="<?php echo esc_attr($opts['floating_breakpoint']); ?>">
                                    <?php esc_html_e('px', $this->text_domain); ?>
                                </label>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Gap and radius', $this->text_domain); ?></th>
                        <td class="your-share-gap-radius">
                            <label>
                                <?php esc_html_e('Gap', $this->text_domain); ?>
                                <input type="number" name="<?php echo esc_attr($this->options->key()); ?>[gap]" value="<?php echo esc_attr($opts['gap']); ?>">
                                <?php esc_html_e('px', $this->text_domain); ?>
                            </label>
                            <label>
                                <?php esc_html_e('Radius', $this->text_domain); ?>
                                <input type="number" name="<?php echo esc_attr($this->options->key()); ?>[radius]" value="<?php echo esc_attr($opts['radius']); ?>">
                                <?php esc_html_e('px', $this->text_domain); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <p class="description">
                <strong><?php esc_html_e('Shortcode', $this->text_domain); ?>:</strong>
                <code>[your_share]</code>
                <?php esc_html_e('Attributes: networks, labels, style, size, align, brand, utm_campaign, url, title.', $this->text_domain); ?>
            </p>
        </div>
        <?php
    }
}
