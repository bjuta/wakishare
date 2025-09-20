<?php
/**
 * Plugin Name: Waki Share (Single-File)
 * Description: Minimal, fast social share buttons with per-network UTM tagging, inline + floating layouts, Web Share API, and Copy Link. Shortcode: [waki_share].
 * Version: 1.0.0
 * Author: WAKILISHA
 * License: GPLv2 or later
 */

if (!defined('ABSPATH')) { exit; }

final class Waki_Share {
    const OPT_KEY = 'waki_share_options';
    const VER     = '1.0.0';
    private static $instance = null;

    public static function instance() { return self::$instance ?: (self::$instance = new self()); }

    private function __construct() {
        add_action('init',              [$this, 'register_shortcode']);
        add_action('wp_enqueue_scripts',[$this, 'enqueue_assets']);
        add_action('wp_footer',         [$this, 'maybe_render_floating']);
        add_action('admin_menu',        [$this, 'admin_menu']);
        add_action('admin_init',        [$this, 'register_settings']);
    }

    /** ---------- Defaults & Options ---------- */
    public function defaults() {
        return [
            'enable_utm'         => 1,
            'utm_medium'         => 'social',
            'utm_campaign'       => '',
            'utm_term'           => '',
            'utm_content'        => 'post-{ID}-share',
            'brand_colors'       => 1,
            'style'              => 'solid',    // solid|outline|ghost
            'size'               => 'md',       // sm|md|lg
            'labels'             => 'auto',     // auto|show|hide
            'networks'           => 'facebook,x,whatsapp,telegram,linkedin,reddit,email,copy',
            'floating_enabled'   => 0,
            'floating_position'  => 'left',     // left|right
            'floating_breakpoint'=> 1024,       // px; show floating on >= this width
            'gap'                => '8',
            'radius'             => '9999',     // px (9999 = pill)
        ];
    }
    public function get_opts() {
        $opts = get_option(self::OPT_KEY, []);
        return wp_parse_args(is_array($opts)? $opts : [], $this->defaults());
    }

