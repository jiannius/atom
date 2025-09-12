<div class="table-menu" x-init="$el.shouldShow = (editor) => (editor?.isActive('table'))">
    <div class="flex items-center gap-1 flex-wrap p-1">
        @foreach ([
            [
                'label' => 'Add column before',
                'command' => 'addColumnBefore()',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="currentColor"><path d="M20 3C20.5523 3 21 3.44772 21 4V20C21 20.5523 20.5523 21 20 21H14C13.4477 21 13 20.5523 13 20V4C13 3.44772 13.4477 3 14 3H20ZM19 5H15V19H19V5ZM6 7C8.76142 7 11 9.23858 11 12C11 14.7614 8.76142 17 6 17C3.23858 17 1 14.7614 1 12C1 9.23858 3.23858 7 6 7ZM7 9H5V10.999L3 11V13L5 12.999V15H7V12.999L9 13V11L7 10.999V9Z"></path></svg>',
            ],
            [
                'label' => 'Add column after',
                'command' => 'addColumnAfter()',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="currentColor"><path d="M10 3C10.5523 3 11 3.44772 11 4V20C11 20.5523 10.5523 21 10 21H4C3.44772 21 3 20.5523 3 20V4C3 3.44772 3.44772 3 4 3H10ZM9 5H5V19H9V5ZM18 7C20.7614 7 23 9.23858 23 12C23 14.7614 20.7614 17 18 17C15.2386 17 13 14.7614 13 12C13 9.23858 15.2386 7 18 7ZM19 9H17V10.999L15 11V13L17 12.999V15H19V12.999L21 13V11L19 10.999V9Z"></path></svg>',
            ],
            [
                'label' => 'Add row before',
                'command' => 'addRowBefore()',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="currentColor"><path d="M20 13C20.5523 13 21 13.4477 21 14V20C21 20.5523 20.5523 21 20 21H4C3.44772 21 3 20.5523 3 20V14C3 13.4477 3.44772 13 4 13H20ZM19 15H5V19H19V15ZM12 1C14.7614 1 17 3.23858 17 6C17 8.76142 14.7614 11 12 11C9.23858 11 7 8.76142 7 6C7 3.23858 9.23858 1 12 1ZM13 3H11V4.999L9 5V7L11 6.999V9H13V6.999L15 7V5L13 4.999V3Z"></path></svg>',
            ],
            [
                'label' => 'Add row after',
                'command' => 'addRowAfter()',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 13C14.7614 13 17 15.2386 17 18C17 20.7614 14.7614 23 12 23C9.23858 23 7 20.7614 7 18C7 15.2386 9.23858 13 12 13ZM13 15H11V16.999L9 17V19L11 18.999V21H13V18.999L15 19V17L13 16.999V15ZM20 3C20.5523 3 21 3.44772 21 4V10C21 10.5523 20.5523 11 20 11H4C3.44772 11 3 10.5523 3 10V4C3 3.44772 3.44772 3 4 3H20ZM5 5V9H19V5H5Z"></path></svg>',
            ],
            [
                'label' => 'Merge cells',
                'command' => 'mergeCells()',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="currentColor"><path d="M20 3C20.5523 3 21 3.44772 21 4V20C21 20.5523 20.5523 21 20 21H4C3.44772 21 3 20.5523 3 20V4C3 3.44772 3.44772 3 4 3H20ZM11 5H5V10.999H7V9L10 12L7 15V13H5V19H11V17H13V19H19V13H17V15L14 12L17 9V10.999H19V5H13V7H11V5ZM13 13V15H11V13H13ZM13 9V11H11V9H13Z"></path></svg>',
            ],
            [
                'label' => 'Split cell',
                'command' => 'splitCell()',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="currentColor"><path d="M20 3C20.5523 3 21 3.44772 21 4V20C21 20.5523 20.5523 21 20 21H4C3.44772 21 3 20.5523 3 20V4C3 3.44772 3.44772 3 4 3H20ZM11 5H5V19H11V15H13V19H19V5H13V9H11V5ZM15 9L18 12L15 15V13H9V15L6 12L9 9V11H15V9Z"></path></svg>',
            ],
            [
                'label' => 'Delete table',
                'command' => 'deleteTable()',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="currentColor"><path d="M7 4V2H17V4H22V6H20V21C20 21.5523 19.5523 22 19 22H5C4.44772 22 4 21.5523 4 21V6H2V4H7ZM6 6V20H18V6H6ZM9 9H11V17H9V9ZM13 9H15V17H13V9Z"></path></svg>',
            ],
        ] as $item)
            <atom:editor.menu.button :label="data_get($item, 'label')" x-on:click="commands().{{ data_get($item, 'command') }}">
                {!! data_get($item, 'icon') !!}
            </atom:editor.menu.button>
        @endforeach
    </div>
</div>

