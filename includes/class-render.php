<?php

namespace YourShare;

if (!defined('ABSPATH')) {
    exit;
}

class Render
{
    /** @var Options */
    private $options;

    /** @var Networks */
    private $networks;

    /** @var UTM */
    private $utm;

    /** @var Icons */
    private $icons;

    /** @var Reactions */
    private $reactions;

    /** @var string */
    private $text_domain;

    /** @var Counts|null */
    private $counts;

    /** @var bool */
    private $media_config_localized = false;

    /** @var bool */
    private $metadata_output = false;

    public function __construct(
        Options $options,
        Networks $networks,
        UTM $utm,
        Icons $icons,
        Reactions $reactions,
        string $text_domain,
        ?Counts $counts = null
    ) {
        $this->options     = $options;
        $this->networks    = $networks;
        $this->utm         = $utm;
        $this->icons       = $icons;
        $this->reactions   = $reactions;
        $this->text_domain = $text_domain;
        add_action('wp_enqueue_scripts', [$this, 'register_media_overlays'], 20);
        add_action('wp_head', [$this, 'output_share_metadata']);
        $this->counts      = $counts;
    }

    public function render(array $context, array $atts): string
    {
        wp_enqueue_style('your-share');
        wp_enqueue_script('your-share');

        $opts       = $this->options->all();
        $map        = $this->networks->all();
        $share_ctx  = $this->current_share_context($atts);
        $allowed    = array_keys($map);
        $placement  = $context['placement'] ?? 'inline';

        if (isset($atts['share_radius'])) {
            $opts['share_radius'] = max(0, (int) $atts['share_radius']);
        }

        if (isset($atts['counts_enabled'])) {
            $opts['counts_enabled'] = !empty($atts['counts_enabled']) ? 1 : 0;
        }

        if (isset($atts['counts_show_badges'])) {
            $opts['counts_show_badges'] = !empty($atts['counts_show_badges']) ? 1 : 0;
        }

        if (isset($atts['counts_show_total'])) {
            $opts['counts_show_total'] = !empty($atts['counts_show_total']) ? 1 : 0;
        }

        if (isset($atts['counts_badge_radius'])) {
            $opts['counts_badge_radius'] = max(0, (int) $atts['counts_badge_radius']);
        }

        if (empty($opts['counts_show_badges']) && empty($opts['counts_show_total'])) {
            $opts['counts_enabled'] = 0;
        }

        $geo       = $this->resolve_geo_country($opts, $context, $atts);
        $matrix    = $opts['smart_share_matrix'] ?? [];
        $networks  = $this->prepare_networks($atts['networks'] ?? '', $allowed);
        if (empty($networks)) {
            $networks = $this->networks_for_country($geo['country'], $matrix, $allowed);
        }

        if (empty($networks)) {
            $defaults = $opts['share_networks_default'];
            if (!is_array($defaults) || empty($defaults)) {
                $defaults = $this->options->defaults()['share_networks_default'];
            }
            $networks = $this->prepare_networks($defaults, $allowed);
        }

        if ($placement !== 'overlay' && !in_array('native', $networks, true)) {
            $networks[] = 'native';
        }

        $visible_limit = count($networks);

        $classes = [
            'waki-share',
            'waki-size-' . sanitize_html_class($atts['size'] ?? 'md'),
            'waki-style-' . sanitize_html_class($atts['style'] ?? 'solid'),
            'waki-labels-' . sanitize_html_class($atts['labels'] ?? 'auto'),
            !empty($atts['brand']) && (string) $atts['brand'] === '1' ? 'is-brand' : 'is-mono',
            'waki-share-placement-' . sanitize_html_class($placement),
        ];

        $defaults = $this->options->defaults();

        if ($placement === 'floating') {
            $gap    = absint($opts['sticky_gap'] ?? $defaults['sticky_gap']);
            $radius = absint($opts['sticky_radius'] ?? $defaults['sticky_radius']);
        } elseif ($placement === 'overlay') {
            $gap    = absint($opts['share_gap'] ?? $defaults['share_gap']);
            $radius = absint($opts['share_radius'] ?? $defaults['share_radius']);
        } else {
            $gap    = absint($opts['share_gap'] ?? $defaults['share_gap']);
            $radius = absint($opts['share_radius'] ?? $defaults['share_radius']);
        }

        if ($gap <= 0) {
            $gap = absint($placement === 'floating' ? $defaults['sticky_gap'] : $defaults['share_gap']);
        }

        if ($radius <= 0) {
            $radius = absint($placement === 'floating' ? $defaults['sticky_radius'] : $defaults['share_radius']);
        }

        if ($placement === 'overlay') {
            $gap = max(4, $gap);
        }

        $style_inline = sprintf('--waki-gap:%dpx;--waki-radius:%dpx;', $gap, $radius);
        $badge_radius = absint($opts['counts_badge_radius'] ?? $defaults['counts_badge_radius'] ?? 0);
        $style_inline .= sprintf('--waki-count-radius:%dpx;', $badge_radius);

        if ($placement === 'floating') {
            $classes[]    = 'waki-share-floating';
            $classes[]    = 'pos-' . sanitize_html_class($context['position'] ?? 'left');
            $style_inline .= sprintf('--waki-breakpoint:%dpx;', intval($context['breakpoint'] ?? 1024));
        } elseif ($placement === 'overlay') {
            $classes[] = 'waki-share-overlay-share';
        } else {
            $classes[] = 'waki-share-inline';
            $classes[] = 'align-' . sanitize_html_class($context['align'] ?? 'left');
        }

        $title = $share_ctx['title'];
        $base  = $share_ctx['url'];

        $counts_state = $this->prepare_counts_state($opts, $networks, $share_ctx);
        $post_id      = $share_ctx['post'] instanceof \WP_Post ? (int) $share_ctx['post']->ID : 0;

        if ($counts_state['enabled']) {
            $classes[] = 'waki-has-counts';
        }

        $max_visible     = $visible_limit;
        $inline_reactions_markup = '';

        if ($placement === 'inline') {
            $inline_reactions_markup = $this->reactions->render_inline($post_id);
        }

        $max_visible     = 5;
        $visible_buttons = [];
        $hidden_buttons  = [];
        $button_index    = 0;

        foreach ($networks as $network) {
            if (!isset($map[$network])) {
                continue;
            }

            [$label] = $map[$network];

            if ($network === 'copy' || $network === 'native') {
                $href = '#';
            } else {
                $utm_url = $this->utm->append($base, $network, $share_ctx['post'], $atts);
                $href    = $this->build_share_url($network, $utm_url, $title);
            }

            $count_value = $counts_state['networks'][$network]['total'] ?? 0;
            $attr        = [
                'class'      => 'waki-btn',
                'data-net'   => $network,
                'aria-label' => sprintf(__('Share on %s', $this->text_domain), $label),
                'role'       => 'link',
            ];

            if ($network !== 'copy' && $network !== 'native') {
                $attr['href']   = $href;
                $attr['target'] = '_blank';
                $attr['rel']    = 'noopener noreferrer';
            } else {
                $attr['href'] = '#';
            }

            $attr = apply_filters('your_share_button_attributes', $attr, $network, $context, $atts, $share_ctx);

            ob_start();
            ?>
            <a<?php foreach ($attr as $key => $value) { if ($value === '' || $value === null) { continue; } echo ' ' . esc_attr($key) . '="' . esc_attr((string) $value) . '"'; } ?>>
                <span class="waki-icon" aria-hidden="true">
                    <?php echo $this->icons->svg($network); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </span>
                <span class="waki-label"><?php echo esc_html($label); ?></span>
                <?php if ($counts_state['enabled'] && !empty($opts['counts_show_badges'])) : ?>
                    <span class="waki-count" data-your-share-count="<?php echo esc_attr($network); ?>" data-value="<?php echo esc_attr((string) $count_value); ?>"><?php echo esc_html($this->format_count($count_value)); ?></span>
                <?php endif; ?>
            </a>
            <?php
            $button_markup = trim((string) ob_get_clean());

            if ($button_markup === '') {
                continue;
            }

            if ($button_index < $max_visible) {
                $visible_buttons[] = $button_markup;
            } else {
                $hidden_buttons[] = $button_markup;
            }

            $button_index++;
        }

        $more_id = '';

        if (!empty($hidden_buttons)) {
            $more_id = uniqid('waki-share-more-');
        }

        ob_start();
        ?>
        <?php $data_attrs = $this->format_attributes([
            'data-your-share-country'        => $geo['country'],
            'data-your-share-country-source' => $geo['source'],
            'data-your-share-placement'      => $placement,
            'data-your-share-counts'         => $counts_state['enabled'] ? '1' : '',
            'data-your-share-post'           => $counts_state['post'],
            'data-your-share-post-id'        => $post_id > 0 ? (string) $post_id : '',
            'data-your-share-networks'       => $counts_state['network_list'],
            'data-your-share-count-url'      => $counts_state['url'],
            'data-your-share-count-ttl'      => $counts_state['ttl'],
            'data-your-share-title'          => $title,
            'data-your-share-url'            => $base,
            'data-your-share-snippet'        => $share_ctx['excerpt'],
            'data-your-share-image'          => $share_ctx['image'],
        ]); ?>
        <div class="<?php echo esc_attr(implode(' ', $classes)); ?>" style="<?php echo esc_attr($style_inline); ?>"<?php echo $data_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <div class="waki-share-row">
                <?php if ($counts_state['enabled'] && !empty($opts['counts_show_total'])) : ?>
                    <div class="waki-share-total" data-your-share-total>
                        <span class="waki-total-value" data-your-share-total-value data-value="<?php echo esc_attr((string) $counts_state['total']); ?>"><?php echo esc_html($this->format_count($counts_state['total'])); ?></span>
                        <span class="waki-total-label"><?php esc_html_e('Shares', $this->text_domain); ?></span>
                    </div>
                <?php endif; ?>
                <div class="waki-share-buttons" data-your-share-buttons>
                    <?php foreach ($visible_buttons as $button_markup) :
                        echo $button_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    endforeach; ?>
                    <?php if (!empty($hidden_buttons)) : ?>
                        <button
                            type="button"
                            class="waki-btn waki-btn--toggle"
                            data-net="more"
                            data-share-toggle="more"
                            aria-expanded="false"
                            aria-label="<?php esc_attr_e('More share options', $this->text_domain); ?>"
                            <?php if ($more_id !== '') : ?>aria-controls="<?php echo esc_attr($more_id); ?>"<?php endif; ?>
                        >
                            <span class="waki-icon" aria-hidden="true">
                                <?php echo $this->icons->svg('share-toggle'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!empty($hidden_buttons)) : ?>
                <div class="waki-share-extra" data-your-share-extra<?php echo $more_id !== '' ? ' id="' . esc_attr($more_id) . '"' : ''; ?> hidden aria-hidden="true">
                    <?php foreach ($hidden_buttons as $button_markup) :
                        echo $button_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($inline_reactions_markup !== '') : ?>
                <div class="waki-share-react-field" data-your-share-react>
                    <span class="waki-share-react-label"><?php esc_html_e('React', $this->text_domain); ?></span>
                    <?php echo $inline_reactions_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return trim((string) ob_get_clean());
    }

