import onMorphed from '../helpers/morphed'

export default (config) => {
    let tiptap
    let root

    // The editor is behind `wire:ignore`, so what says whether it can be edited is two attributes on its root: `data-atom-disabled` (self or inherited; see
    // Attributes::disabledBy) and a host `readonly`. A morph carries both across (helpers/morphed.js). They are read from the DOM at event time, here, so a
    // handler never trusts a flag that a morph may not have reached yet; `off` and `ro` below mirror them only for what Alpine binds (the toolbar, the buttons).
    const isLocked = () => !root || root.hasAttribute('data-atom-disabled') || root.hasAttribute('readonly')

    // what a command does while the editor is locked: nothing. The editor's own commands still change a non-editable document (a bubble menu, a toolbar
    // menu left open), so every toolbar and menu command goes through commands() and is refused here, not hidden by CSS alone
    const inert = new Proxy({}, { get: () => () => false })

    // ProseMirror creates the contenteditable, so the textbox role and its state are given through the editor's config, and again when a morph changes the state
    const attributes = () => {
        const disabled = root?.getAttribute('data-atom-disabled')

        return {
            class: config.class,
            role: 'textbox',
            'aria-multiline': 'true',
            // a disabled editor says so, a read-only one (a disabled form's, or the host's own) says that; the editor really is a non-editable textbox
            ...(disabled === 'self' ? { 'aria-disabled': 'true' } : (isLocked() ? { 'aria-readonly': 'true' } : {})),
            ...(config.labelledby ? { 'aria-labelledby': config.labelledby } : {}),
        }
    }
    let applied = null

    const parseContent = (value) => {
        if (!value) return ''
        if (typeof value !== 'string') return value
        const trimmed = value.trim()
        if (trimmed.startsWith('{') || trimmed.startsWith('[')) {
            try { return JSON.parse(trimmed) } catch (e) { return value }
        }
        return value
    }

    return {
        ts: 0,
        off: null,
        ro: false,
        loading: true,
        editorContent: config.content ?? '',
        files: [],

        // for the bindings (the toolbar of the editor, the buttons of the composer); not reactive to the DOM, so a handler asks isLocked()
        get locked () { return !!this.off || this.ro },
        isLocked,

        init () {
            root = this.$root
            this.readState()

            this.$root._atomTiptapStopMorph?.()
            this.$root._atomTiptapStopMorph = onMorphed((el) => {
                if (el && !el.contains(root)) return
                this.readState()
                this.applyState()
            })

            import('../tiptap.js').then(() => this.createTiptap())

            this.$watch('editorContent', value => {
                if (!tiptap) return
                if (value === JSON.stringify(tiptap.getJSON())) return
                // a push shows even when the editor is locked, and is never sent back (emitUpdate: false), so it does not go through commands()
                tiptap.commands.setContent(parseContent(value), { emitUpdate: false })
            })
        },

        createTiptap () {
            const _this = this
            this.readState()
            applied = `${isLocked()}|${JSON.stringify(attributes())}`

            tiptap = Tiptap({
                element: this.$refs.editor,
                disableEnterKey: config.chat,
                config: {
                    content: parseContent(this.editorContent),
                    placeholder: config.placeholder,
                    editable: !isLocked(),
                    autofocus: isLocked() ? false : config.autofocus,
                    editorProps: {
                        // the contenteditable is not a form control, so it is named here
                        // rather than by a <label for> — see components/tiptap/index.blade.php
                        attributes: attributes(),
                        ...(config.chat ? { handlePaste: () => true, handleDrop: () => true } : {}),
                    },
                    onCreate ({ editor }) {
                        _this.loading = false
                        _this.ts++
                        if (config.chat) {
                            editor.options.element.addEventListener('editor-enter', () => _this.sync())
                        }
                    },
                    onSelectionUpdate () { _this.ts++ },
                    onTransaction () { _this.ts++ },
                    ...(config.chat
                        ? {}
                        : (config.lazy ? { onBlur: () => _this.sync() } : { onUpdate: () => _this.sync() })),
                },
                bubbleMenus: {
                    linkMenu: this.$root.querySelector('.tiptap-menu .link-menu'),
                    imageMenu: this.$root.querySelector('.tiptap-menu .image-menu'),
                    tableMenu: this.$root.querySelector('.tiptap-menu .table-menu'),
                    youtubeMenu: this.$root.querySelector('.tiptap-menu .youtube-menu'),
                },
                mentionTemplate: this.$root.querySelector('.tiptap-mention'),
            })
        },

        readState () {
            this.off = root.getAttribute('data-atom-disabled')
            this.ro = root.hasAttribute('readonly')
        },

        // follow a morph: the surface is editable again, or not. No `update` is emitted (setOptions, not setEditable's default), because a flip is not an edit
        // and the value pushed to the model would be a re-serialised copy of the one the page already holds
        applyState () {
            if (!tiptap) return

            const key = `${isLocked()}|${JSON.stringify(attributes())}`
            if (key === applied) return
            applied = key

            tiptap.setOptions({ editable: !isLocked(), editorProps: { ...tiptap.options.editorProps, attributes: attributes() } })
            if (isLocked()) {
                // a bubble menu that was showing stays up (the state did not change, so its plugin is not asked), and its commands would still change the document
                for (const key of ['linkMenu', 'imageMenu', 'tableMenu', 'youtubeMenu']) tiptap.view.dispatch(tiptap.state.tr.setMeta(key, 'hide'))
                if (tiptap.isFocused) tiptap.commands.blur()
            }
            this.ts++
        },

        destroy () {
            this.$root._atomTiptapStopMorph?.()
            delete this.$root._atomTiptapStopMorph
        },

        editor () { return tiptap },
        can () { return tiptap.can() },
        commands () {
            if (isLocked()) return inert
            tiptap.chain().focus()
            return tiptap.commands
        },
        isActive (...args) { return this.ts >= 0 && tiptap?.isActive(...args) },
        isEmpty () { return !tiptap || tiptap.isEmpty },

        paste (e) {
            if (!config.chat) return
            if (isLocked()) { e.preventDefault(); return }
            const clipboard = e.clipboardData
            const files = Array.from(clipboard.items).filter(i => i.kind === 'file').map(i => i.getAsFile())
            const text = clipboard.getData('text')
            if (files.length) this.readFiles(files)
            else if (text) this.commands().insertContent(text)
        },

        drop (e) {
            if (!config.chat || isLocked()) return
            this.readFiles(e.dataTransfer.files)
        },

        readFiles (files) {
            if (isLocked() || !files || !files.length) return
            this.files = [
                ...this.files,
                ...Array.from(files).map(file => ({
                    file,
                    src: file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
                })),
            ]
            this.$nextTick(() => this.commands().focus())
        },

        sync () {
            if (isLocked() || !tiptap.isEditable) return

            if (config.chat) {
                const body = tiptap.isEmpty ? '' : tiptap.getHTML()
                this.$dispatch('input', { body, files: this.files.map(f => f.file) })
                this.$nextTick(() => { tiptap.commands.clearContent(); this.files = [] })
                return
            }

            this.editorContent = tiptap.isEmpty ? '' : JSON.stringify(tiptap.getJSON())
            this.$dispatch('input', this.editorContent)
        },
    }
}
