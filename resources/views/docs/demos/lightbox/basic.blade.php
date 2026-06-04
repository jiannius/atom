<div x-data>
    <div data-lightbox class="flex flex-wrap gap-3">
        @foreach ([10, 20, 30, 40] as $id)
            <img
            src="https://picsum.photos/id/{{ $id }}/200/200.jpg"
            data-lightbox-url="https://picsum.photos/id/{{ $id }}/1200/800.jpg"
            data-lightbox-name="Photo {{ $id }}"
            x-on:click="$dispatch('lightbox')"
            class="size-24 rounded-lg object-cover cursor-pointer transition hover:opacity-80"/>
        @endforeach
    </div>

    <atom:lightbox/>
</div>