    /** ---------- Admin ---------- */
    public function admin_menu() {
        add_options_page('Waki Share', 'Waki Share', 'manage_options', 'waki-share', [$this, 'settings_page']);
    }
    public function register_settings() {
        register_setting(self::OPT_KEY, self::OPT_KEY, function($in){
            $d = $this->defaults();
            $out = [];
            $out['enable_utm']          = isset($in['enable_utm']) ? 1 : 0;
            $out['utm_medium']          = sanitize_text_field($in['utm_medium'] ?? $d['utm_medium']);
            $out['utm_campaign']        = sanitize_text_field($in['utm_campaign'] ?? '');
            $out['utm_term']            = sanitize_text_field($in['utm_term'] ?? '');
            $out['utm_content']         = sanitize_text_field($in['utm_content'] ?? $d['utm_content']);
            $out['brand_colors']        = isset($in['brand_colors']) ? 1 : 0;
            $out['style']               = in_array($in['style'] ?? $d['style'], ['solid','outline','ghost'], true) ? $in['style'] : $d['style'];
            $out['size']                = in_array($in['size'] ?? $d['size'], ['sm','md','lg'], true) ? $in['size'] : $d['size'];
            $out['labels']              = in_array($in['labels'] ?? $d['labels'], ['auto','show','hide'], true) ? $in['labels'] : $d['labels'];
            $out['networks']            = sanitize_text_field($in['networks'] ?? $d['networks']);
            $out['floating_enabled']    = isset($in['floating_enabled']) ? 1 : 0;
            $out['floating_position']   = in_array($in['floating_position'] ?? $d['floating_position'], ['left','right'], true) ? $in['floating_position'] : $d['floating_position'];
            $out['floating_breakpoint'] = intval($in['floating_breakpoint'] ?? $d['floating_breakpoint']);
            $out['gap']                 = sanitize_text_field($in['gap'] ?? $d['gap']);
            $out['radius']              = sanitize_text_field($in['radius'] ?? $d['radius']);
            return $out;
        });
    }
    public function settings_page() {
        if (!current_user_can('manage_options')) { return; }
        $o = $this->get_opts();
        ?>
        <div class="wrap">
          <h1>Waki Share Settings</h1>
          <form method="post" action="options.php">
            <?php settings_fields(self::OPT_KEY); ?>
            <table class="form-table" role="presentation">
              <tr><th scope="row">Enable UTM</th>
                <td><label><input type="checkbox" name="<?php echo esc_attr(self::OPT_KEY); ?>[enable_utm]" <?php checked($o['enable_utm']); ?>> Append utm_* to shared URLs</label></td></tr>
              <tr><th scope="row">UTM Medium</th>
                <td><input type="text" name="<?php echo esc_attr(self::OPT_KEY); ?>[utm_medium]" value="<?php echo esc_attr($o['utm_medium']); ?>" class="regular-text"></td></tr>
              <tr><th scope="row">UTM Campaign (default)</th>
                <td><input type="text" name="<?php echo esc_attr(self::OPT_KEY); ?>[utm_campaign]" value="<?php echo esc_attr($o['utm_campaign']); ?>" class="regular-text">
                <p class="description">Shortcode can override with <code>utm_campaign="..."</code>.</p></td></tr>
              <tr><th scope="row">UTM Term (default)</th>
                <td><input type="text" name="<?php echo esc_attr(self::OPT_KEY); ?>[utm_term]" value="<?php echo esc_attr($o['utm_term']); ?>" class="regular-text"></td></tr>
              <tr><th scope="row">UTM Content Template</th>
                <td><input type="text" name="<?php echo esc_attr(self::OPT_KEY); ?>[utm_content]" value="<?php echo esc_attr($o['utm_content']); ?>" class="regular-text">
                <p class="description">Supports tokens: <code>{ID}</code>, <code>{slug}</code>, <code>{post_type}</code>.</p></td></tr>
              <tr><th scope="row">Brand Colors</th>
                <td><label><input type="checkbox" name="<?php echo esc_attr(self::OPT_KEY); ?>[brand_colors]" <?php checked($o['brand_colors']); ?>> Use network brand colors (uncheck for monochrome)</label></td></tr>
              <tr><th scope="row">Style / Size / Labels</th>
                <td>
                  <select name="<?php echo esc_attr(self::OPT_KEY); ?>[style]">
                    <option value="solid" <?php selected($o['style'],'solid'); ?>>Solid</option>
                    <option value="outline" <?php selected($o['style'],'outline'); ?>>Outline</option>
                    <option value="ghost" <?php selected($o['style'],'ghost'); ?>>Ghost</option>
                  </select>
                  <select name="<?php echo esc_attr(self::OPT_KEY); ?>[size]">
                    <option value="sm" <?php selected($o['size'],'sm'); ?>>Small</option>
                    <option value="md" <?php selected($o['size'],'md'); ?>>Medium</option>
                    <option value="lg" <?php selected($o['size'],'lg'); ?>>Large</option>
                  </select>
                  <select name="<?php echo esc_attr(self::OPT_KEY); ?>[labels]">
                    <option value="auto" <?php selected($o['labels'],'auto'); ?>>Auto</option>
                    <option value="show" <?php selected($o['labels'],'show'); ?>>Show</option>
                    <option value="hide" <?php selected($o['labels'],'hide'); ?>>Hide</option>
                  </select>
                </td></tr>
              <tr><th scope="row">Default Networks</th>
                <td><input type="text" name="<?php echo esc_attr(self::OPT_KEY); ?>[networks]" value="<?php echo esc_attr($o['networks']); ?>" class="regular-text">
                <p class="description">Comma list: <code>facebook,x,whatsapp,telegram,linkedin,reddit,email,copy</code></p></td></tr>
              <tr><th scope="row">Floating Bar</th>
                <td>
                  <label><input type="checkbox" name="<?php echo esc_attr(self::OPT_KEY); ?>[floating_enabled]" <?php checked($o['floating_enabled']); ?>> Enable</label><br>
                  Position:
                  <select name="<?php echo esc_attr(self::OPT_KEY); ?>[floating_position]">
                    <option value="left" <?php selected($o['floating_position'],'left'); ?>>Left</option>
                    <option value="right" <?php selected($o['floating_position'],'right'); ?>>Right</option>
                  </select>
                  &nbsp; Show on ≥
                  <input type="number" step="1" min="0" name="<?php echo esc_attr(self::OPT_KEY); ?>[floating_breakpoint]" value="<?php echo esc_attr($o['floating_breakpoint']); ?>" style="width:100px;"> px
                </td></tr>
              <tr><th scope="row">Gap / Radius</th>
                <td>
                  <input type="number" name="<?php echo esc_attr(self::OPT_KEY); ?>[gap]" value="<?php echo esc_attr($o['gap']); ?>" style="width:100px;"> px
                  &nbsp; <input type="number" name="<?php echo esc_attr(self::OPT_KEY); ?>[radius]" value="<?php echo esc_attr($o['radius']); ?>" style="width:100px;"> px
                </td></tr>
            </table>
            <?php submit_button(); ?>
          </form>
          <p><strong>Shortcode:</strong> <code>[waki_share]</code> &middot; Attributes: <code>networks</code>, <code>labels</code>, <code>style</code>, <code>size</code>, <code>align</code>, <code>brand</code> (1|0), <code>utm_campaign</code>, <code>url</code>, <code>title</code>.</p>
        </div>
        <?php
    }

