<?php

namespace YourShare;

if (!defined('ABSPATH')) {
    exit;
}

class Options
{
    /** @var string */
    private $option_key;

    public function __construct(string $option_key)
    {
        $this->option_key = $option_key;
    }

    public function key(): string
    {
        return $this->option_key;
    }

    public function defaults(): array
    {
        return [
            'enable_utm'          => 1,
            'utm_medium'          => 'social',
            'utm_campaign'        => '',
            'utm_term'            => '',
            'utm_content'         => 'post-{ID}-share',
            'brand_colors'        => 1,
            'style'               => 'solid',
            'size'                => 'md',
            'labels'              => 'auto',
            'networks'            => 'facebook,x,whatsapp,telegram,linkedin,reddit,email,copy',
            'floating_enabled'    => 0,
            'floating_position'   => 'left',
            'floating_breakpoint' => 1024,
            'gap'                 => '8',
            'radius'              => '9999',
        ];
    }

    public function all(): array
    {
        $stored = get_option($this->option_key, []);

        if (!is_array($stored)) {
            $stored = [];
        }

        return wp_parse_args($stored, $this->defaults());
    }

    public function sanitize(array $input): array
    {
        $defaults = $this->defaults();

        $output = [];
        $output['enable_utm']          = !empty($input['enable_utm']) ? 1 : 0;
        $output['utm_medium']          = sanitize_text_field($input['utm_medium'] ?? $defaults['utm_medium']);
        $output['utm_campaign']        = sanitize_text_field($input['utm_campaign'] ?? '');
        $output['utm_term']            = sanitize_text_field($input['utm_term'] ?? '');
        $output['utm_content']         = sanitize_text_field($input['utm_content'] ?? $defaults['utm_content']);
        $output['brand_colors']        = !empty($input['brand_colors']) ? 1 : 0;
        $output['style']               = in_array($input['style'] ?? $defaults['style'], ['solid', 'outline', 'ghost'], true)
            ? $input['style'] : $defaults['style'];
        $output['size']                = in_array($input['size'] ?? $defaults['size'], ['sm', 'md', 'lg'], true)
            ? $input['size'] : $defaults['size'];
        $output['labels']              = in_array($input['labels'] ?? $defaults['labels'], ['auto', 'show', 'hide'], true)
            ? $input['labels'] : $defaults['labels'];
        $output['networks']            = sanitize_text_field($input['networks'] ?? $defaults['networks']);
        $output['floating_enabled']    = !empty($input['floating_enabled']) ? 1 : 0;
        $output['floating_position']   = in_array($input['floating_position'] ?? $defaults['floating_position'], ['left', 'right'], true)
            ? $input['floating_position'] : $defaults['floating_position'];
        $output['floating_breakpoint'] = intval($input['floating_breakpoint'] ?? $defaults['floating_breakpoint']);
        $output['gap']                 = sanitize_text_field($input['gap'] ?? $defaults['gap']);
        $output['radius']              = sanitize_text_field($input['radius'] ?? $defaults['radius']);

        return $output;
    }
}
