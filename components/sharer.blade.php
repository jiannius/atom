@props([
    // refer https://ellisonleao.github.io/sharer.js for available sites
    'sites' => [
        'facebook',
        'twitter-x',
        'linkedin',
        'whatsapp',
        'telegram',
        'email',
    ],
    'url' => null,
    'title' => null,
])

<div>
    <div class="text-sm text-zinc-400 font-medium mb-2">
        {{ t('Share to') }}
    </div>

    <div x-data x-init="Sharer.init()" class="flex items-center gap-2 flex-wrap">
        @foreach ($sites as $site)
            <atom:tooltip :content="str($site)->headline()->toString()">
                <button
                type="button"
                data-sharer="{{ $site }}"
                data-url="{!! $url !!}"
                data-title="{!! $title !!}"
                aria-label="{{ str($site)->headline()->toString() }}"
                class="size-10 rounded flex text-2xl cursor-pointer hover:bg-slate-100 hover:border">
                    <x-dynamic-component :component="'atom::icon.'.$site" size="24" @class([
                        'm-auto',
                        match ($site) {
                            'facebook' => 'text-blue-500',
                            'twitter-x' => 'text-black',
                            'linkedin' => 'text-blue-400',
                            'whatsapp' => 'text-green-500',
                            'telegram' => 'text-blue-500',
                            default => 'text-zinc-800',
                        }
                    ]) />
                </button>
            </atom:tooltip>
        @endforeach

        <atom:tooltip :content="t('Copy Link')">
            <button
            type="button"
            x-on:click.stop="$clipboard({{ js($url) }})"
            aria-label="{{ t('Copy Link') }}"
            class="size-10 rounded flex text-lg cursor-pointer hover:bg-slate-100 hover:border">
                <atom:icon.link size="24" class="m-auto" />
            </button>
        </atom:tooltip>
    </div>
</div>