    /** ---------- Frontend ---------- */
    public function register_shortcode() { add_shortcode('waki_share', [$this, 'shortcode']); }

    public function shortcode($atts, $content = '') {
        $o = $this->get_opts();
        $atts = shortcode_atts([
            'networks'     => $o['networks'],
            'labels'       => $o['labels'],
            'style'        => $o['style'],
            'size'         => $o['size'],
            'align'        => 'left',         // left|center|right
            'brand'        => $o['brand_colors'] ? '1' : '0',
            'utm_campaign' => '',
            'url'          => '',
            'title'        => '',
        ], $atts, 'waki_share');

        $ctx = [
            'placement' => 'inline',
            'align'     => $atts['align'],
        ];
        return $this->render_buttons($ctx, $atts);
    }

    public function maybe_render_floating() {
        $o = $this->get_opts();
        if (!$o['floating_enabled']) return;

        // Use defaults; allow override via filter
        $atts = [
            'networks'     => $o['networks'],
            'labels'       => 'hide',
            'style'        => $o['style'],
            'size'         => 'sm',
            'align'        => 'left',
            'brand'        => $o['brand_colors'] ? '1' : '0',
            'utm_campaign' => '',
            'url'          => '',
            'title'        => '',
        ];
        $ctx = [
            'placement' => 'floating',
            'position'  => $o['floating_position'],
            'breakpoint'=> intval($o['floating_breakpoint']),
        ];
        echo $this->render_buttons($ctx, $atts);
    }

    /** ---------- Rendering ---------- */
    private function current_share_context($atts) {
        $post = get_post();
        $title = $atts['title'] ?: ($post ? get_the_title($post) : get_bloginfo('name'));
        $url   = $atts['url']   ?: (function_exists('wp_get_canonical_url') ? wp_get_canonical_url($post) : get_permalink($post));
        if (!$url) { $url = home_url('/'); }
        return [
            'post'  => $post,
            'title' => wp_strip_all_tags($title),
            'url'   => $url,
        ];
    }

    private function networks_map() {
        // label, brand hex, SVG path (simple), share link builder callback
        return apply_filters('waki_share_networks', [
            'facebook' => ['Facebook', '#1877F2'],
            'x'        => ['X',        '#000000'],
            'whatsapp' => ['WhatsApp', '#25D366'],
            'telegram' => ['Telegram', '#26A5E4'],
            'linkedin' => ['LinkedIn', '#0A66C2'],
            'reddit'   => ['Reddit',   '#FF4500'],
            'email'    => ['Email',    '#6B7280'],
            'copy'     => ['Copy',     '#6B7280'],
            'native'   => ['Share',    '#6B7280'], // Web Share API button (optional)
        ]);
    }

    private function build_share_url($net, $base_url, $title) {
        $url   = rawurlencode($base_url);
        $title = rawurlencode($title);
        switch ($net) {
            case 'facebook': return "https://www.facebook.com/sharer/sharer.php?u={$url}";
            case 'x':        return "https://twitter.com/intent/tweet?text={$title}&url={$url}";
            case 'whatsapp': return "https://wa.me/?text={$title}%20{$url}";
            case 'telegram': return "https://t.me/share/url?url={$url}&text={$title}";
            case 'linkedin': return "https://www.linkedin.com/sharing/share-offsite/?url={$url}";
            case 'reddit':   return "https://www.reddit.com/submit?url={$url}&title={$title}";
            case 'email':    return "mailto:?subject={$title}&body={$title}%20—%20{$url}";
            default:         return $base_url;
        }
    }

