{{-- palette-colour: a palette colour or a dark: pair. A line marked `expect:` is a finding (`x2`: two tokens on it); every other line must stay clean. --}}
<atom:page>
    <div class="bg-zinc-100 dark:bg-zinc-800"> {{-- expect: palette-colour x2 --}}
        Pair
    </div>

    <span class="text-red-500">Late</span> {{-- expect: palette-colour --}}

    <p class="text-white bg-black/50">Scrim</p> {{-- expect: palette-colour x2 --}}

    <div class="hover:border-slate-300 md:hover:ring-sky-500/40">State</div> {{-- expect: palette-colour x2 --}}

    <div :class="['bg-green-100 text-green-800' => $ok]">Bound</div> {{-- expect: palette-colour x2 --}}

    <div class="p-2 {{ $late ? 'bg-amber-50' : '' }}">Echoed string</div> {{-- expect: palette-colour --}}

    <div class="dark:text-fg">A dark: role token</div> {{-- expect: palette-colour --}}

    <div class="dark:hover:bg-[#111] dark:border-border">A dark: hex and role</div> {{-- expect: palette-colour x2 --}}

    <atom:icon.check class="size-4 text-zinc-400" /> {{-- expect: palette-colour --}}

    <div class="bg-zinc-100 bg-zinc-100">One token twice counts once</div> {{-- expect: palette-colour --}}

    <atom:select wrapper-class="text-rose-600" /> {{-- expect: palette-colour --}}

    <div class="[&:hover]:bg-red-500 supports-[display:grid]:text-teal-700">A colon inside brackets is no variant</div> {{-- expect: palette-colour x2 --}}

    <div class="dark:bg-[color:#111]">A typed colour in brackets</div> {{-- expect: palette-colour --}}

    {{-- expect: palette-colour --}}
    <div
        id="wide"
        @class(['p-2', 'ring-blue-500' => $focus])
    >
        An @class argument is read, at the tag's first line
    </div>

    <div class="bg-red-500 text-red-500 border-red-500 border-t-red-500 ring-red-500 ring-offset-red-500 inset-ring-red-500 divide-red-500 outline-red-500 fill-red-500 stroke-red-500 from-red-500 via-red-500 to-red-500 placeholder-red-500 decoration-red-500 accent-red-500 caret-red-500 shadow-red-500 drop-shadow-red-500 text-shadow-red-500"> {{-- expect: palette-colour x21 --}}
        Every colour utility prefix
    </div>

    <div class="border-r-red-500 border-b-red-500 border-l-red-500 border-x-red-500 border-y-red-500 border-s-red-500 border-e-red-500"> {{-- expect: palette-colour x7 --}}
        Every side of a border
    </div>

    <div class="bg-slate-500 bg-gray-500 bg-zinc-500 bg-neutral-500 bg-stone-500 bg-red-500 bg-orange-500 bg-amber-500 bg-yellow-500 bg-lime-500 bg-green-500 bg-emerald-500 bg-teal-500 bg-cyan-500 bg-sky-500 bg-blue-500 bg-indigo-500 bg-violet-500 bg-purple-500 bg-fuchsia-500 bg-pink-500 bg-rose-500 bg-mauve-500 bg-olive-500 bg-mist-500 bg-taupe-500"> {{-- expect: palette-colour x26 --}}
        Every hue
    </div>

    <div class="dark:bg-[rgb(0,0,0)] dark:bg-[rgba(0,0,0,.5)] dark:bg-[hsl(1,2%,3%)] dark:bg-[hsla(1,2%,3%,.5)] dark:bg-[oklch(0.1_0.1_100)]"> {{-- expect: palette-colour x5 --}}
        Every colour function in brackets after dark:
    </div>

    <div class="dark:border-control-border"> {{-- expect: palette-colour --}}
        A dark: control border
    </div>

    {{-- look-alikes: none of these is flagged --}}
    <div class="bg-surface text-fg-muted border-border">Role tokens</div>
    <div class="bg-[#fffefc] text-[#1d1c19] border-[rgb(0,0,0)]">A colour in brackets is the document paper's</div>
    <div class="text-sm dark:hidden">A dark: that is no colour</div>
    <div class="dark:block md:dark:flex">Neither</div>
    <div class="bg-neutral-bg text-neutral-fg border-danger-border">Tone tokens</div>
    <div class="text-accent-foreground bg-accent hover:bg-accent-ink">Accent tokens</div>
    <div class="border-t border-border-strong divide-y divide-border">Rules</div>
    <div class="bg-{{ $tone }}-100">A token built from an echo</div>
    <div data-tone="text-red-500" title="bg-zinc-100">Not a class attribute</div>
    <p>Writing bg-zinc-100 or text-white in a sentence is only text.</p>
    <div class="text-red">No step is no palette colour</div>
    <div class="bg-zinc-25">A step that Tailwind has not got is read as no colour</div>

    <atom:layouts.sidebar>
        <x-slot:brand>
            <div class="bg-zinc-900 text-white dark:bg-black">The host's own look</div>
        </x-slot:brand>
        <x-slot:logo>
            <span class="text-indigo-600">Logo</span>
        </x-slot:logo>
    </atom:layouts.sidebar>
</atom:page>
