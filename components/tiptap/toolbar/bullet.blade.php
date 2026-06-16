<atom:dropdown>
    <atom:tiptap.toolbar.button label="List">
        <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="currentColor"><path d="M8 4H21V6H8V4ZM3 3.5H6V6.5H3V3.5ZM3 10.5H6V13.5H3V10.5ZM3 17.5H6V20.5H3V17.5ZM8 11H21V13H8V11ZM8 18H21V20H8V18Z"></path></svg>
    </atom:tiptap.toolbar.button>

    <atom:menu popover>
        @foreach ([
            [
                'label' => 'Bullet List',
                'command' => 'toggleBulletList()',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="currentColor"><path d="M8 4H21V6H8V4ZM3 3.5H6V6.5H3V3.5ZM3 10.5H6V13.5H3V10.5ZM3 17.5H6V20.5H3V17.5ZM8 11H21V13H8V11ZM8 18H21V20H8V18Z"></path></svg>',
            ],
            [
                'label' => 'Ordered List',
                'command' => 'toggleOrderedList()',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="currentColor"><path d="M8 4H21V6H8V4ZM5 3V6H6V7H3V6H4V4H3V3H5ZM3 14V11.5H5V11H3V10H6V12.5H4V13H6V14H3ZM5 19.5H3V18.5H5V18H3V17H6V21H3V20H5V19.5ZM8 11H21V13H8V11ZM8 18H21V20H8V18Z"></path></svg>',
            ],
            [
                'label' => 'Indent',
                'command' => 'sinkListItem("listItem")',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="currentColor"><path d="M3 4H21V6H3V4ZM3 19H21V21H3V19ZM11 14H21V16H11V14ZM11 9H21V11H11V9ZM3 12.5L7 9V16L3 12.5Z"></path></svg>',
            ],
            [
                'label' => 'Outdent',
                'command' => 'liftListItem("listItem")',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="currentColor"><path d="M3 4H21V6H3V4ZM3 19H21V21H3V19ZM11 14H21V16H11V14ZM11 9H21V11H11V9ZM7 12.5L3 16V9L7 12.5Z"></path></svg>',
            ],
        ] as $btn)
            <atom:menu.item x-on:click="commands().{{ data_get($btn, 'command') }}">
                <div class="flex items-center gap-3">
                    {!! data_get($btn, 'icon') !!}
                    <div class="grow">{{ t(data_get($btn, 'label')) }}</div>
                </div>
            </atom:menu.item>
        @endforeach
    </atom:menu>
</atom:dropdown>
