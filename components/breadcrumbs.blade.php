@props([
    'heading' => true,
])

<div
x-data="breadcrumbs()"
x-on:navigate-back.window="back()"
x-on:livewire:navigated.window="build()"
x-on:livewire:navigate.window="getLatestHref($event)"
data-atom-breadcrumbs>
    @if ($heading)
        <template x-if="breadcrumbs.length === 1" hidden>
            <atom:heading size="xl" x-text="breadcrumbs[0].title" class="font-bold"></atom:heading>
        </template>
    @endif

    <template x-if="breadcrumbs.length > 1" hidden>
        <ol {{ $attributes->class(['flex flex-wrap items-center gap-1.5 text-sm overflow-hidden']) }}>
            <template x-for="(item, i) in breadcrumbs" x-bind:key="`${item.title}-${item.url}-${i}`">
                <li class="shrink-0 max-w-40 lg:max-w-64">
                    <div class="flex items-center gap-1.5 truncate">
                        <template x-if="i !== breadcrumbs.length - 1">
                            <div class="flex items-center gap-1.5 truncate">
                                <a
                                x-text="item.title"
                                x-bind:href="atom.safeUrl(item.href)"
                                class="leading-none font-medium truncate whitespace-nowrap rounded-nav text-fg-muted transition-colors hover:text-fg"
                                wire:navigate></a>
                                <atom:icon.right class="shrink-0 text-fg-subtle size-4"/>
                            </div>
                        </template>

                        <template x-if="i === breadcrumbs.length - 1">
                            <span x-text="item.title" class="leading-none font-medium truncate whitespace-nowrap text-fg" aria-current="page"></span>
                        </template>
                    </div>
                </li>                
            </template>
        </ol>        
    </template>
</div>
