# Your Share Plugin

Modern social sharing plugin featuring configurable layouts, floating share bars, follow buttons, and per-network UTM tagging.

## Shortcodes

### Share buttons

Use the `[your_share]` shortcode (alias `[waki_share]`) to output the configured share buttons. Attributes include:

| Attribute | Values | Description |
| --- | --- | --- |
| `networks` | comma-separated slugs | Override the network order. |
| `style` | `solid`, `outline`, `ghost` | Override button style. |
| `size` | `sm`, `md`, `lg` | Override button size. |
| `labels` | `auto`, `show`, `hide` | Control label visibility. |
| `brand` | `1` or `0` | Enable/disable brand colours. |
| `utm_campaign` | string | Override the default campaign value. |
| `url` | URL | Share a specific URL. |
| `title` | string | Override the share title. |

### Follow buttons

Use the `[share_follow]` shortcode (alias `[waki_follow]`) to output follow buttons that link directly to your profiles. Supported networks are X, Instagram, Facebook Page, TikTok, YouTube, and LinkedIn.

The shortcode accepts the following attributes:

| Attribute | Values | Description |
| --- | --- | --- |
| `networks` | comma-separated slugs | Override button order (slugs: `x`, `instagram`, `facebook-page`, `tiktok`, `youtube`, `linkedin`). |
| `style` | `solid`, `outline`, `ghost` | Button style. |
| `size` | `sm`, `md`, `lg` | Button size. |
| `align` | `left`, `center`, `right`, `space-between` | Control alignment. |
| `brand` | `1` or `0` | Enable/disable brand colours. |
| `labels` | `show`, `hide`, `auto` | Label visibility (default `show`). |

Profiles without URLs are skipped automatically. Links open in a new tab with `rel="me noopener"` and do not trigger share popups.

## Admin settings

The plugin adds a **Follow** tab under *Settings → Your Share* with:

- Profile URL fields for each supported network.
- Drag-and-drop ordering for the default follow button order.
- A live shortcode preview for `[share_follow]`.

Configure the existing Share, Sticky Bar, Smart Share, and Analytics tabs to control the behaviour of share buttons.