    public function register_media_overlays(): void
    {
        if ($this->media_config_localized || is_admin()) {
            return;
        }

        if (!wp_script_is('your-share', 'enqueued') && !wp_script_is('your-share', 'registered')) {
            return;
        }

        $options = $this->options->all();

        if (!apply_filters('your_share_media_overlay_enabled', true, $options)) {
            return;
        }

        $raw_selectors = $options['media_overlay_selectors'] ?? '';
        $selectors     = $this->normalize_selector_list($raw_selectors);
        $selectors[]   = '[data-your-share-media]';
        $selectors     = apply_filters('your_share_media_overlay_selectors', $selectors, $options);

        $selectors = array_values(array_filter($selectors, static function ($selector) {
            return is_string($selector) && trim($selector) !== '';
        }));

        $selectors = array_values(array_unique($selectors));

        if (empty($selectors)) {
            return;
        }

        $allowed_positions = ['top-start', 'top-end', 'bottom-start', 'bottom-end', 'center'];
        $position          = $options['media_overlay_position'] ?? 'top-end';
        if (!in_array($position, $allowed_positions, true)) {
            $position = 'top-end';
        }

        $allowed_triggers = ['hover', 'always'];
        $trigger          = $options['media_overlay_trigger'] ?? 'hover';
        if (!in_array($trigger, $allowed_triggers, true)) {
            $trigger = 'hover';
        }

        $all_networks = array_keys($this->networks->all());

        $default_overlay_networks = $options['share_networks_default'];
        if (!is_array($default_overlay_networks)) {
            $default_overlay_networks = $this->options->defaults()['share_networks_default'];
        }
        $default_overlay_networks = array_slice($default_overlay_networks, 0, 4);

        $networks = $this->prepare_networks($default_overlay_networks, $all_networks);
        $networks = apply_filters('your_share_media_overlay_networks', $networks, $options);
        $networks = $this->prepare_networks($networks, $all_networks);

        if (empty($networks)) {
            $networks = $this->prepare_networks($this->options->defaults()['share_networks_default'], $all_networks);
        }

        $atts = [
            'networks'     => implode(',', $networks),
            'labels'       => 'hide',
            'style'        => $options['share_style'],
            'size'         => 'sm',
            'align'        => 'left',
            'brand'        => $options['share_brand_colors'] ? '1' : '0',
            'utm_campaign' => '',
            'url'          => '',
            'title'        => '',
        ];

        $context = [
            'placement' => 'overlay',
        ];

        $markup = trim($this->render($context, $atts));
        $markup = apply_filters('your_share_media_overlay_markup', $markup, $options, $context, $atts);

        if ($markup === '') {
            return;
        }

        $config = [
            'selectors'   => $selectors,
            'position'    => $position,
            'trigger'     => $trigger,
            'markup'      => $markup,
            'toggleLabel' => __('Share this media', $this->text_domain),
            'toggleText'  => __('Share', $this->text_domain),
        ];

        $config = apply_filters('your_share_media_overlay_config', $config, $options);

        if (empty($config['selectors']) || empty($config['markup'])) {
            return;
        }

        wp_localize_script('your-share', 'yourShareMedia', $config);

        $this->media_config_localized = true;
    }

