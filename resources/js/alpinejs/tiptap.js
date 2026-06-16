export default (config) => {
    let tiptap

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
        loading: true,
        editorContent: config.content ?? '',
        files: [],

        init () {
            import('../tiptap.js').then(() => this.createTiptap())

            this.$watch('editorContent', value => {
                if (!tiptap) return
                if (value === JSON.stringify(tiptap.getJSON())) return
                this.commands().setContent(parseContent(value), { emitUpdate: false })
            })
        },

        createTiptap () {
            const _this = this

            tiptap = Tiptap({
                element: this.$refs.editor,
                disableEnterKey: config.chat,
                config: {
                    content: parseContent(this.editorContent),
                    placeholder: config.placeholder,
                    editable: !config.readonly,
                    autofocus: config.autofocus,
                    editorProps: {
                        attributes: { class: config.class },
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

        editor () { return tiptap },
        can () { return tiptap.can() },
        commands () { tiptap.chain().focus(); return tiptap.commands },
        isActive (...args) { return this.ts >= 0 && tiptap?.isActive(...args) },
        isEmpty () { return !tiptap || tiptap.isEmpty },

        paste (e) {
            if (!config.chat) return
            const clipboard = e.clipboardData
            const files = Array.from(clipboard.items).filter(i => i.kind === 'file').map(i => i.getAsFile())
            const text = clipboard.getData('text')
            if (files.length) this.readFiles(files)
            else if (text) this.commands().insertContent(text)
        },

        drop (e) {
            if (!config.chat) return
            this.readFiles(e.dataTransfer.files)
        },

        readFiles (files) {
            if (!files || !files.length) return
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
            if (!tiptap.isEditable) return

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
