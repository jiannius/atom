{{-- arbitrary-value: a gap, padding or font size in brackets. A line marked `expect:` is a finding (`x2`: two tokens on it); every other line must stay clean. --}}
<atom:page>
    <div class="flex gap-[22px]">Gap</div> {{-- expect: arbitrary-value --}}

    <div class="grid gap-x-[1.5rem] gap-y-[3px]">Axes</div> {{-- expect: arbitrary-value x2 --}}

    <div class="p-[10px] px-[6px]">Padding</div> {{-- expect: arbitrary-value x2 --}}

    <div class="pt-[3px] pr-[3px] pb-[3px] pl-[3px] ps-[2px] pe-[2px] py-[1px]">Every side</div> {{-- expect: arbitrary-value x7 --}}

    <p class="text-[15px]">Type</p> {{-- expect: arbitrary-value --}}

    <p class="text-[0.8rem] md:text-[13px]">Type again</p> {{-- expect: arbitrary-value x2 --}}

    <div class="p-[2%]">A percentage</div> {{-- expect: arbitrary-value --}}

    <div :class="open ? 'p-[18px]' : ''">Alpine</div> {{-- expect: arbitrary-value --}}

    <atom:card class="gap-[22px]">Card</atom:card> {{-- expect: arbitrary-value --}}

    {{-- expect: arbitrary-value --}}
    <div
        id="wide"
        @class(['p-2', 'gap-[5px]' => $tight])
    >
        An @class argument is read, at the tag's first line
    </div>

    {{-- look-alikes: none of these is flagged --}}
    <atom:modal class="w-[28rem] max-w-[640px] h-[350px] size-[26px]">A width is the modal pattern</atom:modal>
    <div class="gap-[var(--gap)] p-[--pad] gap-(--gap) p-(--pad)">A variable value</div>
    <div class="pb-[env(safe-area-inset-bottom)] px-[calc(var(--pad)*2)]">An environment or variable value</div>
    <p class="text-[#abc] text-[var(--fs)] text-[length:var(--fs)] text-[color:var(--c)]">A colour or a variable size</p>
    <p class="text-sm text-xs gap-4 p-3 px-2">The scale</p>
    <div class="mt-[3px] rounded-[5px] top-[3px] inset-[2px]">Not spacing inside a box</div>
    <div class="gap-{{ $n }} p-[{{ $n }}px]">A token built from an echo</div>
    <p>A gap-[22px] in a sentence is only text.</p>

    <atom:layouts.sidebar>
        <x-slot:brand>
            <div class="gap-[22px] text-[15px]">The host's own look</div>
        </x-slot:brand>
    </atom:layouts.sidebar>
</atom:page>
