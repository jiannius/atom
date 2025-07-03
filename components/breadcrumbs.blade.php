@props([
    'heading' => true,
])

<ol
x-data="{
    items: [],

    build ({ home = null, items = [], create = false }) {
        if (create || !this.items.length) this.items = [].concat([home]).concat(items).filter(Boolean)
        else if (home && !items.length) this.items = [home]
        else if (!home && !items.length) this.items = []
        else if (items.length) {
            items.forEach(item => {
                let index = this.items.findIndex(value => value.title === item.title && value.url === item.url)

                if (index === -1) this.items.push(item)
                else {
                    for (let i = index + 1; i < this.items.length; i++) {
                        this.items.splice(i, 1)
                    }
                }
            })
        }
    },
}"
@if (!$heading)
x-show="items.length > 1"
@endif
x-on:atom-breadcrumbs.window="build($event.detail)"
{{ $attributes->class(['flex flex-wrap items-center gap-2 overflow-hidden']) }}
data-atom-breadcrumb>
    <template x-for="(item, i) in items" hidden>
        <li class="shrink-0 max-w-40 lg:max-w-64">
            <div
            x-bind:class="items.length === 1 ? 'text-xl font-medium text-zinc-900 dark:text-white' : 'text-zinc-800'"
            class="flex items-center gap-2 truncate">
                <atom:icon.home
                x-show="items.length > 1 && i === 0"
                class="text-muted-foreground shrink-0 size-5"/>

                <template x-if="i !== items.length - 1" hidden>
                    <div class="flex items-center gap-2 truncate">
                        <a
                        x-text="item.title"
                        x-bind:href="item.url"
                        wire:navigate
                        class="font-medium truncate whitespace-nowrap dark:text-zinc-300"></a>
                        <atom:icon.right class="shrink-0 text-muted-foreground size-4"/>
                    </div>
                </template>

                <template x-if="i === items.length - 1" hidden>
                    <span
                    x-text="item.title"
                    x-bind:class="items.length > 1 && 'text-muted-foreground'"
                    class="truncate whitespace-nowrap"></span>
                </template>
            </div>
        </li>
    </template>
</ol>
