<?php

namespace YourShare;

if (!defined('ABSPATH')) {
    exit;
}

class Networks
{
    /** @var string */
    private $text_domain;

    public function __construct(string $text_domain)
    {
        $this->text_domain = $text_domain;
    }

    public function all(): array
    {
        $map = [
            'facebook' => [__('Facebook', $this->text_domain), '#1877F2'],
            'x'        => [__('X', $this->text_domain), '#000000'],
            'whatsapp' => [__('WhatsApp', $this->text_domain), '#25D366'],
            'telegram' => [__('Telegram', $this->text_domain), '#26A5E4'],
            'linkedin' => [__('LinkedIn', $this->text_domain), '#0A66C2'],
            'reddit'   => [__('Reddit', $this->text_domain), '#FF4500'],
            'email'    => [__('Email', $this->text_domain), '#6B7280'],
            'copy'     => [__('Copy', $this->text_domain), '#6B7280'],
            'native'   => [__('Share', $this->text_domain), '#6B7280'],
        ];

        return apply_filters('your_share_networks', $map);
    }
}
