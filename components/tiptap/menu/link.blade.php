<div
x-data="{
    link: null,
    isEditing: false,

    init () {
        this.$el.shouldShow = (editor) => this.shouldShow(editor)
    },

    shouldShow (editor) {
        if (editor?.isActive('link')) this.getLink(editor)
        return editor?.isActive('link')
    },

    getLink (editor) {
        this.$nextTick(() => {
            editor.chain().focus().extendMarkRange('link').run()

            this.link = {
                href: editor.getAttributes('link').href,
                target: editor.getAttributes('link').target,
            }
        })
    },

    edit () {
        this.href = this.link?.href
        this.newtab = this.link?.target === '_blank'
        this.isEditing = true
    },

    remove () {
        editor().chain().focus().extendMarkRange('link').unsetLink().run()
        this.link = null
    },

    save () {
        if (!this.href.startsWith('https://') && !this.href.startsWith('http://')) {
            this.$dispatch('alert', {
                message: {{ js(t('Invalid URL')) }},
                type: 'error'
            })
        }
        else {
            let target = this.newtab ? '_blank' : '_self'
            let link = { href: this.href, target }

            editor().chain().focus().extendMarkRange('link').setLink(link).run()

            this.link = link
            this.isEditing = false
        }
    },
}"
x-on:link-menu-edit="link = null; edit()"
class="link-menu bg-white dark:bg-zinc-700 rounded-lg shadow-sm border dark:border-zinc-600">
    <template x-if="link && !isEditing">
        <div class="py-2 px-3 flex items-center gap-4 cursor-pointer max-w-xl">
            <div class="grow flex items-center gap-2">
                <div class="shrink-0 text-muted-foreground">
                    <atom:icon.link />
                </div>
                <div class="grow grid">
                    <div x-text="link.href" class="font-medium truncate text-blue-500"></div>
                </div>
            </div>
            <div class="shrink-0 text-muted" x-on:click="edit()">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="currentColor"><path d="M15.7279 9.57627L14.3137 8.16206L5 17.4758V18.89H6.41421L15.7279 9.57627ZM17.1421 8.16206L18.5563 6.74785L17.1421 5.33363L15.7279 6.74785L17.1421 8.16206ZM7.24264 20.89H3V16.6473L16.435 3.21231C16.8256 2.82179 17.4587 2.82179 17.8492 3.21231L20.6777 6.04074C21.0682 6.43126 21.0682 7.06443 20.6777 7.45495L7.24264 20.89Z"></path></svg>
            </div>
            <div class="shrink-0 text-muted" x-on:click="remove()">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="currentColor"><path d="M17 6H22V8H20V21C20 21.5523 19.5523 22 19 22H5C4.44772 22 4 21.5523 4 21V8H2V6H7V3C7 2.44772 7.44772 2 8 2H16C16.5523 2 17 2.44772 17 3V6ZM18 8H6V20H18V8ZM9 11H11V17H9V11ZM13 11H15V17H13V11ZM9 4V6H15V4H9Z"></path></svg>
            </div>
        </div>
    </template>

    <template x-if="isEditing">
        <div x-on:keydown.enter.prevent="save()" class="p-4 flex flex-col gap-2 w-80">
            <atom:input x-model="href" placeholder="Link URL">
                <x-slot:actions>
                    <button type="button" x-on:click="save()">
                        <atom:icon.check />
                    </button>
                </x-slot:actions>
            </atom:input>
            <atom:checkbox x-model="newtab">{{ t('Open in new tab') }}</atom:checkbox>
        </div>
    </template>
</div>
