@props([
    'title' => null,
    'editor' => false,
])

<atom:layouts.sidebar :title="trim(($title ? $title.' — ' : '').'Atom Docs')" :editor="$editor" :vite="[]">
    <x-slot:brand>
        <a href="{{ route('atom.docs') }}" class="me-5 flex items-center gap-2 px-1">
            <div class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground">
                <atom:icon.blocks class="size-5"/>
            </div>
            <span class="text-lg font-medium">Atom Docs</span>
        </a>
    </x-slot:brand>

    <x-slot:nav>
        <div x-data="{ q: '' }" class="flex flex-col gap-4">
            <atom:input placeholder="Search components..." x-model="q"/>

            <atom:navlist>
                @foreach (app(\Jiannius\Atom\Services\Docs::class)->grouped() as $category => $items)
                    <atom:navlist.group :heading="$category">
                        @foreach ($items as $item)
                            <atom:navlist.item
                            :href="route('atom.docs.show', $item['name'])"
                            :x-show="'!q || '.js($item['name']).'.includes(q.toLowerCase())'">
                                {{ $item['name'] }}
                            </atom:navlist.item>
                        @endforeach
                    </atom:navlist.group>
                @endforeach
            </atom:navlist>
        </div>
    </x-slot:nav>

    {{-- The body grid pins itself to 100dvh and clamps the main row, so the docs
         content needs its own scroll region — otherwise the whole document scrolls
         and the sticky sidebar background stops short of long pages. --}}
    <div class="h-full overflow-y-auto overscroll-contain">
        <div class="max-w-3xl p-6 lg:p-8">
            {{ $slot }}
        </div>
    </div>

    {{-- docs pages render no Livewire component, so Livewire never auto-injects its
         assets — but atom.js needs Livewire's bundled Alpine (alpine:init) to start --}}
    @livewireScripts
</atom:layouts.sidebar>
