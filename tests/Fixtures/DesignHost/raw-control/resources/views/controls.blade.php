{{-- raw-control: a button or link styled by hand. A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:page>
    <button class="rounded-md border px-3 py-1">Save</button> {{-- expect: raw-control --}}

    <a href="#" class="rounded-lg bg-accent px-4 py-2 text-accent-foreground">Open</a> {{-- expect: raw-control --}}

    <button type="button" class="inline-flex h-8 items-center rounded border-2 bg-surface" x-on:click="go()">Go</button> {{-- expect: raw-control --}}

    <a href="#" :class="['rounded-md bg-surface-sunken px-2 py-1' => $on]">Bound</a> {{-- expect: raw-control --}}

    {{-- expect: raw-control --}}
    <button
        type="button"
        class="rounded-full border p-2"
    >
        <atom:icon.plus />
    </button>

    {{-- look-alikes: none of these is flagged --}}
    <a href="#" class="text-accent-ink underline">A text link</a>
    <a href="#" class="px-2 py-1 hover:bg-surface-sunken">A nav item has no resting fill or rounding</a>
    <button class="rounded-md px-2 py-1">No border or fill</button>
    <button class="rounded-md border">No padding or height</button>
    <button class="rounded-md border p-0">Padding zero</button>
    <button class="rounded-md border border-border bg-transparent">Transparent, no padding</button>
    <button class="rounded-md bg-transparent px-2">Transparent is no fill</button>
    <button class="bg-surface px-2 py-1 hover:rounded-md">The rounding is a state</button>
    <button class="rounded-md border h-full">A full height is not a control height</button>
    <button class="rounded-md border px-2 py-1 {{ $extra }}">An echo among the classes hides only itself</button> {{-- expect: raw-control --}}
    <a href="#" class="block rounded-panel border bg-surface p-4"><div>A tile holds blocks</div></a>
    <button class="rounded-md border px-2 py-1" role="switch" aria-checked="true">A switch</button>
    <button class="rounded-md border px-2 py-1" role="tab">A tab</button>
    <atom:button variant="ghost">Atom's own</atom:button>
    <x-fancy-button class="rounded-md border px-2 py-1">A host component</x-fancy-button>
    <div class="rounded-md border px-2 py-1">Not a control</div>

    {{-- a tile or a row: a block, several elements, or a picture beside the text --}}
    <a href="#" class="rounded-md border px-2 py-1"><div>x</div></a>
    <a href="#" class="rounded-md border px-2 py-1"><p>x</p></a>
    <a href="#" class="rounded-md border px-2 py-1"><section>x</section></a>
    <a href="#" class="rounded-md border px-2 py-1"><article>x</article></a>
    <a href="#" class="rounded-md border px-2 py-1"><ul>x</ul></a>
    <a href="#" class="rounded-md border px-2 py-1"><ol>x</ol></a>
    <a href="#" class="rounded-md border px-2 py-1"><table>x</table></a>
    <a href="#" class="rounded-md border px-2 py-1"><figure>x</figure></a>
    <a href="#" class="rounded-md border px-2 py-1"><form>x</form></a>
    <a href="#" class="rounded-md border px-2 py-1"><h1>x</h1></a>
    <a href="#" class="rounded-md border px-2 py-1"><h2>x</h2></a>
    <a href="#" class="rounded-md border px-2 py-1"><h3>x</h3></a>
    <a href="#" class="rounded-md border px-2 py-1"><h4>x</h4></a>
    <a href="#" class="rounded-md border px-2 py-1"><h5>x</h5></a>
    <a href="#" class="rounded-md border px-2 py-1"><h6>x</h6></a>
    <a href="#" class="rounded-md border px-2 py-1"><atom:card>x</atom:card></a>
    <a href="#" class="rounded-md border px-2 py-1"><atom:panel>x</atom:panel></a>
    <a href="#" class="flex items-center gap-3 rounded-lg border p-3 hover:bg-surface"><img src="a.png" alt=""><span>Name</span><span>Sub</span></a>
    <a href="#" class="rounded-lg border p-3"><atom:avatar /> Name</a>
    <button class="rounded-md border px-2 py-1"><atom:icon.plus /> Add</button>
    <button class="rounded-md border px-2 py-1"><svg viewBox="0 0 1 1"></svg> Add</button>
    <button class="rounded-md border px-2 py-1"><span>A</span><span>B</span></button>
    <button class="rounded-md border p-2"><span>Save</span></button> {{-- expect: raw-control --}}

    {{-- a background that is not a fill --}}
    <button class="rounded-md px-2 bg-transparent">No fill</button>
    <button class="rounded-md px-2 bg-inherit">No fill</button>
    <button class="rounded-md px-2 bg-current">No fill</button>
    <button class="rounded-md px-2 bg-none">No fill</button>
    <button class="rounded-md px-2 bg-clip-text">No fill</button>
    <button class="rounded-md px-2 bg-cover">No fill</button>
    <button class="rounded-md px-2 bg-contain">No fill</button>
    <button class="rounded-md px-2 bg-center">No fill</button>
    <button class="rounded-md px-2 bg-top">No fill</button>
    <button class="rounded-md px-2 bg-bottom">No fill</button>
    <button class="rounded-md px-2 bg-left">No fill</button>
    <button class="rounded-md px-2 bg-right">No fill</button>
    <button class="rounded-md px-2 bg-repeat">No fill</button>
    <button class="rounded-md px-2 bg-no-repeat">No fill</button>
    <button class="rounded-md px-2 bg-fixed">No fill</button>
    <button class="rounded-md px-2 bg-local">No fill</button>
    <button class="rounded-md px-2 bg-scroll">No fill</button>
    <button class="rounded-md px-2 bg-origin-center">No fill</button>
    <button class="rounded-md px-2 bg-blend-multiply">No fill</button>
    <button class="rounded-md px-2 bg-gradient-to-r">No fill</button>
    <button class="rounded-md px-2 bg-linear-45">No fill</button>
    <button class="rounded-md px-2 bg-radial">No fill</button>
    <button class="rounded-md px-2 bg-conic">No fill</button>
    <button class="rounded-md px-2 bg-size-auto">No fill</button>
    <button class="rounded-md px-2 bg-position-x">No fill</button>
    <button class="rounded-md px-2 bg-auto">No fill</button>

    <atom:layouts.sidebar>
        <x-slot:brand>
            <a href="/" class="rounded-md border px-2 py-1">The host's own look</a>
        </x-slot:brand>
    </atom:layouts.sidebar>
</atom:page>
