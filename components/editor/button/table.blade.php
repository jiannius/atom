<atom:dropdown>
    <atom:editor.button label="Table">
        <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="currentColor"><path d="M3 3C2.44772 3 2 3.44772 2 4V20C2 20.5523 2.44772 21 3 21H21C21.5523 21 22 20.5523 22 20V4C22 3.44772 21.5523 3 21 3H3ZM8 5V8H4V5H8ZM4 14V10H8V14H4ZM4 16H8V19H4V16ZM10 16H20V19H10V16ZM20 14H10V10H20V14ZM20 5V8H10V5H20Z"></path></svg>
    </atom:editor.button>

    <atom:menu popover>
        <div x-data="{ rows: null, cols: null }" class="flex flex-col gap-2 p-3 w-max">
            <div class="grid grid-cols-10 gap-1">
                @foreach ([1,2,3,4,5,6,7,8,9,10] as $row)
                    @foreach ([1,2,3,4,5,6,7,8,9,10] as $col)
                        <div
                        x-on:mouseover="rows = @js($row); cols = @js($col)"
                        x-on:click="commands().insertTable({ rows, cols }); close()"
                        x-bind:class="rows >= @js($row) && cols >= @js($col)
                            ? 'bg-blue-500 border-blue-300'
                            : 'border-zinc-300'"
                        class="size-5 border rounded"></div>
                    @endforeach
                @endforeach
            </div>

            <div x-show="rows && cols" class="flex items-center justify-center gap-2">
                <span x-text="rows"></span><span>x</span><span x-text="cols"></span>
            </div>
        </div>
    </atom:menu>
</atom:dropdown>
