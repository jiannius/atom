<div
x-data="{
    uploading: false,
    progress: 0,
    read (files) {
        this.uploading = true
        this.$wire.uploadMultiple('_editor.images', Array.from(files),
            () => this.finish(true),
            () => this.finish('error'),
            (e) => this.progress = e.detail.progress,
            () => this.finish(),
        )
    },
    finish (response) {
        if (response === 'error') atom.alert({ heading: 'Error', message: 'Failed to upload images', variant: 'danger' })
        else if (response) this.$wire.get('_editor.images').forEach(url => commands().setImage({ src: url }))
        this.uploading = false
        this.progress = 0
    },
}">
    <input type="file" x-ref="fileinput" x-on:change="read($event.target.files)" class="hidden" accept="image/*" multiple>

    <atom:tiptap.toolbar.button label="Image" active="isActive('image')" x-show="!uploading" x-on:click="$refs.fileinput.click()">
        <atom:icon.image class="size-5" />
    </atom:tiptap.toolbar.button>

    <atom:tiptap.toolbar.button label="Uploading" x-show="uploading" class="opacity-50 pointer-events-none gap-1">
        <atom:icon.loading class="size-4 shrink-0" /> <span x-show="progress > 0 && progress < 100" x-text="`${progress}%`" class="text-xs"></span>
    </atom:tiptap.toolbar.button>
</div>
