@props([
    'type' => 'text',
    'copyable' => false,
    'clearable' => false,
    'placeholder' => null,
    'icon' => null,
    'iconSuffix' => null,
    'invalid' => false,
    'step' => null,
])

@php
$step = $type === 'number' ? 'any' : null;
/* The field recipe, shared by every text control (input, textarea, select, date and time pickers): the control token's height,
   the 7px radius, a strong hairline on the surface, no shadow. The ring is the base :focus-visible rule, hugging the border
   (offset 0) with the border in the focus colour, so a focused field reads as a 3px accent frame. */
$classes = Arr::toCssClasses([
    'h-control w-full no-spinner rounded-control border bg-surface text-sm text-fg',
    'placeholder:text-fg-subtle',
    'disabled:cursor-not-allowed disabled:bg-surface-sunken disabled:text-fg-subtle',
    $invalid ? 'focus-visible:border-danger-fg' : 'focus-visible:border-focus',
    'focus-visible:outline-offset-0',
    $invalid ? 'border-danger-fg' : 'border-control-border',
    'group-has-[[data-atom-error]]/field:border-danger-fg group-has-[[data-atom-error]]/field:focus-visible:border-danger-fg',
    $icon ? 'pl-8' : 'pl-2.5',
    $copyable || $clearable || $iconSuffix || $type === 'password' ? 'pr-8' : 'pr-2.5',
]);
@endphp

<div class="group/input relative" data-atom-input>
    @if ($icon)
        <div class="z-1 pointer-events-none absolute top-0 bottom-0 flex items-center justify-center text-fg-subtle pl-2.5 left-0">
            <x-dynamic-component :component="'atom::icon.'.$icon" class="size-4" />
        </div>
    @endif

    <input {{ $attributes->class($classes)->merge([
        'type' => $type,
        'step' => $step,
        'placeholder' => t($placeholder),
    ]) }}>

    @if ($type === 'password')
        <div class="z-1 absolute top-0 bottom-0 flex items-center justify-center text-fg-subtle pr-1 right-0">
            <button
            type="button"
            x-data="{ show: false }"
            x-init="$watch('show', show => {
                $el.closest('[data-atom-input]').querySelector('input').setAttribute('type', show ? 'text' : 'password')
            })"
            x-on:click.stop="show = !show"
            x-bind:aria-label="show ? '{{ t('Hide password') }}' : '{{ t('Show password') }}'"
            x-bind:aria-pressed="show ? 'true' : 'false'"
            class="size-6 flex items-center justify-center rounded-nav cursor-pointer hover:text-fg">
                <atom:icon.eye-slash x-show="show" class="size-4"/>
                <atom:icon.eye x-show="!show" class="size-4"/>
            </button>
        </div>
    @elseif ($copyable || $clearable || $iconSuffix)
        <div class="z-1 absolute top-0 bottom-0 flex items-center justify-center text-fg-subtle pr-1 right-0">
            @if ($copyable)
                <atom:tooltip content="Copy to clipboard">
                    <atom:copy x-bind:data-copy-value="$el.closest('[data-atom-input]').querySelector('input').value">
                        <div class="size-6 flex items-center justify-center rounded-nav cursor-pointer hover:text-fg">
                            <atom:icon.copy class="size-4" />
                        </div>
                    </atom:copy>
                </atom:tooltip>
            @elseif ($clearable)
                <button
                type="button"
                x-cloak
                x-data="{
                    input: null,
                    show: false,
                }"
                x-init="() => {
                    input = $el.closest('[data-atom-input]').querySelector('input')
                    input.addEventListener('input', e => show = !atom.empty(e.target.value))
                }"
                x-show="show"
                x-on:click.stop="() => {
                    show = false
                    input.value = ''
                    $dispatch('input', '')
                    $nextTick(() => input.focus())
                }"
                aria-label="{{ t('Clear') }}"
                class="size-6 flex items-center justify-center rounded-nav cursor-pointer hover:text-fg">
                    <atom:icon.close class="size-4"/>
                </button>
            @elseif ($iconSuffix)
                <div class="size-6 mr-1 flex items-center justify-center pointer-events-none">
                    <x-dynamic-component :component="'atom::icon.'.$iconSuffix" class="size-4"/>
                </div>
            @endif
        </div>
    @elseif (isset($actions) && $actions->isNotEmpty())
        <div {{ $actions->attributes->class([
            'z-1 absolute top-0 bottom-0 flex items-center justify-center text-fg-subtle pr-1.5 right-0',
            '[&_button]:flex [&_button]:items-center [&_button]:justify-center [&_button]:text-fg-muted [&_button]:rounded-nav [&_button]:p-1 [&_button]:hover:bg-fg/5',
        ]) }}>
            {{ $actions }}
        </div>
    @endif
</div>
