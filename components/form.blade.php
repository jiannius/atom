<div class="contents relative">
    <form {{ $attributes->class(['group/form space-y-6'])->merge(['wire:submit' => 'submit']) }} data-atom-form>
        {{ $slot }}
    </form>

    <div
    wire:loading.flex
    wire:target="{{ $attributes->wire('submit')->value() }}"
    class="absolute inset-0 z-1 flex justify-end p-4 text-zinc-500 dark:text-white">
        <atom:icon.loading class="size-5" />
    </div>
</div>