    public function render_follow(array $atts): string
    {
        $atts      = $this->normalize_follow_attributes($atts);
        $options   = $this->options->all();
        $defaults  = $this->options->defaults();
        $map      = $this->networks->follow();
        $allowed  = array_keys($map);

        $networks = $this->prepare_networks($atts['networks'] ?? '', $allowed);

        if (empty($networks)) {
            $networks = $this->prepare_networks($options['follow_networks'] ?? [], $allowed);
        }

        $profiles = $options['follow_profiles'] ?? [];

        $style  = $atts['style'];
        $size   = $atts['size'];
        $align  = $atts['align'];
        $labels = $atts['labels'];
        $brand  = (string) $atts['brand'];

        $gap    = absint($options['share_gap'] ?? $defaults['share_gap']);
        $radius = absint($options['share_radius'] ?? $defaults['share_radius']);

        if ($gap <= 0) {
            $gap = absint($defaults['share_gap']);
        }

        if ($radius <= 0) {
            $radius = absint($defaults['share_radius']);
        }

        $classes = [
            'waki-share',
            'waki-follow',
            'waki-share-inline',
            'waki-size-' . sanitize_html_class($size),
            'waki-style-' . sanitize_html_class($style),
            'waki-labels-' . sanitize_html_class($labels),
            'align-' . sanitize_html_class($align),
            $brand === '1' ? 'is-brand' : 'is-mono',
        ];

        $style_inline = sprintf('--waki-gap:%dpx;--waki-radius:%dpx;', $gap, $radius);

        $has_links = false;

        wp_enqueue_style('your-share');

        ob_start();
        ?>
        <div class="<?php echo esc_attr(implode(' ', $classes)); ?>" style="<?php echo esc_attr($style_inline); ?>">
            <?php foreach ($networks as $network) :
                if (!isset($map[$network])) {
                    continue;
                }

                $profile = $profiles[$network] ?? '';

                if ($profile === '') {
                    continue;
                }

                [$label] = $map[$network];
                $href     = esc_url($profile);

                if ($href === '') {
                    continue;
                }

                $has_links = true;

                $attr = [
                    'class'      => 'waki-btn',
                    'data-net'   => $network,
                    'href'       => $href,
                    'target'     => '_blank',
                    'rel'        => 'me noopener',
                    'aria-label' => sprintf(__('Follow on %s', $this->text_domain), $label),
                ];

                $attr = apply_filters('your_share_follow_attributes', $attr, $network, $atts, $options);
                ?>
                <a <?php foreach ($attr as $key => $value) { echo esc_attr($key) . '="' . esc_attr($value) . '" '; } ?>>
                    <?php echo $this->icons->svg($network); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <span class="waki-label"><?php echo esc_html($label); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        <?php
        $html = trim((string) ob_get_clean());

        if (!$has_links) {
            return '';
        }

        return $html;
    }

