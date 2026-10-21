<atom:dropdown>
    <atom:tiptap.toolbar.button label="Text Color">
        <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="currentColor"><path d="M15.2459 14H8.75407L7.15407 18H5L11 3H13L19 18H16.8459L15.2459 14ZM14.4459 12L12 5.88516L9.55407 12H14.4459ZM3 20H21V22H3V20Z"></path></svg>
    </atom:tiptap.toolbar.button>

    <atom:menu popover>
        <div class="grow grid grid-cols-11 gap-1 p-2 max-h-[300px] overflow-auto" data-atom-scroll-region>
            @foreach (Arr::collapse(\Jiannius\Atom\Services\Color::all()) as $color)
                <div
                x-on:click="commands().setColor(@js($color)); close()"
                x-bind:style="{ backgroundColor: @js($color) }"
                class="cursor-pointer w-6 h-6 border border-border-strong rounded-nav hover:ring-1 hover:ring-fg hover:ring-offset-1 hover:ring-offset-surface-raised">
                </div>
            @endforeach

            <div
            x-on:click="commands().unsetColor(); close()"
            class="cursor-pointer w-6 h-6 border border-danger-fg rounded-nav flex items-center justify-center text-danger-fg hover:ring-1 hover:ring-fg hover:ring-offset-1 hover:ring-offset-surface-raised">
                <atom:icon.close class="size-4" />
            </div>
        </div>
    </atom:menu>
</atom:dropdown>
