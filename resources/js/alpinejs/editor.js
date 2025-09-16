export default (config) => {
    // Alpine's reactive engine automatically wraps component properties in proxy objects.
    // If you attempt to use a proxied editor instance to apply a transaction, it will cause a "Range Error: Applying a mismatched transaction",
    // so be sure to unwrap it using Alpine.raw(), or simply avoid storing your editor as a component property, as shown in this example.
    let tiptap

    return {
        ts: Date.now(), // force Alpine to rerender on selection change
        files: [], // for chat
        loading: true,
        editorContent: '',

        init () {
            import('../tiptap.js').then(() => this.createTiptap())

            this.$watch('editorContent', value => {
                if (!tiptap) return
                if (value === tiptap.getHTML()) return
                this.commands().setContent(value, false)
            })
        },

        createTiptap () {
            const _this = this

            tiptap = Tiptap({
                element: this.$refs.editor,

                config: {
                    content: this.editorContent,
                    placeholder: config.placeholder,
                    editable: !config.readonly,
                    autofocus: config.autofocus,

                    editorProps: {
                        attributes: {
                            class: config.class
                        },

                        ...(config.chat ? {
                            // disable pasting and handle using x-on:paste
                            transformPasted () {
                                return ''
                            },
                            // disable drop and handle using x-on:drop
                            handleDrop () {
                                return true
                            },
                        } : {}),
                    },

                    onCreate ({ editor }) {
                        _this.loading = false
                        _this.ts = Date.now()

                        // for chat, only sync when press enter
                        if (config.chat && editor.options.element) {
                            editor.options.element.addEventListener('editor-enter', () => _this.sync())
                        }
                    },

                    onSelectionUpdate () {
                        _this.ts = Date.now()
                    },

                    ...(!config.chat && config.lazy ? { onBlur: () => _this.sync() } : {}),
                    ...(!config.chat && !config.lazy ? { onUpdate: () => _this.sync() } : {}),
                },

                bubbleMenus: {
                    linkMenu: this.$root.querySelector('.editor-menu .link-menu'),
                    imageMenu: this.$root.querySelector('.editor-menu .image-menu'),
                    tableMenu: this.$root.querySelector('.editor-menu .table-menu'),
                    youtubeMenu: this.$root.querySelector('.editor-menu .youtube-menu'),
                },

                mentionTemplate: this.$root.querySelector('.editor-mention'),

                ...(config.chat ? { disableEnterKey: true } : {}),
            })
        },

        editor () {
            return tiptap
        },

        can () {
            return tiptap.can()
        },

        commands () {
            tiptap.chain().focus()
            return tiptap.commands
        },

        isEmpty () {
            if (typeof this.editorContent === 'string') return empty(this.editorContent.striptags())
            else return empty(this.editorContent)
        },

        // paste files into the editor
        // only work in chat mode
        paste (e) {
            if (!this.chat) return
            let clipboard = e.clipboardData
            let files = Array.from(clipboard.items).filter(item => (item.kind === 'file')).map(item => (item.getAsFile()))
            let text = clipboard.getData('text')

            if (files.length) this.readFiles(files)
            else if (text) this.editor().chain().focus().insertContent(text).run()
        },

        // drag and drop files into the editor
        // only work in chat mode
        drop (e) {
            if (!this.chat) return
            let files = e.dataTransfer.files
            this.readFiles(files)
        },

        // read files from input
        // only work in chat mode
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
            if (tiptap.isEmpty) this.editorContent = ''
            else this.editorContent = tiptap.getHTML()

            if (config.chat) {
                this.$dispatch('input', { body: this.editorContent, files: this.files.map(file => file.file) })
                this.$nextTick(() => {
                    tiptap.commands.clearContent()
                    this.files = []
                })
            }
            else this.$dispatch('input', this.editorContent)
        },
    }
}