    public function render_share_inline(array $atts = []): string
    {
        $share_atts = $this->normalize_share_attributes(
            $this->filter_share_attributes($atts)
        );

        $context = [
            'placement' => 'inline',
            'align'     => $share_atts['align'],
        ];

        return $this->render($context, $share_atts);
    }

    public function render_share_floating(array $atts = []): string
    {
        $options        = $this->options->all();
        $override_label = $this->extract_value($atts, ['sticky_labels', 'stickyLabels'], 'hide');

        $share_atts = $this->normalize_share_attributes(
            $this->filter_share_attributes($atts),
            [
                'labels' => $override_label !== null && $override_label !== '' ? $override_label : 'hide',
            ]
        );

        $position = $this->extract_value($atts, ['sticky_position', 'stickyPosition', 'position']);
        if (!is_string($position) || $position === '') {
            $position = $options['sticky_position'] ?? 'left';
        }

        $breakpoint = $this->extract_value($atts, ['sticky_breakpoint', 'stickyBreakpoint', 'breakpoint']);
        if ($breakpoint === null || $breakpoint === '') {
            $breakpoint = intval($options['sticky_breakpoint'] ?? 1024);
        } else {
            $breakpoint = intval($breakpoint);
        }

        $context = [
            'placement'  => 'floating',
            'position'   => $position,
            'breakpoint' => $breakpoint,
        ];

        return $this->render($context, $share_atts);
    }

