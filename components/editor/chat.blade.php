@props([
    'label' => null,
    'caption' => null,
    'autofocus' => false,
    'mention' => null,
    'variant' => null,
    'placeholder' => 'Write something...',
])

@php
$name ??= $attributes->wire('model')->value();
$transparent = $variant === 'transparent';
@endphp

@if ($label || $caption)
    <atom:input.field :label="$label" :caption="$caption">
        <atom:editor.chat :attributes="$attributes->merge(compact('autofocus', 'mention', 'placeholder'))" />
    </atom:input.field>
@else
    <link rel="stylesheet" href="{{ app('atom')->asset()->version('editor.css') }}">

    <div
    wire:ignore
    x-cloak
    x-data="editor({
        chat: true,
        transparent: @js($transparent),
        placeholder: @js($placeholder),
        autofocus: @js($autofocus),
        class: @js(Arr::toCssClasses(['editor-content editor-chat-content m-3 focus:outline-none', $attributes->get('class')])),
    })"
    x-modelable="editorContent"
    class="group/editor"
    {{ $attributes->except(['class']) }}>
        <div x-show="loading">
            <atom:skeleton />
        </div>

        <div x-show="!loading" @class([
            'editor relative rounded-lg',
            'outline-offset-1 hover:outline-1 hover:outline-zinc-100/50',
            'shadow-sm bg-white dark:bg-white/10' => !$transparent,
            'has-focus:outline-1 has-focus:outline-zinc-200 dark:has-focus:outline-2' => !$transparent,
            'border border-zinc-200 dark:border-white/10' => !$transparent,
            'border-0 bg-transparent dark:bg-transparent' => $transparent,
            'has-focus:outline-none has-focus:bg-zinc-50 dark:has-focus:bg-zinc-700/50' => $transparent,
        ])>
            @if ($mention instanceof \Illuminate\View\ComponentSlot)
                <atom:editor.mention :options="$mention->attributes->get('options', [])" :filters="$mention->attributes->get('filters', [])">
                    {{ $mention }}
                </atom:editor.mention>
            @elseif (is_string($mention))
                <atom:editor.mention :options="$mention" />
            @elseif ($mention)
                <atom:editor.mention :options="data_get($mention, 'options')" :filters="data_get($mention, 'filters')" />
            @endif

            <div class="flex items-end">
                <div x-ref="editor" x-on:input.stop="" class="grow"></div>

                <div class="shrink-0 p-2 flex items-center group-[.is-loading]/editor:hidden">
                    <atom:editor.button.chat-formatting />
                    <atom:editor.button.chat-upload />
                    <atom:editor.button label="Submit" x-on:click="sync()">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-corner-down-left-icon lucide-corner-down-left"><path d="M20 4v7a4 4 0 0 1-4 4H4"/><path d="m9 10-5 5 5 5"/></svg>
                    </atom:editor.button>
                </div>
            </div>

            <div x-show="files.length" class="py-2 px-3 flex flex-col gap-2">
                <template x-for="(file, i) in files" hidden>
                    <div class="group flex items-center gap-2">
                        <figure class="shrink-0 size-6 bg-zinc-200 rounded-md overflow-hidden border border-zinc-300 flex items-center justify-center">
                            <img x-show="file.src" x-bind:src="file.src" class="w-full h-full object-cover">
                            <atom:icon.file x-show="!file.src" class="size-4" />
                        </figure>

                        <div x-text="file.file.name" class="grow text-xs text-muted-more truncate"></div>

                        <button
                        type="button"
                        x-on:click="files.splice(i, 1)"
                        class="shrink-0 flex items-center justify-center text-muted-foreground">
                            <atom:icon.delete class="size-4" />
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </div>
@endif
