<?php

use Illuminate\Support\Facades\Blade;

describe('avatar', function () {
    it('renders an image with an alt drawn from the name', function () {
        $html = Blade::render('<atom:avatar name="John Doe" src="https://cdn.test/a.jpg" />');

        expect($html)
            ->toContain('data-atom-avatar')
            ->toContain('src="https://cdn.test/a.jpg"')
            ->toContain('alt="John Doe"');
    });

    it('falls back to initials when there is no image', function () {
        $html = Blade::render('<atom:avatar name="John Doe" />');

        expect($html)->toContain('JD');
    });

    it('takes a single initial at the small sizes', function () {
        $html = Blade::render('<atom:avatar name="John Doe" size="sm" />');

        expect($html)->toContain('J')->not->toContain('JD');
    });

    it('collapses overflow into a +N counter', function () {
        $html = Blade::render(<<<'BLADE'
            <atom:avatar.group max="2">
                <atom:avatar name="Anna" />
                <atom:avatar name="Beth" />
                <atom:avatar name="Cara" />
            </atom:avatar.group>
        BLADE);

        expect($html)->toContain('+1');
    });
});

describe('badge', function () {
    it('renders a named-colour badge with its label', function () {
        $html = Blade::render('<atom:badge color="green" label="Active" />');

        expect($html)
            ->toContain('data-atom-badge')
            ->toContain('Active')
            ->toContain('bg-green-100');
    });

    it('renders a hex-colour badge as inline styles', function () {
        $html = Blade::render('<atom:badge color="#ff0000" label="Custom" />');

        expect($html)
            ->toContain('data-atom-badge')
            ->toContain('Custom')
            ->toContain('color: #ff0000');
    });

    it('groups badges with an overflow counter', function () {
        $html = Blade::render(<<<'BLADE'
            <atom:badge.group max="2">
                <atom:badge label="One" />
                <atom:badge label="Two" />
                <atom:badge label="Three" />
            </atom:badge.group>
        BLADE);

        expect($html)->toContain('data-atom-badge-group');
    });
});

describe('callout', function () {
    it('renders a variant with heading and content', function () {
        $html = Blade::render('<atom:callout variant="info" heading="Heads up" content="Body text" />');

        expect($html)
            ->toContain('Heads up')
            ->toContain('Body text')
            ->toContain('bg-sky-100');
    });

    it('labels the dismiss button when closeable', function () {
        $html = Blade::render('<atom:callout heading="Hi" closeable />');

        expect($html)
            ->toContain('aria-label="Dismiss"')
            ->toContain('show = false');
    });

    it('has no dismiss button by default', function () {
        $html = Blade::render('<atom:callout heading="Hi" />');

        expect($html)->not->toContain('aria-label="Dismiss"');
    });
});

describe('embed', function () {
    it('renders an image source', function () {
        $html = Blade::render('<atom:embed src="https://cdn.test/photo.jpg" />');

        expect($html)->toContain('<img')->toContain('https://cdn.test/photo.jpg');
    });

    it('titles the youtube iframe for screen readers', function () {
        $html = Blade::render('<atom:embed src="https://www.youtube.com/watch?v=abc" />');

        expect($html)
            ->toContain('<iframe')
            ->toContain('title="Embedded video"');
    });

    it('falls back to an icon without erroring on a null source', function () {
        $html = Blade::render('<atom:embed />');

        // Regression: parse_url(null) raised a deprecation + undefined "path" index.
        expect($html)->toContain('text-muted');
    });
});

describe('list', function () {
    it('renders a heading and its items', function () {
        $html = Blade::render(<<<'BLADE'
            <atom:list heading="Files">
                <atom:list.item>Readme</atom:list.item>
            </atom:list>
        BLADE);

        expect($html)
            ->toContain('data-atom-list')
            ->toContain('Files')
            ->toContain('data-atom-list-item')
            ->toContain('Readme');
    });

    it('exposes the remove control as a labelled button', function () {
        $html = Blade::render('<atom:list.item wire:remove="remove">Item</atom:list.item>');

        // Regression: the remove control was a click-only <div> (no keyboard, no name).
        expect($html)
            ->toContain('aria-label="Remove"')
            ->toContain('type="button"')
            ->toContain("\$dispatch('remove')");
    });
});

