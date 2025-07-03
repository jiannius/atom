@php
$icon = $attributes->get('icon');
$align = $attributes->get('align');

$classes = $attributes->classes()
    ->add('flex items-center gap-2 select-none font-medium leading-6 text-zinc-800 dark:text-white')
    ->add(match ($align) {
        'right' => 'justify-end',
        'center' => 'justify-center',
        default => 'justify-start',
    })
    ;

$attrs = $attributes
    ->class($classes)
    ->except(['icon', 'align'])
    ;
@endphp

@isset ($actions)
    <div class="group flex items-center gap-2 justify-between">
        <atom:label :attributes="$attributes">
            {{ $slot }}
        </atom:label>

        @if ($actions->isEmpty())
            <div class="shrink-0 {{ $actions->attributes->get('visible') ? '' : 'hidden group-hover:block' }}">
                <div
                @if ($actions->attributes->has('tooltip'))
                x-tooltip="{{ js(t($actions->attributes->get('tooltip'))) }}"
                @endif
                class="w-5 h-5 flex items-center justify-center text-muted-more cursor-pointer"
                    {{ $actions->attributes->except(['tooltip', 'icon']) }}>
                    <x-dynamic-component :component="'atom::icon.'.$actions->attributes->get('icon')" size="13"/>
                </div>
            </div>
        @else
            <div class="shrink-0">
                {{ $actions }}
            </div>
        @endif
    </div>
@else
    <label {{ $attrs }} data-atom-label>
        @if ($icon)
            <x-dynamic-component :component="'atom::icon.'.$icon" class="shrink-0"/>
        @endif

        {{ $slot }}
    </label>
@endisset
