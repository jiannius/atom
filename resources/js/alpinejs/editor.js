export default (config) => {
    // Alpine's reactive engine automatically wraps component properties in proxy objects.
    // If you attempt to use a proxied editor instance to apply a transaction, it will cause a "Range Error: Applying a mismatched transaction",
    // so be sure to unwrap it using Alpine.raw(), or simply avoid storing your editor as a component property, as shown in this example.
    let tiptap

    return {
        ts: Date.now(), // force Alpine to rerender on selection change
        content: '',
        loading: true,

        init () {
            import('../tiptap.js').then(() => this.createTiptap())

            this.$watch('content', value => {
                if (!tiptap) return
                if (value === tiptap.getHTML()) return
                this.commands().setContent(value, false)
            })
        },

        createTiptap () {
            const _this = this

            tiptap = Tiptap({
                element: this.$refs.editor,
                tiptapConfig: {
                    content: this.content,
                    placeholder: config.placeholder,
                    editable: !config.readonly,
                    autofocus: config.autofocus,
                    editorProps: { attributes: { class: config.class }},
                    onCreate () { _this.loading = false; _this.ts = Date.now() },
                    onSelectionUpdate () { _this.ts = Date.now() },
                    ...(config.lazy
                        ? { onBlur: () => _this.sync() }
                        : { onUpdate: () => _this.sync() }),
                },
                bubbleMenus: {
                    linkMenu: this.$root.querySelector('.editor-menu .link-menu'),
                    imageMenu: this.$root.querySelector('.editor-menu .image-menu'),
                    tableMenu: this.$root.querySelector('.editor-menu .table-menu'),
                    youtubeMenu: this.$root.querySelector('.editor-menu .youtube-menu'),
                },
                mentionTemplate: this.$root.querySelector('.editor-mention'),
            })
        },

        editor () {
            return tiptap
        },

        sync () {
            if (!tiptap.isEditable) return
            if (tiptap.isEmpty) this.content = ''
            else this.content = tiptap.getHTML()
        },

        can () {
            return tiptap.can()
        },

        commands () {
            tiptap.chain().focus()
            return tiptap.commands
        },

        isEmpty () {
            if (typeof this.content === 'string') return empty(this.content.striptags())
            else return empty(this.content)
        },
    }
}