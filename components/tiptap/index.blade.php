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
    'toolbar' => 'full',
])

@php
$name ??= $attributes->wire('model')->value();
$error ??= $errors?->first($name);
$model = $attributes->wire('model')->value();
$lazy = $attributes->modifier('blur');
$transparent = $variant === 'transparent';

$presets = [
    'full'    => ['heading', 'text', 'font-size', 'text-align', 'text-color', 'text-highlight', 'horizontal-rule', 'bullet', 'link', 'table', 'image', 'youtube'],
    'basic'   => ['heading', 'text', 'bullet', 'link', 'image'],
    'minimal' => ['text', 'link'],
    'none'    => [],
];
// The editable surface is a contenteditable, not a form control, so a <label for> has
// nothing to bind to: it is given a textbox role and named by pointing back at the
// field's label. The attributes have to be set through the editor's own config because
// ProseMirror creates the contenteditable itself — see resources/js/alpinejs/tiptap.js.
// Derived, not minted per render: see ComponentAttributeBag::fieldId().
$labelId = $label ? $attributes->fieldId('atom-tiptap', $name, $label).'-label' : null;

$hasToolbarSlot = $toolbar instanceof \Illuminate\View\ComponentSlot;
$buttons = $hasToolbarSlot ? [] : (is_array($toolbar) ? $toolbar : ($presets[$toolbar] ?? $presets['full']));
$menus = $hasToolbarSlot ? ['link', 'table', 'image', 'youtube'] : $buttons;
@endphp

@if ($label || $caption)
    <atom:input.field :label="$label" :caption="$caption" :required="$required" :error="$error" :label-id="$labelId">
        @if ($hasToolbarSlot)
            {{-- mention passed as an explicit prop, not merged into the bag: an array value would render as an attribute and e() would choke on it --}}
            <atom:tiptap :mention="$mention" :attributes="$attributes->merge(compact('name', 'variant', 'readonly', 'autofocus', 'placeholder') + ['aria-labelledby' => $labelId])">
                <x-slot:toolbar>{{ $toolbar }}</x-slot:toolbar>
            </atom:tiptap>
        @else
            <atom:tiptap :toolbar="$toolbar" :mention="$mention" :attributes="$attributes->merge(compact('name', 'variant', 'readonly', 'autofocus', 'placeholder') + ['aria-labelledby' => $labelId])" />
        @endif
    </atom:input.field>
@else
    <link rel="stylesheet" href="{{ app('atom')->asset()->version('tiptap.css') }}">

    <div
    wire:ignore
    x-cloak
    x-data="tiptap({
        lazy: @js($lazy),
        placeholder: @js($placeholder),
        readonly: @js($readonly),
        autofocus: @js($autofocus),
        class: @js(Arr::toCssClasses(['editor-content m-3 focus:outline-none', $attributes->get('class', 'min-h-10')])),
        labelledby: @js($attributes->get('aria-labelledby')),
    })"
    x-modelable="editorContent"
    class="group/editor"
    @if ($model && $lazy) wire:model.live="{{ $model }}" @else {{ $attributes->except(['class', 'aria-labelledby']) }} @endif>
        <div x-show="loading"><atom:skeleton /></div>

        <div x-show="!loading" @class([
            'editor relative rounded-lg',
            'shadow-sm bg-white dark:bg-white/10 border border-zinc-200 dark:border-white/10' => !$transparent,
            'has-focus:outline-1 has-focus:outline-zinc-200' => !$transparent,
            'border-0 bg-transparent' => $transparent,
        ])>
            @if (!$readonly && ($hasToolbarSlot || count($buttons)))
                <div class="sticky top-0 z-1 p-1">
                    <div role="toolbar" aria-label="{{ t('Formatting') }}" class="flex gap-1 items-center flex-wrap p-1 bg-white rounded-md dark:bg-zinc-800 border dark:border-zinc-700">
                        @if ($hasToolbarSlot)
                            {{ $toolbar }}
                        @else
                            @foreach ($buttons as $button)
                                <x-dynamic-component :component="'tiptap.toolbar.'.$button" />
                            @endforeach
                        @endif
                    </div>
                </div>
            @endif

            <div class="tiptap-menu">
                @if (in_array('link', $menus)) <atom:tiptap.menu.link/> @endif
                @if (in_array('table', $menus)) <atom:tiptap.menu.table/> @endif
                @if (in_array('image', $menus)) <atom:tiptap.menu.image/> @endif
                @if (in_array('youtube', $menus)) <atom:tiptap.menu.youtube/> @endif
            </div>

            @if ($mention)
                <atom:tiptap.mention :options="$mention" />
            @endif

            <div x-ref="editor" class="grow"></div>
        </div>
    </div>
@endif
