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

    {{-- expect: palette-colour --}}
    <div
        id="wide"
        @class(['p-2', 'ring-blue-500' => $focus])
    >
        An @class argument is read, at the tag's first line
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
