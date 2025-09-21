(function (wp) {
    if (!wp || !wp.blocks || !wp.element || !wp.blockEditor || !wp.components) {
        return;
    }

    var registerBlockType = wp.blocks.registerBlockType;
    var __ = wp.i18n.__;
    var blockEditor = wp.blockEditor;
    var InspectorControls = blockEditor.InspectorControls;
    var useBlockProps = blockEditor.useBlockProps;
    var InnerBlocks = blockEditor.InnerBlocks;
    var components = wp.components;
    var PanelBody = components.PanelBody;
    var TextControl = components.TextControl;
    var ToggleControl = components.ToggleControl;
    var SelectControl = components.SelectControl;
    var RangeControl = components.RangeControl;
    var Fragment = wp.element.Fragment;
    var el = wp.element.createElement;
    var ServerSideRender = wp.serverSideRender;

    function toNumber(value, fallback) {
        var parsed = parseInt(value, 10);
        if (isNaN(parsed)) {
            return fallback;
        }
        return parsed;
    }

    function inspectorWrapper(children) {
        return el(InspectorControls, null, children);
    }

    var LABEL_OPTIONS = [
        { label: __('Theme default', 'your-share'), value: '' },
        { label: __('Auto', 'your-share'), value: 'auto' },
        { label: __('Show', 'your-share'), value: 'show' },
        { label: __('Hide', 'your-share'), value: 'hide' }
    ];

    var STYLE_OPTIONS = [
        { label: __('Theme default', 'your-share'), value: '' },
        { label: __('Solid', 'your-share'), value: 'solid' },
        { label: __('Outline', 'your-share'), value: 'outline' },
        { label: __('Ghost', 'your-share'), value: 'ghost' }
    ];

    var SIZE_OPTIONS = [
        { label: __('Theme default', 'your-share'), value: '' },
        { label: __('Small', 'your-share'), value: 'sm' },
        { label: __('Medium', 'your-share'), value: 'md' },
        { label: __('Large', 'your-share'), value: 'lg' }
    ];

    var ALIGN_OPTIONS = [
        { label: __('Theme default', 'your-share'), value: '' },
        { label: __('Left', 'your-share'), value: 'left' },
        { label: __('Center', 'your-share'), value: 'center' },
        { label: __('Right', 'your-share'), value: 'right' },
        { label: __('Space between', 'your-share'), value: 'space-between' }
    ];

    var STICKY_POSITIONS = [
        { label: __('Theme default', 'your-share'), value: '' },
        { label: __('Left', 'your-share'), value: 'left' },
        { label: __('Right', 'your-share'), value: 'right' }
    ];

    var REACTION_PLACEMENTS = [
        { label: __('Inline', 'your-share'), value: 'inline' },
        { label: __('Sticky', 'your-share'), value: 'sticky' }
    ];

    var MEDIA_TAGS = [
        { label: __('Figure', 'your-share'), value: 'figure' },
        { label: __('Div', 'your-share'), value: 'div' },
        { label: __('Section', 'your-share'), value: 'section' }
    ];

    var networkHelp = __('Enter comma-separated network slugs to override the defaults.', 'your-share');

    function renderPreview(blockName, attributes, inspector) {
        var blockProps = useBlockProps ? useBlockProps() : {};

        return el(Fragment, null,
            inspectorWrapper(inspector),
            el('div', blockProps,
                ServerSideRender ? el(ServerSideRender, {
                    block: blockName,
                    attributes: attributes
                }) : null
            )
        );
    }

    registerBlockType('your-share/share-suite', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;

            var inspector = el(Fragment, null,
                el(PanelBody, { title: __('Share buttons', 'your-share'), initialOpen: true },
                    el(TextControl, {
                        label: __('Networks', 'your-share'),
                        help: networkHelp,
                        value: attributes.networks || '',
                        onChange: function (value) { setAttributes({ networks: value }); }
                    }),
                    el(SelectControl, {
                        label: __('Labels', 'your-share'),
                        value: attributes.labels || '',
                        options: LABEL_OPTIONS,
                        onChange: function (value) { setAttributes({ labels: value }); }
                    }),
                    el(SelectControl, {
                        label: __('Style', 'your-share'),
                        value: attributes.style || '',
                        options: STYLE_OPTIONS,
                        onChange: function (value) { setAttributes({ style: value }); }
                    }),
                    el(SelectControl, {
                        label: __('Size', 'your-share'),
                        value: attributes.size || '',
                        options: SIZE_OPTIONS,
                        onChange: function (value) { setAttributes({ size: value }); }
                    }),
                    el(SelectControl, {
                        label: __('Alignment', 'your-share'),
                        value: attributes.align || '',
                        options: ALIGN_OPTIONS,
                        onChange: function (value) { setAttributes({ align: value }); }
                    }),
                    el(ToggleControl, {
                        label: __('Use brand colours', 'your-share'),
                        checked: attributes.brand !== false,
                        onChange: function (value) { setAttributes({ brand: value }); }
                    }),
                    el(TextControl, {
                        label: __('Custom share URL', 'your-share'),
                        value: attributes.shareUrl || '',
                        onChange: function (value) { setAttributes({ shareUrl: value }); }
                    }),
                    el(TextControl, {
                        label: __('Custom title', 'your-share'),
                        value: attributes.shareTitle || '',
                        onChange: function (value) { setAttributes({ shareTitle: value }); }
                    }),
                    el(TextControl, {
                        label: __('UTM campaign override', 'your-share'),
                        value: attributes.utmCampaign || '',
                        onChange: function (value) { setAttributes({ utmCampaign: value }); }
                    })
                ),
                el(PanelBody, { title: __('Enhancements', 'your-share'), initialOpen: false },
                    el(ToggleControl, {
                        label: __('Show share buttons', 'your-share'),
                        checked: attributes.showShare !== false,
                        onChange: function (value) { setAttributes({ showShare: value }); }
                    }),
                    el(ToggleControl, {
                        label: __('Add follow buttons', 'your-share'),
                        checked: attributes.showFollow === true,
                        onChange: function (value) { setAttributes({ showFollow: value }); }
                    }),
                    el(ToggleControl, {
                        label: __('Show reactions', 'your-share'),
                        checked: attributes.showReactions === true,
                        onChange: function (value) { setAttributes({ showReactions: value }); }
                    }),
                    el(ToggleControl, {
                        label: __('Include floating toggle', 'your-share'),
                        checked: attributes.stickyToggle === true,
                        onChange: function (value) { setAttributes({ stickyToggle: value }); }
                    }),
                    el(SelectControl, {
                        label: __('Floating position', 'your-share'),
                        value: attributes.stickyPosition || '',
                        options: STICKY_POSITIONS,
                        onChange: function (value) { setAttributes({ stickyPosition: value }); }
                    }),
                    el(SelectControl, {
                        label: __('Floating labels', 'your-share'),
                        value: attributes.stickyLabels || 'hide',
                        options: LABEL_OPTIONS,
                        onChange: function (value) { setAttributes({ stickyLabels: value }); }
                    }),
                    el(RangeControl, {
                        label: __('Floating breakpoint', 'your-share'),
                        value: attributes.stickyBreakpoint || 1024,
                        onChange: function (value) { setAttributes({ stickyBreakpoint: toNumber(value, 1024) }); },
                        min: 480,
                        max: 1600,
                        step: 20
                    }),
                    el(SelectControl, {
                        label: __('Reactions placement', 'your-share'),
                        value: attributes.reactionsPlacement || 'inline',
                        options: REACTION_PLACEMENTS,
                        onChange: function (value) { setAttributes({ reactionsPlacement: value }); }
                    })
                ),
                attributes.showFollow === true ? el(PanelBody, { title: __('Follow options', 'your-share'), initialOpen: false },
                    el(TextControl, {
                        label: __('Follow networks', 'your-share'),
                        help: networkHelp,
                        value: attributes.followNetworks || '',
                        onChange: function (value) { setAttributes({ followNetworks: value }); }
                    }),
                    el(SelectControl, {
                        label: __('Follow labels', 'your-share'),
                        value: attributes.followLabels || '',
                        options: LABEL_OPTIONS,
                        onChange: function (value) { setAttributes({ followLabels: value }); }
                    }),
                    el(SelectControl, {
                        label: __('Follow alignment', 'your-share'),
                        value: attributes.followAlign || '',
                        options: ALIGN_OPTIONS,
                        onChange: function (value) { setAttributes({ followAlign: value }); }
                    })
                ) : null
            );

            return renderPreview('your-share/share-suite', attributes, inspector);
        },
        save: function () {
            return null;
        }
    });

    registerBlockType('your-share/sticky-toggle', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;

            var inspector = el(Fragment, null,
                el(PanelBody, { title: __('Sticky share bar', 'your-share'), initialOpen: true },
                    el(TextControl, {
                        label: __('Networks', 'your-share'),
                        help: networkHelp,
                        value: attributes.networks || '',
                        onChange: function (value) { setAttributes({ networks: value }); }
                    }),
                    el(SelectControl, {
                        label: __('Labels', 'your-share'),
                        value: attributes.labels || 'hide',
                        options: LABEL_OPTIONS,
                        onChange: function (value) { setAttributes({ labels: value }); }
                    }),
                    el(SelectControl, {
                        label: __('Style', 'your-share'),
                        value: attributes.style || '',
                        options: STYLE_OPTIONS,
                        onChange: function (value) { setAttributes({ style: value }); }
                    }),
                    el(SelectControl, {
                        label: __('Size', 'your-share'),
                        value: attributes.size || 'sm',
                        options: SIZE_OPTIONS,
                        onChange: function (value) { setAttributes({ size: value }); }
                    }),
                    el(ToggleControl, {
                        label: __('Use brand colours', 'your-share'),
                        checked: attributes.brand !== false,
                        onChange: function (value) { setAttributes({ brand: value }); }
                    }),
                    el(SelectControl, {
                        label: __('Floating position', 'your-share'),
                        value: attributes.stickyPosition || '',
                        options: STICKY_POSITIONS,
                        onChange: function (value) { setAttributes({ stickyPosition: value }); }
                    }),
                    el(RangeControl, {
                        label: __('Floating breakpoint', 'your-share'),
                        value: attributes.stickyBreakpoint || 1024,
                        onChange: function (value) { setAttributes({ stickyBreakpoint: toNumber(value, 1024) }); },
                        min: 480,
                        max: 1600,
                        step: 20
                    })
                )
            );

            return renderPreview('your-share/sticky-toggle', attributes, inspector);
        },
        save: function () {
            return null;
        }
    });

    registerBlockType('your-share/follow-buttons', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;

            var inspector = el(Fragment, null,
                el(PanelBody, { title: __('Follow buttons', 'your-share'), initialOpen: true },
                    el(TextControl, {
                        label: __('Networks', 'your-share'),
                        help: networkHelp,
                        value: attributes.networks || '',
                        onChange: function (value) { setAttributes({ networks: value }); }
                    }),
                    el(SelectControl, {
                        label: __('Labels', 'your-share'),
                        value: attributes.labels || '',
                        options: LABEL_OPTIONS,
                        onChange: function (value) { setAttributes({ labels: value }); }
                    }),
                    el(SelectControl, {
                        label: __('Style', 'your-share'),
                        value: attributes.style || '',
                        options: STYLE_OPTIONS,
                        onChange: function (value) { setAttributes({ style: value }); }
                    }),
                    el(SelectControl, {
                        label: __('Size', 'your-share'),
                        value: attributes.size || '',
                        options: SIZE_OPTIONS,
                        onChange: function (value) { setAttributes({ size: value }); }
                    }),
                    el(SelectControl, {
                        label: __('Alignment', 'your-share'),
                        value: attributes.align || '',
                        options: ALIGN_OPTIONS,
                        onChange: function (value) { setAttributes({ align: value }); }
                    }),
                    el(ToggleControl, {
                        label: __('Use brand colours', 'your-share'),
                        checked: attributes.brand !== false,
                        onChange: function (value) { setAttributes({ brand: value }); }
                    })
                )
            );

            return renderPreview('your-share/follow-buttons', attributes, inspector);
        },
        save: function () {
            return null;
        }
    });

    registerBlockType('your-share/reactions', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;

            var inspector = el(Fragment, null,
                el(PanelBody, { title: __('Reactions', 'your-share'), initialOpen: true },
                    el(SelectControl, {
                        label: __('Placement', 'your-share'),
                        value: attributes.placement || 'inline',
                        options: REACTION_PLACEMENTS,
                        onChange: function (value) { setAttributes({ placement: value }); }
                    }),
                    el(TextControl, {
                        label: __('Override post ID', 'your-share'),
                        help: __('Leave blank to use the current post.', 'your-share'),
                        value: attributes.postId ? String(attributes.postId) : '',
                        onChange: function (value) { setAttributes({ postId: toNumber(value, 0) }); }
                    })
                )
            );

            return renderPreview('your-share/reactions', attributes, inspector);
        },
        save: function () {
            return null;
        }
    });

    registerBlockType('your-share/media-selector', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps ? useBlockProps({ className: 'your-share-media-selector' }) : {};

            var inspector = el(Fragment, null,
                el(PanelBody, { title: __('Overlay options', 'your-share'), initialOpen: true },
                    el(SelectControl, {
                        label: __('Wrapper element', 'your-share'),
                        value: attributes.tagName || 'figure',
                        options: MEDIA_TAGS,
                        onChange: function (value) { setAttributes({ tagName: value }); }
                    }),
                    el(TextControl, {
                        label: __('Overlay label', 'your-share'),
                        help: __('Optional text announced to assistive technology.', 'your-share'),
                        value: attributes.overlayLabel || '',
                        onChange: function (value) { setAttributes({ overlayLabel: value }); }
                    })
                )
            );

            return el(Fragment, null,
                inspectorWrapper(inspector),
                el('div', blockProps,
                    el('p', { className: 'your-share-media-selector__description' }, __('Add media blocks inside to enable the share overlay for that content.', 'your-share')),
                    el(InnerBlocks)
                )
            );
        },
        save: function () {
            return null;
        }
    });
})();
