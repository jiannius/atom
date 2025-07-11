@props([
    'heading' => true,
])

<div x-data="breadcrumbs({ heading: @js($heading) })" x-on:livewire:navigated.window="build()" data-atom-breadcrumbs>
    <template x-if="breadcrumbs.length === 1 && heading" hidden>
        <atom:heading size="xl" x-text="breadcrumbs[0].title" class="font-bold"></atom:heading>
    </template>

    <template x-if="breadcrumbs.length > 1" hidden>
        <ol {{ $attributes->class(['flex flex-wrap items-center gap-2 overflow-hidden']) }}>
            <template x-for="(item, i) in breadcrumbs" :key="item.key">
                <li class="shrink-0 max-w-40 lg:max-w-64">
                    <div class="flex items-center gap-2 truncate text-zinc-800">
                        <template x-if="i === 0" hidden>
                            <span class="shrink-0 size-5 text-muted">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-full h-full" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-house-icon lucide-house"><path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                            </span>
                        </template>

                        <template x-if="i !== breadcrumbs.length - 1">
                            <div class="flex items-center gap-2 truncate">
                                <a
                                x-text="item.title"
                                x-bind:href="item.url"
                                class="leading-none font-medium truncate whitespace-nowrap dark:text-zinc-300"
                                wire:navigate></a>
                                <atom:icon.right class="shrink-0 text-muted-foreground size-4"/>
                            </div>
                        </template>

                        <template x-if="i === breadcrumbs.length - 1">
                            <span x-text="item.title" class="leading-none truncate whitespace-nowrap text-muted-foreground"></span>
                        </template>
                    </div>
                </li>                
            </template>
        </ol>        
    </template>
</div>
