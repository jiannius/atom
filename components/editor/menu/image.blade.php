<div class="image-menu bg-white dark:bg-zinc-700 rounded-lg shadow-sm border dark:border-zinc-600" x-init="$el.shouldShow = (editor) => (editor?.isActive('image'))">
    <div class="flex items-center flex-wrap p-1">
        @foreach ([
            [
                'label' => 'Align Left',
                'command' => 'updateAttributes(\'image\', { align: \'left\', float: null })',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="currentColor"><path d="M3 21V3H5V21H3ZM9 15H15V18H9V15ZM8 13C7.44772 13 7 13.4477 7 14V19C7 19.5523 7.44772 20 8 20H16C16.5523 20 17 19.5523 17 19V14C17 13.4477 16.5523 13 16 13H8ZM9 9H19V6H9V9ZM7 5C7 4.44772 7.44772 4 8 4H20C20.5523 4 21 4.44772 21 5V10C21 10.5523 20.5523 11 20 11H8C7.44772 11 7 10.5523 7 10V5Z"></path></svg>',
            ],
            [
                'label' => 'Align Center',
                'command' => 'updateAttributes(\'image\', { align: \'center\', float: null })',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="currentColor"><path d="M11 4V2H13V4H19C19.5523 4 20 4.44772 20 5V10C20 10.5523 19.5523 11 19 11H13V13H17C17.5523 13 18 13.4477 18 14V19C18 19.5523 17.5523 20 17 20H13V22H11V20H7C6.44772 20 6 19.5523 6 19V14C6 13.4477 6.44772 13 7 13H11V11H5C4.44772 11 4 10.5523 4 10V5C4 4.44772 4.44772 4 5 4H11ZM8 15V18H16V15H8ZM6 9H18V6H6V9Z"></path></svg>',
            ],
            [
                'label' => 'Align Right',
                'command' => 'updateAttributes(\'image\', { align: \'right\', float: null })',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="currentColor"><path d="M19 21V3H21V21H19ZM9 15H15V18H9V15ZM8 13C7.44772 13 7 13.4477 7 14V19C7 19.5523 7.44772 20 8 20H16C16.5523 20 17 19.5523 17 19V14C17 13.4477 16.5523 13 16 13H8ZM5 9H15V6H5V9ZM3 5C3 4.44772 3.44772 4 4 4H16C16.5523 4 17 4.44772 17 5V10C17 10.5523 16.5523 11 16 11H4C3.44772 11 3 10.5523 3 10V5Z"></path></svg>',
            ],
        ] as $item)
            <atom:editor.menu.button :label="data_get($item, 'label')" x-on:click="commands().{{ data_get($item, 'command') }}">
                {!! data_get($item, 'icon') !!}
            </atom:editor.menu.button>
        @endforeach

        @foreach (['30%', '50%', '80%', '100%'] as $size)
            <atom:editor.menu.button x-on:click="commands().updateAttributes('image', { width: {{ js($size) }} })">
                <div class="font-medium text-sm">
                    {{ $size }}
                </div>
            </atom:editor.menu.button>
        @endforeach

        <atom:editor.menu.button label="Remove" x-on:click="commands().deleteSelection()">
            <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="currentColor"><path d="M17 6H22V8H20V21C20 21.5523 19.5523 22 19 22H5C4.44772 22 4 21.5523 4 21V8H2V6H7V3C7 2.44772 7.44772 2 8 2H16C16.5523 2 17 2.44772 17 3V6ZM18 8H6V20H18V8ZM9 11H11V17H9V11ZM13 11H15V17H13V11ZM9 4V6H15V4H9Z"></path></svg>
        </atom:editor.menu.button>
    </div>
</div>

