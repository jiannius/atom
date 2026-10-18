<?php

// V4-2c: <atom:timeline> and <atom:timeline.item>, in its three kinds.

describe('timeline', function () {
    it('is an ordered list named for its kind, one row per item', function (string $kind, string $name) {
        $html = renderBlade('<atom:timeline kind="'.$kind.'"><atom:timeline.item title="One" /><atom:timeline.item title="Two" /></atom:timeline>');
        $list = domQuery($html, '//*[@data-atom-timeline]/ol');

        expect($list)->toHaveCount(1)
            ->and($list[0]->getAttribute('aria-label'))->toBe($name)
            ->and(domQuery($html, '//ol/li[@data-atom-timeline-item]'))->toHaveCount(2)
            ->and(domQuery($html, '//*[@data-atom-timeline]')[0]->getAttribute('data-kind'))->toBe($kind);
    })->with([['event', 'Activity'], ['step', 'Steps'], ['message', 'Messages']]);

    it('is an event timeline by default, and falls back to it for an unknown kind', function () {
        expect(domQuery(renderBlade('<atom:timeline />'), '//*[@data-atom-timeline]')[0]->getAttribute('data-kind'))->toBe('event')
            ->and(domQuery(renderBlade('<atom:timeline kind="nope" />'), '//*[@data-atom-timeline]')[0]->getAttribute('data-kind'))->toBe('event');
    });

    it('takes a name for the list', function () {
        expect(domQuery(renderBlade('<atom:timeline label="Invoice history" />'), '//ol[@aria-label="Invoice history"]'))->toHaveCount(1);
    });

    it('puts the composer after the list, as the last row', function () {
        $html = renderBlade('<atom:timeline kind="message"><atom:timeline.item speaker="A" body="x" /><x-slot:composer><textarea id="c"></textarea></x-slot:composer></atom:timeline>');

        expect(domQuery($html, '//*[@data-atom-timeline]/*[@data-atom-timeline-composer]/textarea[@id="c"]'))->toHaveCount(1)
            ->and(domQuery($html, '//*[@data-atom-timeline-composer]/preceding-sibling::ol'))->toHaveCount(1);
    });

    it('has no composer wrapper without one', function () {
        expect(domQuery(renderBlade('<atom:timeline />'), '//*[@data-atom-timeline-composer]'))->toBeEmpty();
    });
});

