{{-- extra-primary: a form that is the item of a loop has its own submit. --}}
<atom:page>
    <atom:page.header title="Comments" />

    @foreach ($comments as $comment)
        <atom:form wire:submit="reply({{ $comment->id }})">
            <atom:textarea wire:model="replies.{{ $comment->id }}" />
            <atom:button type="submit">Reply</atom:button>
        </atom:form>
    @endforeach
</atom:page>
