@php
$answer = <<<'MD'
## Next steps

Move the visit to **Friday** and confirm with the customer:

1. Call the site contact
2. Update the [work order](https://example.com/orders/1042)
3. Send the new time by email

| Day | Slot |
|-----|------|
| Fri | 10:00 |

Use `php artisan queue:work` to clear the backlog.
MD;
@endphp

<div class="max-w-md">
    <atom:timeline kind="message" label="Conversation">
        <atom:timeline.item speaker="Customer" time="10:02" body="Can you move the visit to Friday?"/>

        <atom:timeline.item speaker="Assistant" time="10:03">
            <atom:prose :markdown="$answer" size="sm"/>
        </atom:timeline.item>
    </atom:timeline>
</div>