describe('event', function () {
    it('prints what happened, who and when, with a dot in the tone', function () {
        $html = renderBlade('<atom:timeline><atom:timeline.item title="Payment received" body="by bank transfer" actor="Ana" time="14 Jul, 2:10 pm" tone="success" /></atom:timeline>');
        $item = domQuery($html, '//li')[0];

        expect(trim(domQuery($html, '//*[@data-atom-timeline-title]')[0]->textContent))->toBe('Payment received')
            ->and($item->textContent)->toContain('by bank transfer')
            ->and(domQuery($html, '//*[@data-atom-timeline-actor]')[0]->textContent)->toBe('Ana')
            ->and($item->textContent)->toContain('14 Jul, 2:10 pm')
            ->and(domClasses(domQuery($html, '//*[@data-atom-timeline-dot]')[0]))->toContain('bg-success-fg')
            ->and($item->getAttribute('data-tone'))->toBe('success');
    });

    it('has a dot for every tone, and treats an unknown one as neutral', function (string $tone, string $class) {
        $dot = domQuery(renderBlade('<atom:timeline><atom:timeline.item title="x" :tone="$t" /></atom:timeline>', ['t' => $tone]), '//*[@data-atom-timeline-dot]')[0];

        expect(domClasses($dot))->toContain($class);
    })->with([['neutral', 'bg-fg-subtle'], ['info', 'bg-info-fg'], ['success', 'bg-success-fg'], ['warning', 'bg-warning-fg'], ['danger', 'bg-danger-fg'], ['accent', 'bg-accent'], ['wild', 'bg-fg-subtle']]);

    it('hides the dot from a screen reader and keeps the words', function () {
        $html = renderBlade('<atom:timeline><atom:timeline.item title="Failed" status="Bounced" tone="danger" /></atom:timeline>');

        expect(domQuery($html, '//*[@data-atom-timeline-dot]')[0]->getAttribute('aria-hidden'))->toBe('true')
            ->and(domQuery($html, '//*[@data-atom-status][@data-tone="danger"]')[0]->textContent)->toContain('Bounced');
    });

    it('folds a detail away behind a toggle that says whether it is open', function () {
        $html = renderBlade('<atom:timeline><atom:timeline.item title="Reminder emailed"><x-slot:detail>Opened twice</x-slot:detail></atom:timeline.item></atom:timeline>');
        $toggle = domQuery($html, '//button[@data-atom-timeline-toggle]');
        $detail = domQuery($html, '//*[@data-atom-timeline-detail]');

        expect($toggle)->toHaveCount(1)
            ->and($toggle[0]->getAttribute('aria-expanded'))->toBe('false')
            ->and($toggle[0]->getAttribute('type'))->toBe('button')
            ->and($detail)->toHaveCount(1)
            ->and($detail[0]->getAttribute('x-show'))->toBe('open')
            ->and($detail[0]->hasAttribute('x-cloak'))->toBeTrue()
            ->and($detail[0]->textContent)->toContain('Opened twice');
    });

    it('has no toggle without a detail', function () {
        expect(domQuery(renderBlade('<atom:timeline><atom:timeline.item title="x" /></atom:timeline>'), '//button'))->toBeEmpty();
    });

    it('prints a DateTimeInterface in a time element, with a machine value', function () {
        $html = renderBlade('<atom:timeline><atom:timeline.item title="x" :time="$t" /></atom:timeline>', ['t' => new DateTimeImmutable('2026-07-14 14:10:00', new DateTimeZone('UTC'))]);
        $time = domQuery($html, '//time')[0];

        expect($time->getAttribute('datetime'))->toBe('2026-07-14T14:10:00+00:00')
            ->and($time->textContent)->toBe('14 Jul 2026, 2:10 pm');
    });

    it('prints a time string as it is, in no time element', function () {
        $html = renderBlade('<atom:timeline><atom:timeline.item title="x" time="Yesterday" /></atom:timeline>');

        expect(domQuery($html, '//time'))->toBeEmpty()->and($html)->toContain('Yesterday');
    });
});