    public function render_share_suite(array $atts = []): string
    {
        $flags = [
            'share'     => $this->truthy($this->extract_value($atts, ['show_share', 'showShare']), true),
            'follow'    => $this->truthy($this->extract_value($atts, ['show_follow', 'showFollow']), false),
            'reactions' => $this->truthy($this->extract_value($atts, ['show_reactions', 'showReactions']), false),
            'sticky'    => $this->truthy($this->extract_value($atts, ['sticky_toggle', 'stickyToggle']), false),
        ];

        $sections = [];
        $share_atts = $this->filter_share_attributes($atts);

        if ($flags['share']) {
            $sections[] = $this->render_share_inline($share_atts);
        }

        if ($flags['sticky']) {
            $sticky_atts               = $share_atts;
            $sticky_atts['labels']     = $this->extract_value($atts, ['sticky_labels', 'stickyLabels'], 'hide');
            $sticky_atts['position']   = $this->extract_value($atts, ['sticky_position', 'stickyPosition', 'position']);
            $sticky_atts['breakpoint'] = $this->extract_value($atts, ['sticky_breakpoint', 'stickyBreakpoint', 'breakpoint']);

            $sections[] = $this->render_share_floating($sticky_atts);
        }

        if ($flags['follow']) {
            $follow_atts = $this->filter_follow_attributes($atts);

            if (empty($follow_atts['networks']) && isset($share_atts['networks'])) {
                $follow_atts['networks'] = $share_atts['networks'];
            }

            $sections[] = $this->render_follow($follow_atts);
        }

        if ($flags['reactions']) {
            $sections[] = $this->render_reactions([
                'placement' => $this->extract_value($atts, ['reactions_placement', 'reactionsPlacement', 'placement'], 'inline'),
                'post_id'   => $this->extract_value($atts, ['post_id', 'postId']),
            ]);
        }

        $sections = array_filter($sections);

        if (empty($sections)) {
            return '';
        }

        $markup = '<div class="waki-share-suite">' . implode('', $sections) . '</div>';

        return (string) apply_filters('your_share_suite_markup', $markup, $sections, $atts);
    }

    public function render_reactions(array $atts = []): string
    {
        $placement = $this->extract_value($atts, ['placement', 'reactions_placement', 'reactionsPlacement'], 'inline');

        if (!is_string($placement) || $placement === '') {
            $placement = 'inline';
        }

        $placement = $placement === 'sticky' ? 'sticky' : 'inline';

        $post_id = (int) $this->extract_value($atts, ['post_id', 'postId']);

        return $this->reactions->render_block($placement, $post_id > 0 ? $post_id : null);
    }

    private function normalize_share_attributes(array $atts, array $overrides = []): array
    {
        $options = $this->options->all();

        $defaults = [
            'networks'     => '',
            'labels'       => $options['share_labels'] ?? 'auto',
            'style'        => $options['share_style'] ?? 'solid',
            'size'         => $options['share_size'] ?? 'md',
            'align'        => $options['share_align'] ?? 'left',
            'brand'        => !empty($options['share_brand_colors']) ? '1' : '0',
            'utm_campaign' => '',
            'url'          => '',
            'title'        => '',
        ];

        if (!is_array($atts)) {
            $atts = [];
        }

        $defaults = array_merge($defaults, array_filter($overrides, static function ($value) {
            return $value !== null;
        }));

        $atts = wp_parse_args($atts, $defaults);

        $atts['brand']  = $this->truthy($atts['brand'], $defaults['brand'] === '1') ? '1' : '0';
        $atts['labels'] = $atts['labels'] !== '' ? $atts['labels'] : $defaults['labels'];

        if ($atts['align'] === '') {
            $atts['align'] = $defaults['align'];
        }

        return $atts;
    }

    private function normalize_follow_attributes(array $atts): array
    {
        $options = $this->options->all();

        $defaults = [
            'networks' => '',
            'style'    => $options['share_style'] ?? 'solid',
            'size'     => $options['share_size'] ?? 'md',
            'align'    => $options['share_align'] ?? 'left',
            'brand'    => !empty($options['share_brand_colors']) ? '1' : '0',
            'labels'   => 'show',
        ];

        if (!is_array($atts)) {
            $atts = [];
        }

        $atts = wp_parse_args($atts, $defaults);

        $atts['brand'] = $this->truthy($atts['brand'], $defaults['brand'] === '1') ? '1' : '0';

        if ($atts['labels'] === '') {
            $atts['labels'] = 'show';
        }

        if ($atts['align'] === '') {
            $atts['align'] = $defaults['align'];
        }

        return $atts;
    }

