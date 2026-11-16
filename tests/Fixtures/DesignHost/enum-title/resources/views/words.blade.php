{{-- enum-title: an enum's word title-cased by hand. A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:page>
    <p>{{ str($order->status->name)->title() }}</p> {{-- expect: enum-title --}}
    <p>{{ Str::headline($order->status->value) }}</p> {{-- expect: enum-title --}}
    <p>{{ ucfirst($invoice->stage->value) }}</p> {{-- expect: enum-title --}}
    <p>{{ ucwords(str_replace('_', ' ', $job->type->name)) }}</p> {{-- expect: enum-title --}}
    <p>{{ str($status->value)->replace('_', ' ')->title() }}</p> {{-- expect: enum-title --}}
    <p>{{ Str::of($order->kind?->name)->headline() }}</p> {{-- expect: enum-title --}}
    <p>{{ str($order->account->state->value)->title() }}</p> {{-- expect: enum-title --}}

    {{-- look-alikes --}}
    <p>{{ str($user->name)->title() }}</p>
    <p>{{ Str::title($customer->company->name) }}</p>
    <p>{{ ucfirst($user->role) }}</p>
    <p>{{ str($address->state->name)->title() }}</p>
    <p>{{ $order->status->label() }}</p>
    <p>{{ $order->status->name }}</p>
    <p>{{ str($order->status->name) . ' ' . $user->title() }}</p>
    <p>{{ str($order->status->name)->lower() }}</p>
    <p>{{ str($order->status->name) }} {{ $ticket->title() }}</p>
    <p>{{ substr($order->status->name, 0, 3) }}</p>
    <atom:status :value="$order->status" />
    <p>Never write <code>str($order->status->name)->title()</code></p>
</atom:page>
