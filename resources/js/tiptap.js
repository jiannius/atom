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

// Image with float/align/width attributes (ported from v2; .extend stable in v3)
const ImageExtended = Image.extend({
    addAttributes () {
        return {
            ...this.parent?.(),
            float: {
                default: null,
                parseHTML: el => el.getAttribute('data-float'),
                renderHTML: a => (a.float ? { 'data-float': a.float, style: `float: ${a.float}` } : {}),
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
                renderHTML: a => (a.width ? { 'data-width': a.width, style: `width: ${a.width}` } : {}),
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
        Color,
        FontSize,
        Highlight.configure({ multicolor: true }),
        ImageExtended,
        Placeholder.configure({ placeholder: config.placeholder }),
        Subscript,
        Superscript,
        TableKit.configure({ table: { resizable: true } }),
        TextAlign.configure({ types: ['heading', 'paragraph'] }),
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
