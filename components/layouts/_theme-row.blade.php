{{-- The shells' theme row: a Light / Dark / System segmented group inside the account menu. It is not a menu.item, and the
     wrapper stops the click, so picking a theme does not close the menu (the dropdown closes on a click that reaches its
     root). window.darkmode() exists only when the page passed `dark`; the shells draw this row only then. --}}
<div
x-data="{ mode: window.localStorage.getItem('darkmode') || 'system' }"
x-on:darkmode-changed.document="mode = $event.detail"
x-on:click.stop
class="flex items-center justify-between gap-2 px-2.5 py-1"
data-atom-theme-row>
    <span class="text-sm text-fg-muted" aria-hidden="true">{{ t('Theme') }}</span>

    <div role="group" aria-label="{{ t('Theme') }}" class="flex items-center gap-0.5 rounded-control border border-border p-0.5">
        @foreach (['light' => ['sun', 'Light'], 'dark' => ['moon', 'Dark'], 'system' => ['laptop', 'System']] as $mode => [$icon, $label])
            <button
            type="button"
            x-on:click="window.darkmode('{{ $mode }}')"
            x-bind:aria-pressed="mode === '{{ $mode }}' ? 'true' : 'false'"
            aria-pressed="false"
            aria-label="{{ t($label) }}"
            class="flex size-6 items-center justify-center rounded-nav text-fg-muted hover:text-fg [&[aria-pressed=true]]:bg-fg/10 [&[aria-pressed=true]]:text-fg"
            data-atom-theme-option="{{ $mode }}">
                <x-dynamic-component :component="'atom::icon.'.$icon" class="size-3.5"/>
            </button>
        @endforeach
    </div>
</div>
