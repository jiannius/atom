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
    })->with([['event', 'Activity'], ['step', 'Steps'], ['path', 'Progress'], ['message', 'Messages']]);

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

    it('lets a bare or labelled input that is a direct child of the composer form fill the row', function () {
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);

        $html = renderBlade('<atom:timeline kind="message"><x-slot:composer><form><atom:input name="a" /><atom:input name="b" label="Reply" /></form></x-slot:composer></atom:timeline>');
        $composer = domQuery($html, '//*[@data-atom-timeline-composer]')[0];

        expect(domClasses($composer))->toContain('[&_form>[data-atom-input]]:min-w-0', '[&_form>[data-atom-input]]:grow', '[&_form>[data-atom-input-field]]:min-w-0', '[&_form>[data-atom-input-field]]:grow')
            // the two roots those selectors name: a bare input's, and a labelled input's field
            ->and(domQuery($html, '//form/*[@data-atom-input]'))->toHaveCount(1)
            ->and(domQuery($html, '//form/*[@data-atom-input-field]'))->toHaveCount(1);
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

        // a UTC instant is shown in the viewer's zone (Kuala Lumpur unless the user or TIMEZONE says otherwise), the machine value keeps its offset
        expect($time->getAttribute('datetime'))->toBe('2026-07-14T14:10:00+00:00')
            ->and($time->textContent)->toBe('14 Jul 2026, 10:10 pm');
    });

    it('shows an instant in the signed-in user\'s timezone', function () {
        \Illuminate\Support\Facades\Auth::setUser(new \Illuminate\Auth\GenericUser(['id' => 1, 'timezone' => 'America/Los_Angeles']));

        $html = renderBlade('<atom:timeline><atom:timeline.item title="x" :time="$t" /></atom:timeline>', ['t' => new DateTimeImmutable('2026-07-14 14:10:00', new DateTimeZone('UTC'))]);
        $time = domQuery($html, '//time')[0];

        expect($time->textContent)->toBe('14 Jul 2026, 7:10 am')
            ->and($time->getAttribute('datetime'))->toBe('2026-07-14T14:10:00+00:00');
    });

    it('does not move an instant that is already in the viewer\'s zone', function () {
        $html = renderBlade('<atom:timeline><atom:timeline.item title="x" :time="$t" /></atom:timeline>', ['t' => new DateTimeImmutable('2026-07-14 22:10:00', new DateTimeZone('Asia/Kuala_Lumpur'))]);

        expect(domQuery($html, '//time')[0]->textContent)->toBe('14 Jul 2026, 10:10 pm');
    });

    it('says in its docs that a date with no time goes in as a Y-m-d string', function () {
        expect(file_get_contents(dirname(__DIR__, 2).'/components/timeline/item.blade.php'))->toContain('`Y-m-d` string');
        expect(file_get_contents(dirname(__DIR__, 2).'/README.md'))->toContain('pass a date with no time as a `Y-m-d` string');
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

    it('says which steps are current and pending to a screen reader, as it does done and failed', function () {
        $html = renderBlade('<atom:timeline kind="step"><atom:timeline.item title="A" state="current" /><atom:timeline.item title="B" state="pending" /></atom:timeline>');

        expect(domQuery($html, '//li[1]//*[@data-atom-timeline-marker]//*[contains(@class, "sr-only")]')[0]->textContent)->toBe('Current')
            ->and(domQuery($html, '//li[2]//*[@data-atom-timeline-marker]//*[contains(@class, "sr-only")]')[0]->textContent)->toBe('Pending')
            // the number itself stays hidden from a screen reader
            ->and(domQuery($html, '//li[1]//*[@data-atom-timeline-marker]//*[@aria-hidden="true"]'))->toHaveCount(1);
    });

    it('puts the time and the chevron in one group at the far end of every foldable step, whether or not it has a time', function () {
        $html = renderBlade('<atom:timeline kind="step">
            <atom:timeline.item title="A" state="done" time="2s">x</atom:timeline.item>
            <atom:timeline.item title="B" state="pending">y</atom:timeline.item>
        </atom:timeline>');

        foreach ([1, 2] as $i) {
            $end = domQuery($html, '//li['.$i.']//button//*[@data-atom-timeline-end]');

            expect($end)->toHaveCount(1)
                ->and(domClasses($end[0]))->toContain('ms-auto')
                ->and(domQuery($html, '//li['.$i.']//button//*[@data-atom-timeline-end]//*[@data-atom-timeline-chevron]'))->toHaveCount(1);
        }

        expect(domQuery($html, '//li[2]//*[@data-atom-timeline-time]'))->toBeEmpty();
    });

    it('draws no end group for a step with neither a time nor a fold', function () {
        expect(domQuery(renderBlade('<atom:timeline kind="step"><atom:timeline.item title="A" /></atom:timeline>'), '//*[@data-atom-timeline-end]'))->toBeEmpty();
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

        expect(domClasses($bubble))->toContain('text-base', 'max-w-[85%]', '[overflow-wrap:anywhere]');
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


describe('path', function () {
    it('is one segment per item in a bordered strip that stacks until its own box is wide enough', function () {
        $html = renderBlade('<atom:timeline kind="path"><atom:timeline.item title="Opened" state="done" /><atom:timeline.item title="Paid" /></atom:timeline>');

        expect(domClasses(domQuery($html, '//*[@data-atom-timeline]')[0]))->toContain('@container', 'w-full')
            ->and(domClasses(domQuery($html, '//*[@data-atom-timeline]/ol')[0]))->toContain('grid', 'rounded-panel', 'border', '@2xl:grid-flow-col', '@2xl:auto-cols-[minmax(0,1fr)]')
            ->and(domQuery($html, '//ol/li[@data-atom-timeline-item]'))->toHaveCount(2);
    });

    it('does not make the other kinds a container', function (string $kind) {
        expect(domClasses(domQuery(renderBlade('<atom:timeline kind="'.$kind.'" />'), '//*[@data-atom-timeline]')[0]))->not->toContain('@container');
    })->with(['event', 'step', 'message']);

    it('draws a title, a time and a person, and marks the state in words and in the tint', function () {
        $html = renderBlade('<atom:timeline kind="path">
            <atom:timeline.item title="Opened" state="done" time="08:54" actor="Ana" />
            <atom:timeline.item title="In progress" state="current" time="1h 12m" actor="Ben" />
            <atom:timeline.item title="Invoiced" state="failed" />
            <atom:timeline.item title="Paid" />
        </atom:timeline>');
        $items = domQuery($html, '//li[@data-atom-timeline-item]');

        expect(array_map(fn ($li) => $li->getAttribute('data-state'), $items))->toBe(['done', 'current', 'failed', 'pending'])
            ->and(trim(domQuery($html, '//li[1]//*[@data-atom-timeline-title]')[0]->textContent))->toBe('Opened')
            ->and(preg_replace('/\s+/', ' ', trim(domQuery($html, '//li[1]//*[@data-atom-timeline-meta]')[0]->textContent)))->toBe('08:54 · Ana')
            ->and(preg_replace('/\s+/', ' ', trim(domQuery($html, '//li[2]//*[@data-atom-timeline-meta]')[0]->textContent)))->toBe('1h 12m · Ben')
            // only the current segment is tinted and carries aria-current
            ->and(domQuery($html, '//li[@aria-current="step"]'))->toHaveCount(1)
            ->and(domClasses($items[1]))->toContain('bg-[color-mix(in_oklab,var(--color-accent)_7%,var(--atom-surface))]')
            ->and(implode(' ', domClasses($items[0])))->not->toContain('color-mix')
            // a segment with no time and no person prints no meta line, and never a dash
            ->and(domQuery($html, '//li[4]//*[@data-atom-timeline-meta]'))->toBeEmpty()
            ->and($items[3]->textContent)->not->toContain('—')
            ->and(domQuery($html, '//li[1]//*[@data-atom-timeline-marker]//*[@class="sr-only"]')[0]->textContent)->toBe('Done')
            ->and(domQuery($html, '//li[2]//*[@data-atom-timeline-marker]//*[@class="sr-only"]')[0]->textContent)->toBe('Current')
            ->and(domQuery($html, '//li[3]//*[@data-atom-timeline-marker]//*[@class="sr-only"]')[0]->textContent)->toBe('Failed')
            ->and(domQuery($html, '//li[4]//*[@data-atom-timeline-marker]//*[@class="sr-only"]')[0]->textContent)->toBe('Pending');
    });

    it('tints the current segment in the status\'s tone, and in a light wash of the accent without one', function (?string $tone, string $bg, string $ring, string $dot) {
        $attr = $tone ? ' tone="'.$tone.'"' : '';
        $html = renderBlade('<atom:timeline kind="path"><atom:timeline.item title="A" state="done" /><atom:timeline.item title="B" state="current"'.$attr.' /><atom:timeline.item title="C"'.$attr.' /></atom:timeline>');
        $items = domQuery($html, '//li');

        expect(domClasses($items[1]))->toContain($bg)
            ->and(domClasses(domQuery($html, '//li[2]//*[@data-atom-timeline-marker]')[0]))->toContain('border-2', $ring)
            ->and(domClasses(domQuery($html, '//li[2]//*[@data-atom-timeline-marker]/span[@aria-hidden]')[0]))->toContain($dot)
            // the tone is the current segment's: a done or a pending one stays plain
            ->and(implode(' ', domClasses($items[0])))->not->toContain('bg-')
            ->and(implode(' ', domClasses($items[2])))->not->toContain('bg-');
    })->with([
        'none' => [null, 'bg-[color-mix(in_oklab,var(--color-accent)_7%,var(--atom-surface))]', 'border-accent-ink', 'bg-accent-ink'],
        'neutral' => ['neutral', 'bg-[color-mix(in_oklab,var(--color-accent)_7%,var(--atom-surface))]', 'border-accent-ink', 'bg-accent-ink'],
        'info' => ['info', 'bg-info-bg', 'border-info-fg', 'bg-info-fg'],
        'warning' => ['warning', 'bg-warning-bg', 'border-warning-fg', 'bg-warning-fg'],
        'danger' => ['danger', 'bg-danger-bg', 'border-danger-fg', 'bg-danger-fg'],
        'success' => ['success', 'bg-success-bg', 'border-success-fg', 'bg-success-fg'],
    ]);

    it('falls back to pending for an unknown state, and is not foldable', function () {
        $html = renderBlade('<atom:timeline kind="path"><atom:timeline.item title="A" state="nope">Body</atom:timeline.item></atom:timeline>');

        expect(domQuery($html, '//li')[0]->getAttribute('data-state'))->toBe('pending')
            ->and(domQuery($html, '//*[@data-atom-timeline-toggle]'))->toBeEmpty()
            ->and($html)->not->toContain('x-data');
    });

    it('prints a DateTimeInterface time in a <time> element', function () {
        $html = renderBlade('<atom:timeline kind="path"><atom:timeline.item title="A" :time="new DateTimeImmutable(\'2026-10-02 09:00:00\')" /></atom:timeline>');

        expect(domQuery($html, '//li//time[@datetime]'))->toHaveCount(1);
    });
});


describe('long text', function () {
    it('breaks an unbroken string anywhere, in every kind, so a narrow box never grows', function () {
        $long = str_repeat('x', 400);
        $html = renderBlade('
            <atom:timeline><atom:timeline.item title="'.$long.'" body="'.$long.'" actor="'.$long.'" time="'.$long.'"><x-slot:detail>'.$long.'</x-slot:detail></atom:timeline.item></atom:timeline>
            <atom:timeline kind="step"><atom:timeline.item title="A" state="current">'.$long.'</atom:timeline.item></atom:timeline>
            <atom:timeline kind="path"><atom:timeline.item title="A" actor="'.$long.'">'.$long.'</atom:timeline.item></atom:timeline>
            <atom:timeline kind="message"><atom:timeline.item speaker="'.$long.'" body="'.$long.'" /></atom:timeline>');

        foreach (['event' => ['title', 'body', 'meta', 'detail'], 'step' => ['body'], 'path' => ['meta', 'body'], 'message' => ['bubble']] as $kind => $parts) {
            foreach ($parts as $part) {
                $found = domQuery($html, '//*[@data-kind="'.$kind.'"]//*[@data-atom-timeline-'.$part.']');

                expect($found)->not->toBeEmpty("$kind $part")
                    ->and(domClasses($found[0]))->toContain('[overflow-wrap:anywhere]');
            }
        }
    });
});
