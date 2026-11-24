// Tiptap v3 engine. Confirmed v3 import surface (Phase 0 spike):
//   StarterKit v3 bundles Link, Underline, UndoRedo, ListKeymap, TrailingNode,
//   CodeBlock, HorizontalRule, Dropcursor, Gapcursor, history, etc.
//   FontSize is first-class in @tiptap/extension-text-style.
//   Placeholder lives in @tiptap/extensions. TableKit replaces table-* packages.
import { Editor } from '@tiptap/core'
import { Extension } from '@tiptap/core'
import StarterKit from '@tiptap/starter-kit'
import { Placeholder } from '@tiptap/extensions'
import BubbleMenu from '@tiptap/extension-bubble-menu'
import { Color } from '@tiptap/extension-color'
import Highlight from '@tiptap/extension-highlight'
import Image from '@tiptap/extension-image'
import Mention from '@tiptap/extension-mention'
import Subscript from '@tiptap/extension-subscript'
import Superscript from '@tiptap/extension-superscript'
import { TableKit } from '@tiptap/extension-table'
import TextAlign from '@tiptap/extension-text-align'
import { TextStyle, FontSize } from '@tiptap/extension-text-style'
import Youtube from '@tiptap/extension-youtube'

// Mirrors Tiptap\StyleValue (PHP): only these float / width values reach a style attribute.
const safeFloat = v => (['left', 'right', 'none'].includes(v) ? v : null)
const safeWidth = v => {
    const m = typeof v === 'string' && v.trim().match(/^(\d{1,4}(?:\.\d+)?)(px|%)$/i)
    return m && +m[1] >= 1 && +m[1] <= (m[2] === '%' ? 100 : 5000) ? v.trim() : null
}

