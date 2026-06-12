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
$classes = Arr::toCssClasses([
    'h-10 w-full py-2 no-spinner rounded-lg shadow-xs outline-offset-1',
    'text-zinc-700 dark:text-zinc-200',
    'bg-white dark:bg-white/10',
    'disabled:bg-zinc-100 disabled:opacity-70 disabled:text-zinc-400 dark:disabled:bg-zinc-800/50 hover:disabled:outline-none',
    'dark:placeholder-zinc-400',
    'focus:outline-1 focus:outline-zinc-200 dark:focus:outline-2 hover:outline-1 hover:outline-zinc-100/50',
    $invalid ? 'border border-red-400' : 'border border-zinc-200 dark:border-white/10',
    'group-has-[[data-atom-error]]/field:border group-has-[[data-atom-error]]/field:border-red-400',
    $icon ? 'pl-10' : 'pl-3',
    $copyable || $clearable || $iconSuffix ? 'pr-10' : 'pr-3',
]);
@endphp

<div class="group/input relative" data-atom-input>
    @if ($icon)
        <div class="z-1 pointer-events-none absolute top-0 bottom-0 flex items-center justify-center text-zinc-400 pl-3 left-0">
            <x-dynamic-component :component="'atom::icon.'.$icon" class="size-5" />
        </div>
    @endif

    <input {{ $attributes->class($classes)->merge([
        'type' => $type,
        'step' => $step,
        'placeholder' => t($placeholder),
    ]) }}>

    @if ($type === 'password')
        <div class="z-1 absolute top-0 bottom-0 flex items-center justify-center text-zinc-400 pr-3 right-0">
            <button
            type="button"
            x-data="{ show: false }"
            x-init="$watch('show', show => {
                $el.closest('[data-atom-input]').querySelector('input').setAttribute('type', show ? 'text' : 'password')
            })"
            x-on:click.stop="show = !show"
            x-bind:aria-label="show ? '{{ t('Hide password') }}' : '{{ t('Show password') }}'"
            x-bind:aria-pressed="show ? 'true' : 'false'"
            class="w-full h-full flex items-center justify-center cursor-pointer">
                <atom:icon.eye-slash x-show="show" class="size-4"/>
                <atom:icon.eye x-show="!show" class="size-4"/>
            </button>
        </div>
    @elseif ($copyable || $clearable || $iconSuffix)
        <div class="z-1 absolute top-0 bottom-0 flex items-center justify-center text-zinc-400 pr-3 right-0">
            @if ($copyable)
                <atom:tooltip content="Copy to clipboard">
                    <atom:copy x-bind:data-copy-value="$el.closest('[data-atom-input]').querySelector('input').value">
                        <div class="w-full h-full flex items-center justify-center cursor-pointer">
                            <atom:icon.copy />
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
                    input.addEventListener('input', e => show = !empty(e.target.value))
                }"
                x-show="show"
                x-on:click.stop="() => {
                    show = false
                    input.value = ''
                    $dispatch('input', '')
                    $nextTick(() => input.focus())
                }"
                aria-label="{{ t('Clear') }}"
                class="w-full h-full flex items-center justify-center cursor-pointer">
                    <atom:icon.close class="size-4"/>
                </button>
            @elseif ($iconSuffix)
                <div class="w-full h-full flex items-center justify-center pointer-events-none">
                    <x-dynamic-component :component="'atom::icon.'.$iconSuffix" class="size-4"/>
                </div>
            @endif
        </div>
    @elseif (isset($actions) && $actions->isNotEmpty())
        <div {{ $actions->attributes->class([
            'z-1 absolute top-0 bottom-0 flex items-center justify-center text-zinc-400 pr-3 right-0',
            '[&_button]:flex [&_button]:items-center [&_button]:justify-center [&_button]:text-muted dark:[&_button]:text-muted-foreground [&_button]:rounded-md  [&_button]:p-1',
            '[&_button:focus]:outline-none [&_button:focus]:bg-zinc-100 dark:[&_button:focus]:bg-zinc-800',
        ]) }}>
            {{ $actions }}
        </div>
    @endif
</div>
