@props([
    'multiple' => true,
    'accept' => '*',
])

<div>
    <input
    type="file"
    x-ref="fileInput"
    x-on:click.stop=""
    x-on:input.stop=""
    x-on:change="readFiles($event.target.files)"
    accept="{{ $accept }}"
    @if ($multiple) multiple @endif
    class="hidden">

    <atom:tiptap.toolbar.button label="Attach" x-on:click="$refs.fileInput.click()">
        <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-paperclip-icon lucide-paperclip"><path d="m16 6-8.414 8.586a2 2 0 0 0 2.829 2.829l8.414-8.586a4 4 0 1 0-5.657-5.657l-8.379 8.551a6 6 0 1 0 8.485 8.485l8.379-8.551"/></svg>
    </atom:tiptap.toolbar.button>
</div>
