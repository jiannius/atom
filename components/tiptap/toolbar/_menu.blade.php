@props([
    'label' => null,
    'icon' => null,
    'active' => null,
    'items' => [],
])

{{-- A toolbar button that opens a menu of commands: `icon` is the button's glyph, `items` the rows (`label`, `icon`, and `click`, what follows `commands().`;
     a `click` with a script argument written by `js()` is an HtmlString, so it is not escaped twice). The glyphs are atom's own inline SVGs, printed as written. --}}
<atom:dropdown>
    <atom:tiptap.toolbar.button :label="$label" :active="$active">
        {!! $icon !!}
    </atom:tiptap.toolbar.button>

    <atom:menu popover>
        @foreach ($items as $item)
            <atom:menu.item x-on:click="commands().{{ data_get($item, 'click') }}">
                <div class="flex items-center gap-3">
                    {!! data_get($item, 'icon') !!}
                    <div class="grow">{{ t(data_get($item, 'label')) }}</div>
                </div>
            </atom:menu.item>
        @endforeach
    </atom:menu>
</atom:dropdown>