// Mirrors Tiptap\StyleValue::color / fontSize (PHP). Stored content is untrusted (a client can save any JSON), and the stock
// Color, Highlight, TextAlign and FontSize write the stored value into `style` verbatim, so `red; background:url(//x)` or
// `left; position:fixed; inset:0` would reach the editor's DOM. Only a value that passes these reaches an attribute.
const NAMED_COLORS = new Set([
    'aliceblue', 'antiquewhite', 'aqua', 'aquamarine', 'azure', 'beige', 'bisque', 'black', 'blanchedalmond', 'blue',
    'blueviolet', 'brown', 'burlywood', 'cadetblue', 'chartreuse', 'chocolate', 'coral', 'cornflowerblue', 'cornsilk',
    'crimson', 'cyan', 'darkblue', 'darkcyan', 'darkgoldenrod', 'darkgray', 'darkgreen', 'darkgrey', 'darkkhaki',
    'darkmagenta', 'darkolivegreen', 'darkorange', 'darkorchid', 'darkred', 'darksalmon', 'darkseagreen',
    'darkslateblue', 'darkslategray', 'darkslategrey', 'darkturquoise', 'darkviolet', 'deeppink', 'deepskyblue',
    'dimgray', 'dimgrey', 'dodgerblue', 'firebrick', 'floralwhite', 'forestgreen', 'fuchsia', 'gainsboro',
    'ghostwhite', 'gold', 'goldenrod', 'gray', 'green', 'greenyellow', 'grey', 'honeydew', 'hotpink', 'indianred',
    'indigo', 'ivory', 'khaki', 'lavender', 'lavenderblush', 'lawngreen', 'lemonchiffon', 'lightblue', 'lightcoral',
    'lightcyan', 'lightgoldenrodyellow', 'lightgray', 'lightgreen', 'lightgrey', 'lightpink', 'lightsalmon',
    'lightseagreen', 'lightskyblue', 'lightslategray', 'lightslategrey', 'lightsteelblue', 'lightyellow', 'lime',
    'limegreen', 'linen', 'magenta', 'maroon', 'mediumaquamarine', 'mediumblue', 'mediumorchid', 'mediumpurple',
    'mediumseagreen', 'mediumslateblue', 'mediumspringgreen', 'mediumturquoise', 'mediumvioletred', 'midnightblue',
    'mintcream', 'mistyrose', 'moccasin', 'navajowhite', 'navy', 'oldlace', 'olive', 'olivedrab', 'orange',
    'orangered', 'orchid', 'palegoldenrod', 'palegreen', 'paleturquoise', 'palevioletred', 'papayawhip',
    'peachpuff', 'peru', 'pink', 'plum', 'powderblue', 'purple', 'rebeccapurple', 'red', 'rosybrown', 'royalblue',
    'saddlebrown', 'salmon', 'sandybrown', 'seagreen', 'seashell', 'sienna', 'silver', 'skyblue', 'slateblue',
    'slategray', 'slategrey', 'snow', 'springgreen', 'steelblue', 'tan', 'teal', 'thistle', 'tomato', 'turquoise',
    'violet', 'wheat', 'white', 'whitesmoke', 'yellow', 'yellowgreen', 'transparent', 'currentcolor',
])
const COLOR_NUM = '\\d{1,3}(?:\\.\\d+)?'
const COLOR_ALPHA = '(?:\\d*\\.)?\\d+%?'
const COLOR_HUE = '-?\\d+(?:\\.\\d+)?(?:deg|grad|rad|turn)?'
const COLOR_PATTERNS = [
    /^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i,
    new RegExp(`^rgba?\\(\\s*${COLOR_NUM}%?\\s*,\\s*${COLOR_NUM}%?\\s*,\\s*${COLOR_NUM}%?\\s*(?:,\\s*${COLOR_ALPHA}\\s*)?\\)$`, 'i'),
    new RegExp(`^rgba?\\(\\s*${COLOR_NUM}%?\\s+${COLOR_NUM}%?\\s+${COLOR_NUM}%?\\s*(?:\\/\\s*${COLOR_ALPHA}\\s*)?\\)$`, 'i'),
    new RegExp(`^hsla?\\(\\s*${COLOR_HUE}\\s*,\\s*${COLOR_NUM}%\\s*,\\s*${COLOR_NUM}%\\s*(?:,\\s*${COLOR_ALPHA}\\s*)?\\)$`, 'i'),
    new RegExp(`^hsla?\\(\\s*${COLOR_HUE}\\s+${COLOR_NUM}%\\s+${COLOR_NUM}%\\s*(?:\\/\\s*${COLOR_ALPHA}\\s*)?\\)$`, 'i'),
]
const safeColor = v => {
    if (typeof v !== 'string') return null
    const value = v.trim()
    return COLOR_PATTERNS.some(re => re.test(value)) || NAMED_COLORS.has(value.toLowerCase()) ? value : null
}
const safeFontSize = v => {
    const m = typeof v === 'string' && v.trim().match(/^(\d{1,3}(?:\.\d+)?)(px|em|rem)$/i)
    if (!m) return null
    const [min, max] = m[2].toLowerCase() === 'px' ? [1, 999] : [0.1, 20]
    return +m[1] >= min && +m[1] <= max ? v.trim() : null
}
const TEXT_ALIGNMENTS = ['left', 'center', 'right', 'justify']

const SafeColor = Color.extend({
    addGlobalAttributes () {
        const global = this.parent()
        global[0].attributes.color.renderHTML = a => (safeColor(a.color) ? { style: `color: ${safeColor(a.color)}` } : {})
        return global
    },
})

const SafeFontSize = FontSize.extend({
    addGlobalAttributes () {
        const global = this.parent()
        global[0].attributes.fontSize.renderHTML = a => (safeFontSize(a.fontSize) ? { style: `font-size: ${safeFontSize(a.fontSize)}` } : {})
        return global
    },
})

const SafeHighlight = Highlight.extend({
    addAttributes () {
        const attributes = this.parent?.() || {}
        if (attributes.color) {
            attributes.color.renderHTML = a => (safeColor(a.color)
                ? { 'data-color': safeColor(a.color), style: `background-color: ${safeColor(a.color)}; color: inherit` }
                : {})
        }
        return attributes
    },
})