    private function filter_share_attributes(array $atts): array
    {
        $map = [
            'networks'     => ['networks', 'share_networks'],
            'labels'       => ['labels', 'share_labels'],
            'style'        => ['style', 'share_style'],
            'size'         => ['size', 'share_size'],
            'align'        => ['align', 'share_align'],
            'brand'        => ['brand', 'share_brand'],
            'utm_campaign' => ['utm_campaign', 'utmCampaign', 'share_utm'],
            'url'          => ['url', 'shareUrl'],
            'title'        => ['title', 'shareTitle'],
        ];

        return $this->filter_attributes_by_keys($atts, $map);
    }

    private function filter_follow_attributes(array $atts): array
    {
        $map = [
            'networks' => ['follow_networks', 'followNetworks', 'networks'],
            'style'    => ['follow_style', 'followStyle'],
            'size'     => ['follow_size', 'followSize'],
            'align'    => ['follow_align', 'followAlign'],
            'brand'    => ['follow_brand', 'followBrand'],
            'labels'   => ['follow_labels', 'followLabels'],
        ];

        return $this->filter_attributes_by_keys($atts, $map);
    }

    private function filter_attributes_by_keys(array $atts, array $map): array
    {
        $output = [];

        foreach ($map as $target => $keys) {
            foreach ($keys as $key) {
                if (is_array($atts) && array_key_exists($key, $atts)) {
                    $output[$target] = $atts[$key];
                    break;
                }
            }
        }

        return $output;
    }

    private function extract_value($atts, array $keys, $default = null)
    {
        if (!is_array($atts)) {
            return $default;
        }

        foreach ($keys as $key) {
            if (array_key_exists($key, $atts)) {
                return $atts[$key];
            }
        }

        return $default;
    }

