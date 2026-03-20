@props([
    'name' => null,
    'label' => null,
    'caption' => null,
    'required' => false,
    'error' => null,
    'readonly' => false,
    'autofocus' => false,
    'variant' => null,
    'mention' => null,
    'placeholder' => 'Write something...',
    'toolbar' => null,
])

@php
$name ??= $attributes->wire('model')->value();
$error ??= $errors?->first($name);
$transparent = $variant === 'transparent';
$model = $attributes->wire('model')->value();
$lazy = $attributes->modifier('blur');
$toolbar ??= ['heading', 'text', 'font-size', 'text-align', 'text-color', 'text-highlight', 'horizontal-rule', 'bullet', 'link', 'table', 'image', 'youtube'];
@endphp

@if ($label || $caption)
    <atom:input.field
    :label="$label"
    :caption="$caption"
    :required="$required"
    :error="$error">
        <atom:editor
        :toolbar="$toolbar"
        :attributes="$attributes->merge(compact('name', 'variant', 'readonly', 'autofocus', 'mention', 'placeholder'))" />
    </atom:input.field>
@else
    <link rel="stylesheet" href="{{ app('atom')->asset()->version('editor.css') }}">

    <div
    wire:ignore
    x-cloak
    x-data="editor({
        lazy: @js($lazy),
        transparent: @js($transparent),
        placeholder: @js($placeholder),
        readonly: @js($readonly),
        autofocus: @js($autofocus),
        class: @js(Arr::toCssClasses([
            'editor-content m-3 focus:outline-none',
            $attributes->get('class', 'min-h-10'),
        ])),
    })"
    x-modelable="editorContent"
    class="group/editor"
    @if ($model && $lazy) wire:model.live="{{ $model }}"
    @else {{ $attributes->except(['class']) }}
    @endif>
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
            @if ($toolbar)
                @if (count($toolbar) && !$readonly)
                    <div @class([
                        'sticky top-0 z-1 p-1',
                        'hidden group-has-focus/editor:block' => $transparent,
                    ])>
                        <div class="flex gap-1 items-center flex-wrap p-1 bg-white rounded-md dark:bg-zinc-800 border dark:border-zinc-700">
                            @foreach ($toolbar as $button)
                                <x-dynamic-component :component="'editor.button.'.$button"/>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="editor-menu">
                    @if (in_array('link', $toolbar)) <atom:editor.menu.link/> @endif
                    @if (in_array('table', $toolbar)) <atom:editor.menu.table/> @endif
                    @if (in_array('image', $toolbar)) <atom:editor.menu.image/> @endif
                    @if (in_array('youtube', $toolbar)) <atom:editor.menu.youtube/> @endif
                </div>
            @endif

            @if ($mention instanceof \Illuminate\View\ComponentSlot)
                <atom:editor.mention :options="$mention->attributes->get('options', [])" :filters="$mention->attributes->get('filters', [])">
                    {{ $mention }}
                </atom:editor.mention>
            @elseif (is_string($mention))
                <atom:editor.mention :options="$mention" />
            @elseif ($mention)
                <atom:editor.mention :options="data_get($mention, 'options')" :filters="data_get($mention, 'filters')" />
            @endif

            <div x-ref="editor" class="grow"></div>
        </div>
    </div>
@endif
