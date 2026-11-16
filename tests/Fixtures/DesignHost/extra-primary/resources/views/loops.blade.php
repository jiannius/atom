{{-- extra-primary: a primary in a loop is one per item. --}}
<atom:page>
    <atom:page.header title="Jobs" />

    @foreach ($jobs as $job)
        <atom:card :heading="$job->name">
            <atom:button variant="primary">Start</atom:button> {{-- expect: extra-primary --}}
        </atom:card>
    @endforeach

    @forelse ($jobs as $job)
        <p>{{ $job->name }}</p>
    @empty
        <atom:button variant="primary">Create a job</atom:button>
    @endforelse
</atom:page>

{{-- a modal that is the item of a loop has its own primary --}}
@foreach ($jobs as $job)
    <atom:modal :name="'job-'.$job->id">
        <atom:button variant="primary">Start</atom:button>
    </atom:modal>
@endforeach
