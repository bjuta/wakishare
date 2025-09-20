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
        static $cache = [];

        $map = [
            'facebook' => 'facebook',
            'x'        => 'x',
            'whatsapp' => 'whatsapp',
            'telegram' => 'telegram',
            'linkedin' => 'linkedin',
            'reddit'   => 'reddit',
            'email'    => 'email',
            'copy'     => 'copy',
            'native'   => 'native',
        ];

        if (!isset($map[$net])) {
            return $this->fallback_svg();
        }

        if (!array_key_exists($net, $cache)) {
            $file = plugin_dir_path(__FILE__) . 'assets/icons/' . $map[$net] . '.svg';
            $svg  = '';

            if (is_readable($file)) {
                $svg = trim(file_get_contents($file));
                if ($svg !== '') {
                    $svg = preg_replace('/<\?xml[^>]*>/i', '', $svg);
                    $allowed = [
                        'svg'  => [
                            'xmlns'        => true,
                            'viewBox'      => true,
                            'viewbox'      => true,
                            'fill'         => true,
                            'class'        => true,
                            'aria-hidden'  => true,
                            'focusable'    => true,
                            'role'         => true,
                        ],
                        'path' => [
                            'd'    => true,
                            'fill' => true,
                        ],
                    ];
                    if (function_exists('wp_kses')) {
                        $svg = wp_kses($svg, $allowed);
                    }
                }
            }

            $cache[$net] = $svg ?: $this->fallback_svg();
        }

        return $cache[$net];
    }

    private function fallback_svg() {
        return '<svg class="waki-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="currentColor"><path d="M12 3l5 5h-3v6h-4V8H7l5-5z"/><path d="M5 17h14v4H5z"/></svg>';
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
