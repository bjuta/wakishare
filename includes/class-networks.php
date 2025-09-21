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
            'facebook'      => [__('Facebook', $this->text_domain), '#1877F2'],
            'x'             => [__('X', $this->text_domain), '#000000'],
            'threads'       => [__('Threads', $this->text_domain), '#000000'],
            'bluesky'       => [__('Bluesky', $this->text_domain), '#0285FF'],
            'whatsapp'      => [__('WhatsApp', $this->text_domain), '#25D366'],
            'telegram'      => [__('Telegram', $this->text_domain), '#26A5E4'],
            'line'          => [__('LINE', $this->text_domain), '#06C755'],
            'linkedin'      => [__('LinkedIn', $this->text_domain), '#0A66C2'],
            'pinterest'     => [__('Pinterest', $this->text_domain), '#E60023'],
            'reddit'        => [__('Reddit', $this->text_domain), '#FF4500'],
            'tumblr'        => [__('Tumblr', $this->text_domain), '#001935'],
            'mastodon'      => [__('Mastodon', $this->text_domain), '#6364FF'],
            'vk'            => [__('VK', $this->text_domain), '#0077FF'],
            'weibo'         => [__('Weibo', $this->text_domain), '#E6162D'],
            'odnoklassniki' => [__('Odnoklassniki', $this->text_domain), '#EE8208'],
            'xing'          => [__('Xing', $this->text_domain), '#006567'],
            'pocket'        => [__('Pocket', $this->text_domain), '#EF4056'],
            'flipboard'     => [__('Flipboard', $this->text_domain), '#E12828'],
            'buffer'        => [__('Buffer', $this->text_domain), '#168EEA'],
            'mix'           => [__('Mix', $this->text_domain), '#FF8126'],
            'evernote'      => [__('Evernote', $this->text_domain), '#00A82D'],
            'diaspora'      => [__('Diaspora', $this->text_domain), '#222222'],
            'hacker-news'   => [__('Hacker News', $this->text_domain), '#FF6600'],
            'email'         => [__('Email', $this->text_domain), '#6B7280'],
            'copy'          => [__('Copy', $this->text_domain), '#6B7280'],
            'native'        => [__('Share', $this->text_domain), '#6B7280'],
        ];

        return apply_filters('your_share_networks', $map);
    }

    public function follow(): array
    {
        $map = [
            'x'             => [__('X', $this->text_domain), '#000000'],
            'instagram'     => [__('Instagram', $this->text_domain), '#E1306C'],
            'facebook-page' => [__('Facebook Page', $this->text_domain), '#1877F2'],
            'tiktok'        => [__('TikTok', $this->text_domain), '#000000'],
            'youtube'       => [__('YouTube', $this->text_domain), '#FF0000'],
            'linkedin'      => [__('LinkedIn', $this->text_domain), '#0A66C2'],
        ];

        return apply_filters('your_share_follow_networks', $map);
    }
}