describe('step', function () {
    it('marks each step by its state: a number, a check or a cross', function () {
        $html = renderBlade('<atom:timeline kind="step">
            <atom:timeline.item title="Queued" state="done" />
            <atom:timeline.item title="Build" state="current" status="Running" />
            <atom:timeline.item title="Deploy" state="pending" />
            <atom:timeline.item title="Check" state="failed" />
        </atom:timeline>');
        $items = domQuery($html, '//li');

        expect(array_map(fn ($i) => $i->getAttribute('data-state'), $items))->toBe(['done', 'current', 'pending', 'failed'])
            ->and(domQuery($html, '//li[1]//*[@data-atom-timeline-marker]//svg'))->toHaveCount(1)
            ->and(domQuery($html, '//li[4]//*[@data-atom-timeline-marker]//svg'))->toHaveCount(1)
            ->and(domQuery($html, '//li[2]//*[@data-atom-timeline-marker]//svg'))->toBeEmpty()
            ->and(domQuery($html, '//li[3]//*[@data-atom-timeline-marker]//svg'))->toBeEmpty()
            // a state is never colour alone: the done and failed markers carry a word
            ->and(domQuery($html, '//li[1]//*[@data-atom-timeline-marker]//*[contains(@class, "sr-only")]')[0]->textContent)->toBe('Done')
            ->and(domQuery($html, '//li[4]//*[@data-atom-timeline-marker]//*[contains(@class, "sr-only")]')[0]->textContent)->toBe('Failed');
    });

    it('numbers the steps with a counter the list resets', function () {
        $html = renderBlade('<atom:timeline kind="step"><atom:timeline.item title="A" /><atom:timeline.item title="B" /></atom:timeline>');

        expect(domClasses(domQuery($html, '//ol')[0]))->toContain('[counter-reset:atom-step]')
            ->and(domClasses(domQuery($html, '//*[@data-atom-timeline-marker]')[0]))->toContain('[counter-increment:atom-step]');
    });

    it('pulses the status of the current step only, in the state tone', function () {
        $html = renderBlade('<atom:timeline kind="step">
            <atom:timeline.item title="A" state="done" status="Done in 2m" />
            <atom:timeline.item title="B" state="current" status="Running" />
            <atom:timeline.item title="C" state="failed" status="Timed out" />
        </atom:timeline>');

        expect(domQuery($html, '//*[@data-atom-status][@data-running]'))->toHaveCount(1)
            ->and(domQuery($html, '//li[2]//*[@data-atom-status][@data-running]'))->toHaveCount(1)
            ->and(domQuery($html, '//li[1]//*[@data-atom-status]')[0]->getAttribute('data-tone'))->toBe('success')
            ->and(domQuery($html, '//li[2]//*[@data-atom-status]')[0]->getAttribute('data-tone'))->toBe('accent')
            ->and(domQuery($html, '//li[3]//*[@data-atom-status]')[0]->getAttribute('data-tone'))->toBe('danger');
    });

    it('folds a step with a body, open for the current and failed ones', function () {
        $html = renderBlade('<atom:timeline kind="step">
            <atom:timeline.item title="A" state="done">log a</atom:timeline.item>
            <atom:timeline.item title="B" state="current">log b</atom:timeline.item>
            <atom:timeline.item title="C" state="failed">log c</atom:timeline.item>
            <atom:timeline.item title="D" state="pending" :open="true">log d</atom:timeline.item>
        </atom:timeline>');

        $open = array_map(fn ($li) => $li->getAttribute('x-data'), domQuery($html, '//li'));

        expect($open)->toBe(['{ open: false }', '{ open: true }', '{ open: true }', '{ open: true }'])
            ->and(domQuery($html, '//li[1]//button[@data-atom-timeline-toggle]')[0]->getAttribute('aria-expanded'))->toBe('false')
            ->and(domQuery($html, '//li[2]//button[@data-atom-timeline-toggle]')[0]->getAttribute('aria-expanded'))->toBe('true')
            // the body of a folded step is cloaked until Alpine reads it, so it never flashes open
            ->and(domQuery($html, '//li[1]//*[@data-atom-timeline-body]')[0]->hasAttribute('x-cloak'))->toBeTrue()
            ->and(domQuery($html, '//li[2]//*[@data-atom-timeline-body]')[0]->hasAttribute('x-cloak'))->toBeFalse();
    });

    it('does not fold when the timeline is not collapsible', function () {
        $html = renderBlade('<atom:timeline kind="step" :collapsible="false"><atom:timeline.item title="A" state="done">log</atom:timeline.item></atom:timeline>');

        expect(domQuery($html, '//button'))->toBeEmpty()
            ->and(domQuery($html, '//li')[0]->hasAttribute('x-data'))->toBeFalse()
            ->and(domQuery($html, '//*[@data-atom-timeline-body]')[0]->hasAttribute('x-show'))->toBeFalse();
    });

    it('has no toggle for a step with no body', function () {
        expect(domQuery(renderBlade('<atom:timeline kind="step"><atom:timeline.item title="A" /></atom:timeline>'), '//button'))->toBeEmpty();
    });
});

describe('message', function () {
    it('puts the other speaker at the start and you at the end', function () {
        $html = renderBlade('<atom:timeline kind="message">
            <atom:timeline.item speaker="Customer" time="10:02" body="Hello" />
            <atom:timeline.item speaker="You" side="end" body="Hi" />
        </atom:timeline>');
        $items = domQuery($html, '//li');

        expect(array_map(fn ($i) => $i->getAttribute('data-side'), $items))->toBe(['start', 'end'])
            ->and(domClasses($items[1]))->toContain('items-end')
            ->and(domClasses($items[0]))->not->toContain('items-end')
            ->and(domClasses(domQuery($html, '//li[1]//*[@data-atom-timeline-bubble]')[0]))->toContain('bg-neutral-bg')
            ->and(domClasses(domQuery($html, '//li[2]//*[@data-atom-timeline-bubble]')[0]))->toContain('bg-accent-soft');
    });

    it('prints a message at the reading size, in a bubble that cannot outgrow the column', function () {
        $bubble = domQuery(renderBlade('<atom:timeline kind="message"><atom:timeline.item body="x" /></atom:timeline>'), '//*[@data-atom-timeline-bubble]')[0];

        expect(domClasses($bubble))->toContain('text-base', 'max-w-[85%]', 'break-words');
    });

    it('treats any side but end as the start', function () {
        expect(domQuery(renderBlade('<atom:timeline kind="message"><atom:timeline.item body="x" side="left" /></atom:timeline>'), '//li')[0]->getAttribute('data-side'))->toBe('start');
    });
});

describe('escaping', function () {
    $evil = '<img src=x onerror=alert(1)>';
    $escaped = '&lt;img src=x onerror=alert(1)&gt;';

    it('prints an event title, body, actor, time and status as text', function () use ($evil, $escaped) {
        $html = renderBlade('<atom:timeline><atom:timeline.item :title="$e" :body="$e" :actor="$e" :time="$e" :status="$e" /></atom:timeline>', ['e' => $evil]);

        expect(domQuery($html, '//img'))->toBeEmpty()
            ->and(substr_count($html, $escaped))->toBe(5);
    });

    it('prints a step title, status and time as text', function () use ($evil, $escaped) {
        $html = renderBlade('<atom:timeline kind="step"><atom:timeline.item :title="$e" :status="$e" :time="$e" /></atom:timeline>', ['e' => $evil]);

        expect(domQuery($html, '//img'))->toBeEmpty()->and(substr_count($html, $escaped))->toBe(3);
    });

    it('prints a message speaker, time and body as text, keeping its line breaks', function () use ($evil, $escaped) {
        $html = renderBlade('<atom:timeline kind="message"><atom:timeline.item :speaker="$e" :time="$e" :body="$b" /></atom:timeline>', ['e' => $evil, 'b' => $evil."\nsecond line"]);

        expect(domQuery($html, '//img'))->toBeEmpty()
            ->and(substr_count($html, $escaped))->toBe(3)
            ->and(domClasses(domQuery($html, '//*[@data-atom-timeline-bubble]/span')[0]))->toContain('whitespace-pre-line');
    });

    it('does not read a pipe in an actor or a title as a translation separator', function () {
        $html = renderBlade('<atom:timeline><atom:timeline.item title="A|B" actor="C|D" /></atom:timeline>');

        expect($html)->toContain('A|B')->toContain('C|D');
    });

    it('ignores a value that is not text', function () {
        $html = renderBlade('<atom:timeline><atom:timeline.item :title="$a" :body="$a" :actor="$a" /></atom:timeline>', ['a' => ['x']]);

        expect(domQuery($html, '//li'))->toHaveCount(1)->and(domQuery($html, '//*[@data-atom-timeline-title]'))->toBeEmpty();
    });

    it('prints the label of the list as text', function () use ($evil) {
        expect(domQuery(renderBlade('<atom:timeline :label="$e" />', ['e' => $evil]), '//img'))->toBeEmpty();
    });
});
