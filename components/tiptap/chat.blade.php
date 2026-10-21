@props([
    'label' => null,
    'caption' => null,
    'autofocus' => false,
    'mention' => null,
    'variant' => null,
    'placeholder' => 'Write something...',
])

@php
$transparent = $variant === 'transparent';

// Same as <atom:tiptap>: ProseMirror creates the contenteditable, so it takes no
// <label for> and is named through the editor config instead. Derived, not minted per
// render — see ComponentAttributeBag::fieldId().
$labelId = $label ? $attributes->fieldId('atom-tiptap-chat', $attributes->wire('model')->value(), $label).'-label' : null;
@endphp

@if ($label || $caption)
    <atom:input.field :label="$label" :caption="$caption" :label-id="$labelId">
        {{-- mention passed as an explicit prop, not merged into the bag: an array value would render as an attribute and e() would choke on it --}}
        <atom:tiptap.chat :mention="$mention" :aria-labelledby="$labelId" :attributes="$attributes->merge(compact('autofocus', 'placeholder', 'variant'))" />
    </atom:input.field>
@else
    <link rel="stylesheet" href="{{ app('atom')->asset()->version('tiptap.css') }}">

    <div
    wire:ignore
    x-cloak
    x-data="tiptap({
        chat: true,
        placeholder: @js($placeholder),
        autofocus: @js($autofocus),
        class: @js(Arr::toCssClasses(['editor-content editor-chat-content m-3 focus-visible:outline-none', $attributes->get('class')])),
        labelledby: @js($attributes->get('aria-labelledby')),
    })"
    x-modelable="editorContent"
    class="group/editor"
    {{ $attributes->except(['class', 'aria-labelledby']) }}>
        <div x-show="loading"><atom:skeleton /></div>

        <div x-show="!loading" @class([
            'editor relative rounded-control',
            'bg-surface border border-control-border' => !$transparent,
            'has-[[contenteditable]:focus-visible]:border-focus has-[[contenteditable]:focus-visible]:outline-2 has-[[contenteditable]:focus-visible]:outline-focus has-[[contenteditable]:focus-visible]:outline-offset-0' => !$transparent,
            'border-0 bg-transparent' => $transparent,
        ])>
            @if ($mention)
                <atom:tiptap.mention :options="$mention" />
            @endif

            <div class="flex items-end">
                <div x-ref="editor" x-on:input.stop="" x-on:paste="paste($event)" x-on:drop.prevent="drop($event)" class="grow"></div>

                <div class="shrink-0 p-2 flex items-center group-[.is-loading]/editor:hidden">
                    <atom:tiptap.chat.formatting />
                    <atom:tiptap.chat.upload />
                    <atom:tiptap.toolbar.button label="Submit" x-on:click="sync()">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 4v7a4 4 0 0 1-4 4H4"/><path d="m9 10-5 5 5 5"/></svg>
                    </atom:tiptap.toolbar.button>
                </div>
            </div>

            <div x-show="files.length" class="py-2 px-3 flex flex-col gap-2">
                <template x-for="(file, i) in files" hidden>
                    <div class="group flex items-center gap-2">
                        <figure class="shrink-0 size-6 bg-surface-sunken text-fg-muted rounded-nav overflow-hidden border flex items-center justify-center">
                            <img x-show="file.src" x-bind:src="file.src" class="w-full h-full object-cover">
                            <atom:icon.file x-show="!file.src" class="size-4" />
                        </figure>

                        <div x-text="file.file.name" class="grow text-xs text-fg-muted truncate"></div>

                        <button type="button" x-on:click="files.splice(i, 1)" aria-label="{{ t('Remove') }}" class="shrink-0 flex items-center justify-center rounded-nav text-fg-muted hover:text-fg">
                            <atom:icon.delete class="size-4" />
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </div>
@endif