    private function append_utm($url, $net, $post, $atts) {
        $o = $this->get_opts();
        if (!$o['enable_utm']) return $url;

        $campaign = $atts['utm_campaign'] ?: $o['utm_campaign'];
        if (!$campaign && $post) { $campaign = sanitize_title(get_post_type($post)) . '-' . date_i18n('Ymd'); }

        $content = $o['utm_content'];
        if ($post) {
            $repl = [
                '{ID}'        => $post->ID,
                '{slug}'      => $post->post_name,
                '{post_type}' => get_post_type($post),
            ];
            $content = strtr($content, $repl);
        }

        $args = [
            'utm_source'   => $net,
            'utm_medium'   => $o['utm_medium'],
            'utm_campaign' => $campaign,
        ];
        if (!empty($o['utm_term']))    { $args['utm_term']    = $o['utm_term']; }
        if (!empty($o['utm_content'])) { $args['utm_content'] = $content; }

        // Preserve query; add/merge UTM
        $url = add_query_arg($args, $url);
        return $url;
    }

    private function render_buttons($ctx, $atts) {
        $o   = $this->get_opts();
        $map = $this->networks_map();
        $cx  = $this->current_share_context($atts);

        $nets = array_filter(array_map('trim', explode(',', strtolower($atts['networks']))));
        $nets = array_values(array_intersect($nets, array_keys($map))); // keep valid

        $classes = [
            'waki-share',
            'waki-size-' . sanitize_html_class($atts['size']),
            'waki-style-' . sanitize_html_class($atts['style']),
            'waki-labels-' . sanitize_html_class($atts['labels']),
            $atts['brand'] === '1' ? 'is-brand' : 'is-mono',
        ];
        $style_inline = sprintf('--waki-gap:%spx;--waki-radius:%spx;', esc_attr($o['gap']), esc_attr($o['radius']));

        if (($ctx['placement'] ?? '') === 'floating') {
            $classes[] = 'waki-share-floating';
            $classes[] = 'pos-' . sanitize_html_class($ctx['position'] ?? 'left');
            $style_inline .= sprintf('--waki-breakpoint:%dpx;', intval($ctx['breakpoint'] ?? 1024));
        } else {
            $classes[] = 'waki-share-inline';
            $classes[] = 'align-' . sanitize_html_class($ctx['align'] ?? 'left');
        }

        $title = $cx['title'];
        $base  = $cx['url'];

        ob_start(); ?>
        <div class="<?php echo esc_attr(implode(' ', $classes)); ?>" style="<?php echo esc_attr($style_inline); ?>">
        <?php
        // Auto include "native" if labels auto and device supports? We'll always render; JS hides if unsupported.
        if (!in_array('native', $nets, true)) { $nets[] = 'native'; }

        foreach ($nets as $net) {
            if (!isset($map[$net])) continue;
            [$label, $brand] = $map[$net];

            if ($net === 'copy' || $net === 'native') {
                $href = '#';
            } else {
                $utm_url = $this->append_utm($base, $net, $cx['post'], $atts);
                $href    = $this->build_share_url($net, $utm_url, $title);
            }

            $attr = [
                'class'    => 'waki-btn',
                'data-net' => esc_attr($net),
                'aria-label'=> esc_attr('Share on ' . $label),
                'role'     => 'link',
            ];
            if ($net !== 'copy' && $net !== 'native') {
                $attr['href']   = esc_url($href);
                $attr['target'] = '_blank';
                $attr['rel']    = 'noopener noreferrer';
            } else {
                $attr['href']   = '#';
            }

            echo '<a';
            foreach ($attr as $k=>$v) { echo ' ' . $k . '="' . $v . '"'; }
            echo '>';
            echo $this->svg_icon($net);
            echo '<span class="waki-label">' . esc_html($label) . '</span>';
            echo '</a>';
        }
        ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function svg_icon($net) {
        // intentionally minimal; monochrome-friendly
        $paths = [
            'facebook' => 'M18 0h-4a6 6 0 0 0-6 6v4H4v4h4v10h4V14h4l1-4h-5V6a2 2 0 0 1 2-2h3z',
            'x'        => 'M0 0l9 10.5L0 24h4.5L12 13.5 19.5 24H24L15 12l9-12h-4.5L12 10.5 4.5 0z',
            'whatsapp' => 'M12 0a12 12 0 0 0-10.4 18L0 24l6-1.6A12 12 0 1 0 12 0zm6.8 17.2c-.3.8-1.7 1.6-2.4 1.7-.6.1-1.3.2-2.1 0-1.2-.3-2.7-.9-4.4-2.2-1.5-1.1-2.6-2.5-3.3-3.9-.7-1.3-.9-2.5-.8-3.4.1-.8.6-1.8 1.4-2.2.4-.3.9-.2 1.2 0l1.7 2.5c.2.3.2.6 0 .9l-.6 1c-.2.3-.1.7.1 1 1.3 2 3 3.2 4.9 4 .3.1.7.1 1-.2l1-.9c.3-.3.7-.3 1 0l2.4 1.5c.3.2.4.6.3 1.2z',
            'telegram' => 'M22.5 1.5L1.6 9.6c-1 .4-1 .9-.2 1.2l5.2 1.6 1.9 6c.2.4.4.5.8.2l2.7-2.2 5.3 3.9c.5.3.8.2.9-.4l3.3-16.7c.2-.9-.3-1.3-1-1zM8 12.6l9.8-6.1c.5-.3.9-.1.6.2L10 13.5l-.3 4.2-1.7-5.1z',
            'linkedin' => 'M4.98 3.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5zM3 9h4v12H3zM10 9h4v2h.1c.6-1 2-2.1 4-2.1 4.3 0 5.1 2.8 5.1 6.5V21h-4v-5.1c0-1.2 0-2.8-1.7-2.8s-2 1.3-2 2.7V21h-4z',
            'reddit'   => 'M22 12a4 4 0 0 0-3.5-3.97l-2.2-1a1 1 0 0 0-1.3.6l-.7 2A8 8 0 1 0 20 16a2.5 2.5 0 1 0-2.5-2.5A2.5 2.5 0 1 0 22 12zM8.5 13A1.5 1.5 0 1 1 10 14.5 1.5 1.5 0 0 1 8.5 13zm7 0A1.5 1.5 0 1 1 17 14.5 1.5 1.5 0 0 1 15.5 13zM12 18c-1.7 0-3.2-.7-4.2-1.7a.5.5 0 1 1 .7-.7c.8.8 2 1.4 3.5 1.4s2.7-.6 3.5-1.4a.5.5 0 1 1 .7.7C15.2 17.3 13.7 18 12 18z',
            'email'    => 'M2 4h20v16H2V4zm10 7L3.5 6.5h17L12 11zm0 2l8.5-5.5V18h-17V7.5L12 13z',
            'copy'     => 'M8 2h10v14H8zM6 4H4v16h12v-2H6z',
            'native'   => 'M12 2l3 5h5l-4 4 2 7-6-4-6 4 2-7-4-4h5z',
        ];
        $d = $paths[$net] ?? 'M0 0h24v24H0z';
        return '<svg class="waki-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="'.esc_attr($d).'"/></svg>';
    }

    /** ---------- Assets ---------- */
    public function enqueue_assets() {
        // Dummy handles to attach inline CSS/JS (single-file plugin)
        wp_register_style('waki-share', false, [], self::VER);
        wp_enqueue_style('waki-share');
        wp_add_inline_style('waki-share', $this->css());

        wp_register_script('waki-share', false, [], self::VER, true);
        wp_enqueue_script('waki-share');
        wp_add_inline_script('waki-share', $this->js());
    }

    private function css() {
        $o = $this->get_opts();
        $gap = intval($o['gap']);
        $radius = intval($o['radius']);
        return <<<CSS
.waki-share{--waki-gap:{$gap}px;--waki-radius:{$radius}px;display:flex;flex-wrap:wrap;gap:var(--waki-gap);align-items:center}
.waki-share.align-center{justify-content:center}
.waki-share.align-right{justify-content:flex-end}
.waki-btn{display:inline-flex;align-items:center;gap:.5rem;text-decoration:none;border:1px solid transparent;border-radius:var(--waki-radius);transition:transform .08s ease,background .2s ease,border-color .2s ease;line-height:1}
.waki-icon{width:1.25rem;height:1.25rem;display:inline-block}
.waki-label{font-size:.875rem;font-weight:500}
.waki-size-sm .waki-btn{padding:.35rem .5rem;font-size:.85rem}
.waki-size-md .waki-btn{padding:.5rem .75rem;font-size:.9rem}
.waki-size-lg .waki-btn{padding:.65rem .9rem;font-size:1rem}
.waki-style-solid.is-brand .waki-btn[data-net]{color:#fff}
.waki-style-solid.is-mono .waki-btn{background:#111;color:#fff}
.waki-style-outline .waki-btn{background:transparent;border-color:rgba(0,0,0,.18);color:#111}
.waki-style-ghost .waki-btn{background:transparent;color:#111}
.waki-style-outline .waki-btn:hover,
.waki-style-ghost .waki-btn:hover{border-color:rgba(0,0,0,.35);transform:translateY(-1px)}
.waki-style-solid .waki-btn:hover{filter:brightness(0.95);transform:translateY(-1px)}
/* Brand colors */
.is-brand .waki-btn[data-net="facebook"]{background:#1877F2}
.is-brand .waki-btn[data-net="x"]{background:#000}
.is-brand .waki-btn[data-net="whatsapp"]{background:#25D366}
.is-brand .waki-btn[data-net="telegram"]{background:#26A5E4}
.is-brand .waki-btn[data-net="linkedin"]{background:#0A66C2}
.is-brand .waki-btn[data-net="reddit"]{background:#FF4500}
.is-brand .waki-btn[data-net="email"]{background:#6B7280}
.is-brand .waki-btn[data-net="copy"]{background:#6B7280}
.is-brand .waki-btn[data-net="native"]{background:#6B7280}
/* Label visibility */
.waki-labels-hide .waki-label{display:none}
@media (max-width:640px){.waki-labels-auto .waki-label{display:none}}
/* Floating bar */
.waki-share-floating{position:fixed;top:40%;transform:translateY(-50%);z-index:9999;flex-direction:column;background:transparent}
.waki-share-floating.pos-left{left:12px}
.waki-share-floating.pos-right{right:12px}
@media (max-width: var(--waki-breakpoint)){.waki-share-floating{display:none}}
/* Toast */
#wakiShareToast{position:fixed;left:50%;bottom:16px;transform:translateX(-50%);background:#111;color:#fff;padding:.5rem .75rem;border-radius:8px;font-size:.875rem;opacity:0;pointer-events:none;transition:opacity .25s ease}
#wakiShareToast.show{opacity:1}
/* Improve visibility if theme has aggressive SVG resets */
.waki-share svg, .waki-share svg *{opacity:1;display:inline-block;fill:currentColor}
CSS;
    }

    private function js() {
        // Minimal JS: popups, copy link, native share, toast
        return <<<JS
(function(){
  function openPopup(url){
    var w=600,h=500,left=(screen.width-w)/2,top=(screen.height-h)/2;
    window.open(url,'wakiShare','toolbar=0,status=0,width='+w+',height='+h+',top='+top+',left='+left);
  }
  function byClass(root, cls){ return root.getElementsByClassName(cls); }
  function toast(msg){
    var t = document.getElementById('wakiShareToast');
    if(!t){ t = document.createElement('div'); t.id='wakiShareToast'; document.body.appendChild(t); }
    t.textContent = msg; t.classList.add('show');
    setTimeout(function(){ t.classList.remove('show'); }, 1600);
  }
  document.addEventListener('click', function(e){
    var a = e.target.closest('.waki-btn');
    if(!a || !a.closest('.waki-share')) return;

    var net = a.getAttribute('data-net');

    if(net === 'copy'){
      e.preventDefault();
      var current = window.location.href;
      try{
        navigator.clipboard.writeText(current);
        toast('Link copied');
      } catch(err){
        var ta=document.createElement('textarea'); ta.value=current; document.body.appendChild(ta); ta.select();
        try{ document.execCommand('copy'); toast('Link copied'); } finally { document.body.removeChild(ta); }
      }
      return;
    }
    if(net === 'native'){
      e.preventDefault();
      if(navigator.share){
        navigator.share({title: document.title, url: window.location.href}).catch(function(){});
      } else {
        toast('Sharing not supported');
      }
      return;
    }
    // default: open in centered popup except mailto & wa/me on mobile
    var href = a.getAttribute('href') || '';
    if(href.indexOf('mailto:') === 0 || href.indexOf('wa.me') !== -1){ return; }
    e.preventDefault(); openPopup(href);
  });
})();
JS;
    }
}

/** Bootstrap */
Waki_Share::instance();

/** -------------------- USAGE NOTES --------------------
 * Shortcode:
 *   [waki_share]
 *   [waki_share networks="facebook,x,whatsapp,telegram,linkedin,reddit,email,copy" labels="show" style="outline" size="lg" align="center" brand="1" utm_campaign="charts-launch"]
 *
 * Template tag (optional):
 *   echo do_shortcode('[waki_share]');
 *
 * Admin:
 *   Settings → Waki Share: set defaults (UTM, style, floating bar).
 *
 * Filters:
 *   add_filter('waki_share_networks', function($nets){
 *      $nets['pinterest'] = ['Pinterest', '#E60023']; // add and then handle building URL via custom JS or extend build_share_url
 *      return $nets;
 *   });
 */