    private function truthy($value, bool $default = false): bool
    {
        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));

            if ($value === '') {
                return $default;
            }

            return in_array($value, ['1', 'true', 'yes', 'on'], true);
        }

        return $default;
    }

    public function output_share_metadata(): void
    {
        if ($this->metadata_output || is_admin()) {
            return;
        }

        $context = $this->current_share_context([]);

        $title   = $context['title'];
        $url     = $context['url'];
        $excerpt = $context['excerpt'];
        $image   = $context['image'];
        $post    = $context['post'];

        if ($title === '' && $url === '') {
            return;
        }

        $site_name = get_bloginfo('name', 'display');
        $type      = $post instanceof \WP_Post ? 'article' : 'website';
        $card      = $image !== '' ? 'summary_large_image' : 'summary';

        $metadata = [
            [
                'property' => 'og:type',
                'content'  => $type,
            ],
            [
                'property' => 'og:title',
                'content'  => $title,
            ],
            [
                'property' => 'og:description',
                'content'  => $excerpt,
            ],
            [
                'property' => 'og:url',
                'content'  => $url,
            ],
            [
                'property' => 'og:site_name',
                'content'  => $site_name,
            ],
            [
                'property' => 'og:image',
                'content'  => $image,
            ],
            [
                'name'    => 'twitter:card',
                'content' => $card,
            ],
            [
                'name'    => 'twitter:title',
                'content' => $title,
            ],
            [
                'name'    => 'twitter:description',
                'content' => $excerpt,
            ],
            [
                'name'    => 'twitter:image',
                'content' => $image,
            ],
            [
                'name'    => 'twitter:url',
                'content' => $url,
            ],
        ];

        $metadata = array_filter($metadata, static function ($tag) {
            return !empty($tag['content']);
        });

        $metadata = apply_filters('your_share_metadata', $metadata, $context);

        if (empty($metadata)) {
            return;
        }

        $this->metadata_output = true;

        echo "\n<!-- Your Share metadata -->\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

        foreach ($metadata as $tag) {
            $attr_name = isset($tag['property']) ? 'property' : 'name';
            $attr_key  = $tag[$attr_name] ?? '';

            if ($attr_key === '') {
                continue;
            }

            printf(
                '<meta %1$s="%2$s" content="%3$s" />' . "\n",
                esc_attr($attr_name),
                esc_attr($attr_key),
                esc_attr($tag['content'])
            );
        }

        echo "<!-- /Your Share metadata -->\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    private function current_share_context(array $atts): array
    {
        $post  = get_post();
        $title = $atts['title'] ?? '';

        if ($title === '') {
            $title = $post ? get_the_title($post) : get_bloginfo('name');
        }

        $url = $atts['url'] ?? '';

        if ($url === '') {
            $url = $post ? (function_exists('wp_get_canonical_url') ? wp_get_canonical_url($post) : get_permalink($post)) : '';
        }

        if (!$url) {
            $url = home_url('/');
        }

        $excerpt = '';
        $image   = '';

        if ($post instanceof \WP_Post) {
            if (has_excerpt($post)) {
                $excerpt = $post->post_excerpt;
            } else {
                $content = strip_shortcodes((string) $post->post_content);
                $excerpt = wp_trim_words($content, 40, '…');
            }

            $thumbnail_id = get_post_thumbnail_id($post);
            if ($thumbnail_id) {
                $image = wp_get_attachment_image_url($thumbnail_id, 'full') ?: '';
            }
        }

        if ($excerpt === '') {
            $excerpt = get_bloginfo('description', 'display');
        }

        $excerpt = wp_strip_all_tags((string) $excerpt);
        $excerpt = trim(preg_replace('/\s+/', ' ', $excerpt));

        if ($excerpt !== '') {
            $excerpt = wp_html_excerpt($excerpt, 280, '…');
        }

        if ($image === '') {
            $image = get_site_icon_url(512);
        }

        return [
            'post'  => $post,
            'title' => wp_strip_all_tags($title),
            'url'   => $url,
            'excerpt' => $excerpt,
            'image'   => $image,
        ];
    }

    private function prepare_networks($list, array $allowed): array
    {
        if (is_array($list)) {
            $nets = $list;
        } else {
            $nets = explode(',', (string) $list);
        }

        $nets = array_filter(array_map(static function ($value) {
            return sanitize_key($value);
        }, $nets));

        $nets = array_values(array_intersect($nets, $allowed));

        return $nets;
    }

    private function networks_for_country(string $country, array $matrix, array $allowed): array
    {
        $country = strtoupper($country);

        if ($country === '' || empty($matrix[$country])) {
            return [];
        }

        return $this->prepare_networks($matrix[$country], $allowed);
    }

    private function resolve_geo_country(array $options, array $context, array $atts): array
    {
        $geo_source = $options['geo_source'] ?? 'auto';
        $country    = '';
        $source     = '';

        if ($geo_source !== 'manual') {
            $cf_header = $_SERVER['HTTP_CF_IPCOUNTRY'] ?? '';
            if (is_string($cf_header)) {
                $cf_header = strtoupper(substr(sanitize_text_field(wp_unslash($cf_header)), 0, 2));
            } else {
                $cf_header = '';
            }

            if (preg_match('/^[A-Z]{2}$/', $cf_header) && $cf_header !== 'XX') {
                $country = $cf_header;
                $source  = 'cloudflare';
            }
        }

        if ($country === '' && $geo_source === 'auto') {
            $accept_language = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
            if (is_string($accept_language)) {
                $accept_language = sanitize_text_field(wp_unslash($accept_language));
            } else {
                $accept_language = '';
            }

            $guessed = $this->country_from_accept_language($accept_language);
            if ($guessed !== '') {
                $country = $guessed;
                $source  = 'accept-language';
            }
        }

        $country = apply_filters('your_share_geo_country', $country, $geo_source, $options, $context, $atts);
        if (!is_string($country)) {
            $country = '';
        }
        $country = strtoupper(substr($country, 0, 2));

        if (!preg_match('/^[A-Z]{2}$/', $country)) {
            $country = '';
        }

        if ($country === '') {
            $source = $source ?: 'unknown';
        }

        return [
            'country' => $country,
            'source'  => $source ?: 'unknown',
        ];
    }

    private function country_from_accept_language(string $header): string
    {
        if ($header === '') {
            return '';
        }

        $locales = explode(',', $header);

        foreach ($locales as $locale) {
            $locale = trim($locale);

            if ($locale === '') {
                continue;
            }

            $token = explode(';', $locale)[0];
            $token = trim($token);

            $country = $this->country_from_locale_token($token);

            if ($country !== '') {
                return $country;
            }
        }

        return '';
    }

    private function country_from_locale_token(string $token): string
    {
        if ($token === '') {
            return '';
        }

        $parts = preg_split('/[-_]/', $token);

        if (!$parts || count($parts) < 2) {
            return '';
        }

        $country = strtoupper(substr($parts[1], 0, 2));

        if (!preg_match('/^[A-Z]{2}$/', $country)) {
            return '';
        }

        return $country;
    }

    private function format_attributes(array $attributes): string
    {
        $output = '';

        foreach ($attributes as $key => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            $output .= sprintf(' %s="%s"', esc_attr($key), esc_attr((string) $value));
        }

        return $output;
    }

    private function prepare_counts_state(array $options, array $networks, array $share_ctx): array
    {
        $post_id = $share_ctx['post'] instanceof \WP_Post ? (int) $share_ctx['post']->ID : 0;
        $url     = $share_ctx['url'];

        $trackable = array_values(array_filter($networks, static function ($network) {
            return !in_array($network, ['copy', 'native'], true);
        }));

        $state = [
            'enabled'      => false,
            'total'        => 0,
            'ttl'          => '',
            'networks'     => [],
            'post'         => $post_id > 0 ? (string) $post_id : ($trackable ? '0' : ''),
            'network_list' => $trackable ? implode(',', $trackable) : '',
            'url'          => $trackable ? esc_url_raw($url) : '',
        ];

        if (!$this->counts instanceof Counts) {
            return $state;
        }

        if (empty($options['counts_enabled']) || empty($trackable)) {
            return $state;
        }

        $counts = $this->counts->get_counts($post_id, $url, $trackable);

        if (empty($counts['enabled'])) {
            return $state;
        }

        foreach ($trackable as $network) {
            if (!isset($counts['networks'][$network])) {
                $counts['networks'][$network] = [
                    'total' => 0,
                ];
            }
        }

        $state['enabled']  = true;
        $state['networks'] = $counts['networks'];
        $state['total']    = isset($counts['total']) ? (int) $counts['total'] : 0;
        $state['ttl']      = isset($counts['ttl']) ? (int) $counts['ttl'] : 0;

        return $state;
    }

    private function format_count(int $value): string
    {
        if ($value >= 1000000) {
            $formatted = round($value / 1000000, 1);
            if (abs($formatted - floor($formatted)) < 0.1) {
                $formatted = (int) $formatted;
            }

            return sprintf('%sM', $formatted);
        }

        if ($value >= 1000) {
            $formatted = round($value / 1000, 1);
            if (abs($formatted - floor($formatted)) < 0.1) {
                $formatted = (int) $formatted;
            }

            return sprintf('%sK', $formatted);
        }

        return number_format_i18n($value);
    }

    private function build_share_url(string $network, string $base_url, string $title): string
    {
        $url   = rawurlencode($base_url);
        $title = rawurlencode($title);

        switch ($network) {
            case 'facebook':
                return "https://www.facebook.com/sharer/sharer.php?u={$url}";
            case 'x':
                return "https://twitter.com/intent/tweet?text={$title}&url={$url}";
            case 'threads':
                return "https://www.threads.net/intent/post?text={$title}%20{$url}";
            case 'bluesky':
                return "https://bsky.app/intent/compose?text={$title}%20{$url}";
            case 'whatsapp':
                return "https://wa.me/?text={$title}%20{$url}";
            case 'telegram':
                return "https://t.me/share/url?url={$url}&text={$title}";
            case 'line':
                return "https://social-plugins.line.me/lineit/share?url={$url}";
            case 'linkedin':
                return "https://www.linkedin.com/sharing/share-offsite/?url={$url}";
            case 'pinterest':
                return "https://www.pinterest.com/pin/create/button/?url={$url}&description={$title}";
            case 'reddit':
                return "https://www.reddit.com/submit?url={$url}&title={$title}";
            case 'tumblr':
                return "https://www.tumblr.com/widgets/share/tool?canonicalUrl={$url}&title={$title}";
            case 'mastodon':
                return "https://mastodon.social/share?text={$title}%20{$url}";
            case 'vk':
                return "https://vk.com/share.php?url={$url}&title={$title}";
            case 'weibo':
                return "https://service.weibo.com/share/share.php?url={$url}&title={$title}";
            case 'odnoklassniki':
                return "https://connect.ok.ru/offer?url={$url}&title={$title}";
            case 'xing':
                return "https://www.xing.com/spi/shares/new?url={$url}";
            case 'pocket':
                return "https://getpocket.com/save?url={$url}&title={$title}";
            case 'flipboard':
                return "https://share.flipboard.com/bookmarklet/popout?v=2&title={$title}&url={$url}";
            case 'buffer':
                return "https://buffer.com/add?text={$title}&url={$url}";
            case 'mix':
                return "https://mix.com/mixit?url={$url}";
            case 'evernote':
                return "https://www.evernote.com/clip.action?url={$url}&title={$title}";
            case 'diaspora':
                return "https://share.diasporafoundation.org/?title={$title}&url={$url}";
            case 'hacker-news':
                return "https://news.ycombinator.com/submitlink?u={$url}&t={$title}";
            case 'email':
                return "mailto:?subject={$title}&body={$title}%20—%20{$url}";
            default:
                return $base_url;
        }
    }

    private function normalize_selector_list($value): array
    {
        if (is_array($value)) {
            $selectors = $value;
        } else {
            $selectors = preg_split('/[\r\n,]+/', (string) $value);
        }

        if (!$selectors) {
            return [];
        }

        $selectors = array_map(static function ($selector) {
            return trim((string) $selector);
        }, $selectors);

        $selectors = array_filter($selectors, static function ($selector) {
            return $selector !== '';
        });

        return array_values(array_unique($selectors));
    }
}
