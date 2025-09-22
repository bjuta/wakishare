# Your Share Plugin

Modern social sharing plugin featuring configurable layouts, floating share bars, follow buttons, and per-network UTM tagging.

## Blocks

### Share Suite

The **Share Suite** block combines inline share buttons with optional follow links, reactions, and a floating toggle in a single layout. Use the inspector to:

- Override networks, sizing, labels, colours, and UTM metadata.
- Toggle follow buttons, emoji reactions, and the sticky share bar on/off per block.
- Configure follow alignment and networks without leaving the editor.

### Sticky Share Toggle

Outputs the floating share bar with the configured theme defaults. Adjust its position, breakpoint, and button styling directly from the block inspector.

### Follow Buttons

Displays the profile links saved in **Settings → Your Share → Follow**. The block exposes controls for network order, alignment, labels, and appearance.

### Reactions

Renders the emoji reaction bar either inline or in floating mode. You can optionally target a specific post ID when embedding the block outside of the main loop.

### Share Overlay

Wrap any media block in the **Share Overlay** wrapper to mark it for share overlays. The block adds a `data-your-share-media="1"` attribute so the share script can attach pop-up menus without writing CSS selectors.

## Shortcodes

### Inline share buttons (`[your_share]`)

The `[your_share]` shortcode (alias `[waki_share]`) mirrors the Share Suite share controls and accepts:

| Attribute | Values | Description |
| --- | --- | --- |
| `networks` | comma-separated slugs | Override the network order. |
| `style` | `solid`, `outline`, `ghost` | Override button style. |
| `size` | `sm`, `md`, `lg` | Override button size. |
| `labels` | `auto`, `show`, `hide` | Control label visibility. |
| `align` | `left`, `center`, `right`, `space-between` | Control alignment. |
| `brand` | `1` or `0` | Enable/disable brand colours. |
| `utm_campaign` | string | Override the default campaign value. |
| `url` | URL | Share a specific URL. |
| `title` | string | Override the share title. |

### Share suite (`[share_suite]`)

Combines share, follow, reactions, and the floating toggle. Attributes include:

| Attribute | Values | Description |
| --- | --- | --- |
| `networks` | comma-separated slugs | Share button networks. |
| `show_follow` | `1` or `0` | Include follow buttons. |
| `show_reactions` | `1` or `0` | Include emoji reactions. |
| `sticky_toggle` | `1` or `0` | Add the floating share bar. |
| `follow_networks` | comma-separated slugs | Override follow networks. |
| `follow_labels` | `show`, `hide`, `auto` | Follow label visibility. |
| `follow_align` | `left`, `center`, `right`, `space-between` | Follow alignment. |
| `reactions_placement` | `inline`, `sticky` | Reaction placement. |
| `sticky_position` | `left`, `right` | Floating share position. |
| `sticky_breakpoint` | integer | Minimum viewport for floating share. |
| Other share attributes | Same as `[your_share]` | Styling, labels, UTM overrides. |

Aliases: `[waki_share_suite]` and `[waki_share]`.

### Follow buttons (`[share_follow]`)

Outputs profile buttons (alias `[waki_follow]`):

| Attribute | Values | Description |
| --- | --- | --- |
| `networks` | comma-separated slugs | Override button order (slugs: `x`, `instagram`, `facebook-page`, `tiktok`, `youtube`, `linkedin`). |
| `style` | `solid`, `outline`, `ghost` | Button style. |
| `size` | `sm`, `md`, `lg` | Button size. |
| `align` | `left`, `center`, `right`, `space-between` | Control alignment. |
| `brand` | `1` or `0` | Enable/disable brand colours. |
| `labels` | `show`, `hide`, `auto` | Label visibility (default `show`). |

Profiles without URLs are skipped automatically. Links open in a new tab with `rel="me noopener"` and do not trigger share popups.

### Reactions (`[share_reactions]`)

Embed the emoji reaction bar anywhere (alias `[waki_reactions]`). Attributes:

| Attribute | Values | Description |
| --- | --- | --- |
| `placement` | `inline`, `sticky` | Where to render the bar. |
| `post_id` | integer | Optional post ID override. |

## Admin settings

The plugin adds a **Follow** tab under *Settings → Your Share* with:

- Profile URL fields for each supported network.
- Drag-and-drop ordering for the default follow button order.
- A live shortcode preview for `[share_follow]`.

Configure the existing Share, Sticky Bar, Smart Share, and Analytics tabs to control the behaviour of share buttons. The **Share** tab now includes automatic inline placement controls so you can enable buttons for specific post types, choose whether they appear before or after the content, and pick a dedicated set of inline networks independent of the shortcode defaults. Editors can override those defaults, toggle share counts, and adjust button corners from the Inline Share Buttons panel that appears in the post sidebar.

Fine-tune share count presentation from the **Share Counts** tab by adjusting badge rounding alongside the existing badge and total toggles.