describe('dd', function () {
    it('renders the label/value pair', function () {
        $html = Blade::render('<atom:dd label="Name">Jane</atom:dd>');

        expect($html)
            ->toContain('data-atom-dd')
            ->toContain('Name')
            ->toContain('Jane');
    });

    it('shows the filler when empty', function () {
        $html = Blade::render('<atom:dd label="Empty" />');

        expect($html)->toContain('--');
    });

    it('lays the group out in columns', function () {
        $html = Blade::render('<atom:dd.group cols="2"><atom:dd label="A">1</atom:dd></atom:dd.group>');

        expect($html)
            ->toContain('data-atom-dd-group')
            ->toContain('md:grid-cols-2');
    });
});

describe('placeholder-bar', function () {
    it('parses an "WxH" size into pixels', function () {
        $html = Blade::render('<atom:placeholder-bar size="200x50" />');

        expect($html)->toContain('width: 200px')->toContain('height: 50px');
    });

    it('keeps a percentage width verbatim, with a pixel height', function () {
        // Regression: the table skeleton fed "45%/x/10" (wrong delimiter), which
        // split to "45%/" + "/10" and rendered the invalid "width: 45%/px".
        $html = Blade::render('<atom:placeholder-bar size="45%x10" />');

        expect($html)
            ->toContain('width: 45%')
            ->toContain('height: 10px')
            ->not->toContain('45%/px');
    });
});

describe('skeleton', function () {
    it('renders the pulsing placeholder', function () {
        $html = Blade::render('<atom:skeleton />');

        expect($html)->toContain('animate-pulse');
    });
});

describe('profile', function () {
    it('renders the name, email and avatar', function () {
        $html = Blade::render('<atom:profile name="Jane Roe" email="jane@test.com" />');

        expect($html)
            ->toContain('data-atom-profile')
            ->toContain('Jane Roe')
            ->toContain('jane@test.com')
            ->toContain('data-atom-avatar');
    });
});

describe('logo', function () {
    it('renders the bundled fallback mark', function () {
        $html = Blade::render('<atom:logo />');

        expect($html)->toContain('<svg')->toContain('viewBox="0 0 40 42"');
    });
});

describe('copy', function () {
    it('wires the clipboard value', function () {
        $html = Blade::render('<atom:copy value="hello" />');

        expect($html)->toContain('hello')->toContain('$clipboard');
    });
});

describe('darkmode-toggle', function () {
    it('labels the toggle for dark mode, not the sidebar', function () {
        $html = Blade::render('<atom:darkmode-toggle />');

        // Regression: the label and data hook were copy-pasted from the sidebar toggle.
        expect($html)
            ->toContain('aria-label="Toggle dark mode"')
            ->toContain('data-atom-darkmode-toggle')
            ->not->toContain('Toggle sidebar')
            ->not->toContain('data-atom-sidebar-toggle');
    });

    it('offers light, dark and system options', function () {
        $html = Blade::render('<atom:darkmode-toggle />');

        expect($html)
            ->toContain('Light')
            ->toContain('Dark')
            ->toContain('System');
    });
});

describe('sharer', function () {
    it('renders share targets as labelled buttons', function () {
        $html = Blade::render('<atom:sharer url="https://example.com" title="Hi" />');

        // Regression: share + copy-link controls were click-only <div>s.
        expect($html)
            ->toContain('type="button"')
            ->toContain('data-sharer="facebook"')
            ->toContain('aria-label="Facebook"')
            ->toContain('aria-label="Copy Link"');
    });
});

describe('lightbox', function () {
    it('labels the close and navigation controls', function () {
        $html = Blade::render('<atom:lightbox />');

        // Regression: close/prev/next were click-only <div>s with no accessible name.
        expect($html)
            ->toContain('data-atom-lightbox')
            ->toContain('aria-label="Close"')
            ->toContain('aria-label="Previous"')
            ->toContain('aria-label="Next"')
            ->toContain('type="button"');
    });

    it('binds arrow keys to gallery navigation', function () {
        $html = Blade::render('<atom:lightbox />');

        expect($html)
            ->toContain('keydown.left')
            ->toContain('keydown.right');
    });
});
