{{-- shadow: a box shadow. A line marked `expect:` is a finding (`x2`: two tokens on it); every other line must stay clean. --}}
<atom:page>
    <div class="shadow-sm">Small</div> {{-- expect: shadow --}}

    <div class="rounded-md shadow">Bare</div> {{-- expect: shadow --}}

    <div class="hover:shadow-lg md:shadow-xl">States</div> {{-- expect: shadow x2 --}}

    <div class="shadow-[0_1px_2px_rgba(0,0,0,0.1)]">Arbitrary</div> {{-- expect: shadow --}}

    <div class="shadow-inner shadow-2xs">Two sizes</div> {{-- expect: shadow x2 --}}

    <atom:card class="shadow-md">Card</atom:card> {{-- expect: shadow --}}

    <div :class="open ? 'shadow-md' : ''">Alpine</div> {{-- expect: shadow --}}

    {{-- expect: shadow --}}
    <div
        id="wide"
        @class(['p-2', 'shadow-2xl' => $raised])
    >
        An @class argument is read, at the tag's first line
    </div>

    {{-- look-alikes: none of these is flagged --}}
    <div class="shadow-none md:shadow-none">None</div>
    <div class="shadow-pop">atom's raised layer</div>
    <div class="drop-shadow-md inset-shadow-sm text-shadow-sm">Other shadows are other utilities</div>
    <div class="shadow-black/10">A shadow colour is palette-colour's</div>
    <div class="shadowed box-shadow shadow-sm-not">Words that only start alike</div>
    <div style="box-shadow: none">A style attribute is not read</div>
    <p>A shadow-sm in a sentence is only text.</p>
    <div class="shadow-{{ $size }}">A token built from an echo</div>

    <atom:layouts.sidebar>
        <x-slot:brand>
            <div class="shadow-md">The host's own look</div>
        </x-slot:brand>
    </atom:layouts.sidebar>
</atom:page>
