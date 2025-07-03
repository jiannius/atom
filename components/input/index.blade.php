@php
$type = $attributes->get('type');
$label = $attributes->get('label');
$caption = $attributes->get('caption');
$prefix = $attributes->get('prefix');
$suffix = $attributes->get('suffix');
$required = $attributes->get('required');
$name = $attributes->get('name') ?? $attributes->wire('model')->value();
$error = $attributes->get('error') ?? $errors?->first($name);
@endphp

@if ($label || $caption)
    <atom:input.field
    :label="$label"
    :caption="$caption"
    :required="$required"
    :error="$error">
        <atom:input :attributes="$attributes->except(['label', 'caption', 'error'])">
            {{ $slot }}

            @isset ($actions)
                <x-slot:actions>
                    {{ $actions }}
                </x-slot:actions>
            @endisset
        </atom:input>
    </atom:input.field>
@elseif ($prefix || $suffix)
    <atom:input.prefix :prefix="$prefix" :suffix="$suffix">
        <atom:input :attributes="$attributes->except(['prefix', 'suffix'])"/>
    </atom:input.prefix>
@elseif ($type === 'file')
    <atom:input.file :attributes="$attributes->except('type')">
        {{ $slot }}
    </atom:input.file>
@elseif ($type === 'tel')
    <atom:input.tel :required="$required" :attributes="$attributes->except(['type', 'required'])"/>
@elseif ($type === 'color')
    <atom:input.color :required="$required" :attributes="$attributes->except(['type', 'required'])">
        {{ $slot }}
    </atom:input.color>
@elseif ($type === 'email' && ($attributes->has('options') || $attributes->get('multiple')))
    <atom:input.email :requried="$required" :attributes="$attributes->except(['type', 'required'])"/>
@else
    @php
    $size = $attributes->get('size');
    $invalid = $attributes->get('invalid');
    $viewable = $attributes->get('viewable', $type === 'password');
    $copyable = $attributes->get('copyable');
    $clearable = $attributes->get('clearable');
    $placeholder = $attributes->get('placeholder');
    $transparent = $attributes->get('transparent');

    $icon = [
        'start' => $attributes->get('icon'),
        'end' => $attributes->get('icon-suffix'),
    ];

    $classes = $attributes->classes()
        ->add('w-full py-2 no-spinner rounded-lg shadow-sm outline-offset-1')
        ->add('text-zinc-700 text-primary dark:text-zinc-200')
        ->add('bg-white dark:bg-white/10')
        ->add('dark:placeholder-zinc-400')
        ->add('focus:outline-1 focus:outline-zinc-200 dark:focus:outline-2 hover:outline-1 hover:outline-zinc-100/50')
        ->add($invalid || $error ? 'border border-red-400' : 'border border-zinc-200 dark:border-white/10')
        ->add('group-has-[[data-atom-error]]/field:border-red-400')
        ->add($size === 'sm' ? 'h-8' : 'h-10')
        ->add(data_get($icon, 'start') ? 'pl-10' : 'pl-3')
        ->add(data_get($icon, 'end') ? 'pr-10' : 'pr-3')
        ;

    $attrs = $attributes
        ->class($classes)
        ->merge([
            'type' => 'text',
            'step' => $type === 'number' ? 'any' : null,
            'required' => $required,
            'name' => $name,
        ])
        ->except([
            'label', 'caption', 'size', 'icon', 'icon-suffix',
            'error', 'placeholder', 'invalid', 'transparent',
            'viewable', 'copyable', 'clearable',
        ])
        ;
    @endphp

    <div class="group/input relative w-full block" data-atom-input>
        @if (data_get($icon, 'start'))
            <div class="z-1 pointer-events-none absolute top-0 bottom-0 flex items-center justify-center text-zinc-400 pl-3 left-0">
                <x-dynamic-component :component="'atom::icon.'.data_get($icon, 'start')"/>
            </div>
        @endif

        <input placeholder="{{ t($placeholder) }}" {{ $attrs }}>

        @if ($viewable || $copyable || $clearable || data_get($icon, 'end'))
            <div class="z-1 absolute top-0 bottom-0 flex items-center justify-center text-zinc-400 pr-3 right-0">
                @if ($viewable)
                    <div
                    x-cloak
                    x-data="{ show: false }"
                    x-init="$watch('show', show => {
                        $el.closest('[data-atom-input]').querySelector('input').setAttribute('type', show ? 'text' : 'password')
                    })"
                    x-on:click.stop="show = !show"
                    class="w-full h-full flex items-center justify-center cursor-pointer">
                        <atom:icon.eye-slash x-show="show" class="size-4"/>
                        <atom:icon.eye x-show="!show" class="size-4"/>
                    </div>
                @elseif ($copyable)
                    <div
                    x-cloak
                    x-data="{ copied: false }"
                    x-tooltip="{{ js(t('copy')) }}"
                    x-on:click.stop="() => {
                        let input = $el.closest('[data-atom-input]').querySelector('input')
                        if (!input.value) return

                        $clipboard(input.value)
                            .then(() => copied = true)
                            .then(() => input.select())
                            .then(() => setTimeout(() => copied = false, 1000))
                    }"
                    class="w-full h-full flex items-center justify-center cursor-pointer">
                        <atom:icon.copy x-show="!copied" class="size-4"/>
                        <atom:icon.check x-show="copied" class="size-4"/>
                    </div>
                @elseif ($clearable)
                    <div
                    x-cloak
                    x-data="{
                        input: null,
                        show: false,
                    }"
                    x-init="() => {
                        input = $el.closest('[data-atom-input]').querySelector('input')
                        input.addEventListener('input', e => show = !empty(e.target.value))
                    }"
                    x-show="show"
                    x-on:click.stop="() => {
                        input.value = ''
                        input.dispatch('input', '')
                        $nextTick(() => input.focus())
                    }"
                    class="w-full h-full flex items-center justify-center cursor-pointer">
                        <atom:icon.close class="size-4"/>
                    </div>
                @else
                    <div class="w-full h-full flex items-center justify-center pointer-events-none">
                        <x-dynamic-component :component="'atom::icon.'.data_get($icon, 'end')" class="size-4"/>
                    </div>
                @endif
            </div>
        @elseif (isset($actions))
            <div {{ $actions->attributes->class([
                'z-1 absolute top-0 bottom-0 flex items-center justify-center text-zinc-400 pr-3 right-0',
                '[&_button]:flex [&_button]:items-center [&_button]:justify-center [&_button]:text-muted [&_button]:rounded-md  [&_button]:p-1',
                '[&_button:focus]:outline-none [&_button:focus]:bg-zinc-100',
            ]) }}>
                {{ $actions }}
            </div>
        @endif
    </div>
@endif