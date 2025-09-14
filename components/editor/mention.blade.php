@props([
    'options' => null,
])

@php
if (is_string($options)) {
    $callback = $options;
    $options = [];
}
else {
    $callback = null;
    $options ??= [];
}
@endphp

<div
x-data="{
    show: false,
    props: null,
    timer: null,
    pointer: 0,
    options: @js($options),
    callback: @js($callback),
    filteredOptions: [],

    init () {
        // assign the methods to the element so it can be called by tiptap using element.method()
        this.$el.start = (props) => this.start(props)
        this.$el.update = (props) => this.update(props)
        this.$el.exit = (props) => this.exit(props)
        this.$el.keydown = (props) => (this.keydown(props))
    },

    start (props) {
        this.props = props
        this.pointer = 0
        this.fetch()
    },

    update (props) {
        this.props = props
        this.fetch()
    },

    exit (props) {
        this.show = false
        this.props = null
        this.filteredOptions = []
    },

    keydown (props) {
        if (props.event.key === 'Escape') {
            this.exit()
            return true
        }
        else if (props.event.key === 'Enter' && this.filteredOptions.length) {
            props.event.preventDefault()
            props.event.stopPropagation()
            if (this.pointer > -1) this.select(this.filteredOptions[this.pointer])
            else this.select(this.filteredOptions[0])
            return true
        }
        else if (props.event.key === 'ArrowUp' && this.filteredOptions.length) {
            this.arrowUp()
            return true
        }
        else if (props.event.key === 'ArrowDown' && this.filteredOptions.length) {
            this.arrowDown()
            return true
        }

        return false
    },

    arrowUp () {
        this.pointer = ((this.pointer + this.filteredOptions.length) - 1) % this.filteredOptions.length
        this.scroll()
    },

    arrowDown () {
        this.pointer = (this.pointer + 1) % this.filteredOptions.length
        this.scroll()
    },

    align () {
        let editor = this.$el.closest('.editor')
        let anchor = this.props.decorationNode
        let dropdown = this.$refs.dropdown

        this.$nextTick(() => {
            let left = anchor.getBoundingClientRect().left - editor.getBoundingClientRect().left
            let top = anchor.getBoundingClientRect().top - editor.getBoundingClientRect().top - dropdown.getBoundingClientRect().height

            dropdown.style.left = `${left}px`
            dropdown.style.top = `${top}px`

            this.show = true
        })
    },

    scroll () {
        let ul = this.$refs.dropdown.querySelector('ul')
        let li = Array.from(this.$refs.dropdown.querySelectorAll('li'))[this.pointer]

        if (this.pointer === 0) ul.scrollTop = 0
        else if (this.pointer === this.filteredOptions.length - 1) ul.scrollTop = ul.scrollHeight
        else {
            let top = li.getBoundingClientRect().top - ul.getBoundingClientRect().top
            let height = li.getBoundingClientRect().height
            let ceiling = 0
            let floor = ul.getBoundingClientRect().height

            // li sinked below floor, scroll down
            if (top > floor) ul.scrollTop = ul.scrollTop + height
            // li above scroll ceiling, scroll up
            else if (top < 0) ul.scrollTop = ul.scrollTop + top
        }
    },

    fetch () {
        this.pointer = 0

        if (this.callback) {
            clearTimeout(this.timer)
            this.timer = setTimeout(() => {
                this.$wire.$call(this.callback, this.props.query)
                    .then(res => this.filteredOptions = [...res])
                    .then(() => this.align())
            }, 300)
        }
        else {
            this.filteredOptions = this.options.filter(opt => {
                let searchable = typeof opt === 'object'
                    ? opt.searchable || `${opt.label} ${opt.small} ${opt.caption}`.trim().toLowerCase()
                    : opt.toString()

                return searchable.includes(this.props.query.toLowerCase())
            })

            this.align()
        }
    },

    select (opt) {
        if (typeof opt === 'string') this.props.command({ id: opt })
        else {
            this.props.command({
                id: opt.id,
                label: opt.render || opt.label || opt.value,
            })
        }

        this.exit()
    },
}"
class="editor-mention">
    <div
    x-ref="dropdown"
    x-on:keydown.up.prevent="arrowUp()"
    x-on:keydown.down.prevent="arrowDown()"
    x-bind:class="(!show || !filteredOptions.length) && 'invisible'"
    class="absolute max-w-lg min-w-72 rounded-lg border shadow-lg z-10 bg-white dark:bg-zinc-800/50">
        <ul class="flex flex-col max-h-[300px] overflow-auto p-2">
            <template x-for="(opt, i) in filteredOptions" hidden>
                <li
                x-on:mouseover="pointer = i"
                x-on:click="select(opt)"
                x-bind:class="pointer === i && '*:bg-zinc-100 *:border-zinc-200 dark:*:bg-zinc-700 dark:*:border-transparent'"
                class="cursor-pointer">
                    <div class="rounded-md p-3 border border-transparent">
                        @if ($slot->isNotEmpty())
                            {{ $slot }}
                        @else
                            <template x-if="typeof opt === 'string'" hidden>
                                <div x-text="opt"></div>
                            </template>

                            <template x-if="typeof opt === 'object'" hidden>
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-2">
                                        <div
                                            x-text="opt.type"
                                            class="uppercase bg-zinc-100 border rounded font-medium text-zinc-500"
                                            style="font-size: 0.65rem; padding: 1px 3px;">
                                        </div>
                                        <div x-text="opt.label" class="font-medium text-sm"></div>
                                    </div>
                                    <div x-show="opt.caption" x-text="opt.caption" class="text-sm text-zinc-500"></div>
                                </div>
                            </template>
                        @endif
                    </div>
                </li>
            </template>
        </ul>
    </div>
</div>