const SafeTextAlign = TextAlign.extend({
    addGlobalAttributes () {
        const global = this.parent()
        global[0].attributes.textAlign.renderHTML = a => (TEXT_ALIGNMENTS.includes(a.textAlign) ? { style: `text-align: ${a.textAlign}` } : {})
        return global
    },
})

// Image with float/align/width attributes (ported from v2; .extend stable in v3)
const ImageExtended = Image.extend({
    addAttributes () {
        return {
            ...this.parent?.(),
            float: {
                default: null,
                parseHTML: el => el.getAttribute('data-float'),
                renderHTML: a => (safeFloat(a.float) ? { 'data-float': a.float, style: `float: ${a.float}` } : {}),
            },
            align: {
                default: null,
                parseHTML: el => el.getAttribute('data-align'),
                renderHTML: a => {
                    let style
                    if (a.align === 'left') style = 'margin-right: auto'
                    else if (a.align === 'center') style = 'margin-left: auto; margin-right: auto'
                    else if (a.align === 'right') style = 'margin-left: auto'
                    return style ? { 'data-align': a.align, style } : {}
                },
            },
            width: {
                default: null,
                parseHTML: el => el.getAttribute('data-width'),
                renderHTML: a => (safeWidth(a.width) ? { 'data-width': a.width.trim(), style: `width: ${a.width.trim()}` } : {}),
            },
        }
    },
})

// floating-ui based bubble menu config (v3; replaces v2 tippyOptions)
const BubbleMenuConfiguration = (element, key) => ({
    pluginKey: key,
    element,
    shouldShow: ({ editor }) => element.shouldShow(editor),
    options: { placement: 'top', offset: 8 },
})

const MentionConfiguration = (element) => ({
    HTMLAttributes: { class: 'mention' },
    renderText ({ options, node }) { return `${options.suggestion.char} ${node.attrs.label ?? node.attrs.id}` },
    suggestion: {
        render: () => ({
            onStart: props => { if (props.clientRect) element.start(props) },
            onUpdate: props => { if (props.clientRect) element.update(props) },
            onKeyDown: props => element.keydown(props),
            onExit: props => element.exit(props),
        }),
    },
})

const DisableEnterKeyExtension = Extension.create({
    addKeyboardShortcuts () {
        return {
            Enter: () => {
                if (!this.editor.isActive('listItem')) {
                    this.editor.options.element.dispatchEvent(new CustomEvent('editor-enter', { bubbles: true, detail: this.editor }))
                    return true
                }
            },
            'Shift-Enter': () => { this.editor.commands.insertContent('<p></p>'); return true },
        }
    },
})

window.Tiptap = ({ element, config, bubbleMenus = {}, disableEnterKey = false, mentionTemplate }) => {
    const extensions = [
        StarterKit.configure({ link: { openOnClick: false } }),
        SafeColor,
        SafeFontSize,
        SafeHighlight.configure({ multicolor: true }),
        ImageExtended,
        Placeholder.configure({ placeholder: config.placeholder }),
        Subscript,
        Superscript,
        TableKit.configure({ table: { resizable: true } }),
        SafeTextAlign.configure({ types: ['heading', 'paragraph'] }),
        TextStyle,
        Youtube,
        disableEnterKey ? DisableEnterKeyExtension : null,
    ].filter(Boolean)

    Object.keys(bubbleMenus || {}).forEach(key => {
        // each menu needs a UNIQUE extension name — Tiptap v3 dedupes by name, so
        // registering the shared 'bubbleMenu' name N times collapses to one and the
        // rest render inline instead of floating on node-selection
        if (bubbleMenus[key]) extensions.push(BubbleMenu.extend({ name: `bubbleMenu_${key}` }).configure(BubbleMenuConfiguration(bubbleMenus[key], key)))
    })

    if (mentionTemplate) extensions.push(Mention.configure(MentionConfiguration(mentionTemplate)))

    return new Editor({ element, autofocus: false, extensions, ...config })
}
