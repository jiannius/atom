<?php

use Jiannius\Atom\Services\DesignCheck;
use Jiannius\Atom\Tests\Support\DesignHost;

/**
 * The notes and messages of the page and header checks. What each finds is held by its fixture host
 * (DesignCheckFixturesTest); these hold what it says, its severity and the corners a fixture line cannot show.
 */

describe('page and header: the severities and groups', function () {
    it('is review for the four layout rules, info for the two nits and break for the persisted trail', function (string $id, string $severity) {
        $rule = (new DesignCheck)->rules()[$id];

        expect($rule::SEVERITY)->toBe($severity)->and($rule::GROUP)->toBe('Page and header');
    })->with([
        ['page-header', DesignCheck::REVIEW],
        ['hand-crumbs', DesignCheck::REVIEW],
        ['own-sidebar-toggle', DesignCheck::REVIEW],
        ['hand-rail-grid', DesignCheck::REVIEW],
        ['page-tabs', DesignCheck::INFO],
        ['page-block-margin', DesignCheck::INFO],
        ['persisted-static-crumbs', DesignCheck::BREAK],
    ]);

    it('runs the persisted trail on a view read as text only, and no other of them', function () {
        $rules = (new DesignCheck)->rules();

        expect($rules['persisted-static-crumbs']->structural())->toBeFalse();

        foreach (['page-header', 'hand-crumbs', 'own-sidebar-toggle', 'page-tabs', 'hand-rail-grid', 'page-block-margin'] as $id) {
            expect($rules[$id]->structural())->toBeTrue();
        }
    });
});

describe('page-header', function () {
    it('says what is missing, and tells a page with a hand title about the title and not twice', function () {
        expect(DesignHost::notes("<atom:page>\n<atom:callout>a</atom:callout>\n</atom:page>", 'page-header'))
            ->toBe([1 => 'This page has no `<atom:page.header>`'])
            ->and(DesignHost::notes("<atom:page>\n<div><h1>Jobs</h1></div>\n</atom:page>", 'page-header'))
            ->toBe([2 => 'A hand-built page title: use `<atom:page.header title="...">`']);
    });

    it('is told by the page that has no header, per page, when the file has none anywhere', function () {
        $source = "<atom:page>\n<atom:callout>a</atom:callout>\n</atom:page>\n<atom:page>\n<atom:page.header title=\"a\"/>\n</atom:page>\n";

        expect(DesignHost::notes($source, 'page-header'))->toBe([]);
    });

    it('declines a page whose first element may draw the header', function () {
        foreach (['@include(\'a\')<div/>', '<x-a.header/><div/>', '<livewire:a/><div/>', '@livewire(\'a\')<div/>', '{{ $slot }}<div/>', '{!! $head !!}<div/>', '@includeIf(\'a\')<div/>'] as $opening) {
            expect(DesignHost::notes("<atom:page>{$opening}</atom:page>", 'page-header'))->toBe([], $opening);
        }
    });

    it('reads a heading as big as a title: lg, xl, or 18 and more, and not a bound size', function () {
        foreach (['size="lg"' => 1, 'size="xl"' => 1, 'size="18"' => 1, 'size="40"' => 1, 'size="17"' => 0, 'size="sm"' => 0, ':size="$s"' => 0, '' => 0] as $size => $count) {
            expect(DesignHost::scanSource("<atom:page><atom:page.header title=\"a\"/><atom:heading {$size}>Jobs</atom:heading></atom:page>", 'page-header'))->toHaveCount($count, $size);
        }
    });
});

describe('hand-crumbs', function () {
    it('names what it saw', function () {
        expect(DesignHost::notes('<nav aria-label="Breadcrumb"><a>a</a></nav>', 'hand-crumbs'))->toBe([1 => 'a `<nav>` labelled as a breadcrumb'])
            ->and(DesignHost::notes('<div><a href="/a">a</a>›<a href="/b">b</a></div>', 'hand-crumbs'))->toBe([1 => 'links with a separator between them']);
    });

    it('is one finding for a nav and the links inside it', function () {
        expect(DesignHost::scanSource('<nav aria-label="Breadcrumb"><a href="/a">a</a><atom:icon.right/><a href="/b">b</a></nav>', 'hand-crumbs'))->toHaveCount(1);
    });

    it('needs a third link before a slash is a trail', function () {
        expect(DesignHost::scanSource('<div><a href="/a">a</a>/<a href="/b">b</a></div>', 'hand-crumbs'))->toHaveCount(0)
            ->and(DesignHost::scanSource('<div><a href="/a">a</a>/<a href="/b">b</a>/<a href="/c">c</a></div>', 'hand-crumbs'))->toHaveCount(1);
    });
});

describe('own-sidebar-toggle', function () {
    it('says whether the tag sends the event, listens for it or is drawn as the icon', function () {
        expect(DesignHost::notes('<button x-on:click="$dispatch(\'toggle-sidebar\')">a</button>', 'own-sidebar-toggle'))->toBe([1 => 'sends `toggle-sidebar`'])
            ->and(DesignHost::notes('<div x-on:toggle-sidebar.window="a()"></div>', 'own-sidebar-toggle'))->toBe([1 => 'listens for `toggle-sidebar`'])
            ->and(DesignHost::notes('<atom:button icon="panel-left"/>', 'own-sidebar-toggle'))->toBe([1 => 'a button with `icon="panel-left"`'])
            ->and(DesignHost::notes('<atom:button><atom:icon.panel-left-open/></atom:button>', 'own-sidebar-toggle'))->toBe([1 => 'a button drawn as the panel icon']);
    });

    it('is one finding for a tag that does two of them', function () {
        expect(DesignHost::scanSource('<atom:button icon="panel-left" x-on:click="$dispatch(\'toggle-sidebar\')"/>', 'own-sidebar-toggle'))->toHaveCount(1);
    });
});

describe('page-block-margin', function () {
    it('names the utility and where it sits', function () {
        expect(DesignHost::notes('<atom:page><div class="mt-6"/></atom:page>', 'page-block-margin'))->toBe([1 => '`mt-6` on a block of `<atom:page>`'])
            ->and(DesignHost::notes('<atom:page><atom:page.columns><div class="lg:-mb-2"/></atom:page.columns></atom:page>', 'page-block-margin'))->toBe([1 => '`-mb-2` on a block of the main column'])
            ->and(DesignHost::notes('<atom:page.columns><x-slot:rail><atom:card class="my-3"/></x-slot:rail></atom:page.columns>', 'page-block-margin'))->toBe([1 => '`my-3` on a card of the rail'])
            ->and(DesignHost::notes('<atom:page class="space-y-4"></atom:page>', 'page-block-margin'))->toBe([1 => '`space-y-4` on `<atom:page>`']);
    });

    it('does not take a margin that adds no room, or one on another slot', function () {
        expect(DesignHost::scanSource('<atom:page><div class="m-0 mt-0 mb-auto my-0"/><x-slot:other><div class="mt-4"/></x-slot:other></atom:page>', 'page-block-margin'))->toBe([]);
    });
});

describe('hand-rail-grid', function () {
    it('reads the track in px or rem, and calls a narrow one a column of figures', function () {
        foreach (['grid-cols-[1fr_12rem]' => 1, 'grid-cols-[1fr_192px]' => 1, 'grid-cols-[1fr_191px]' => 0, 'grid-cols-[minmax(0,1fr)_11.9rem]' => 0, 'grid-cols-[320px_1fr]' => 1, 'lg:grid-cols-[320px_minmax(0,1fr)]' => 1, 'grid-cols-[1fr_20em]' => 0, 'grid-cols-[1fr_auto]' => 0] as $class => $count) {
            expect(DesignHost::scanSource("<atom:page><div class=\"{$class}\"/></atom:page>", 'hand-rail-grid'))->toHaveCount($count, $class);
        }
    });
});

describe('persisted-static-crumbs', function () {
    it('says why it matters', function () {
        expect(DesignHost::notes("@persist('b')<atom:breadcrumbs :items=\"\$t\"/>@endpersist", 'persisted-static-crumbs'))->toBe([1 => 'inside `@persist`: it would keep the first page\'s crumbs']);
    });

    it('is one finding when the trail sits in two persists, and none for a persist that never closes or an escaped one', function () {
        expect(DesignHost::scanSource("@persist('a')@persist('b')<atom:breadcrumbs :items=\"\$t\"/>@endpersist@endpersist", 'persisted-static-crumbs'))->toHaveCount(1)
            ->and(DesignHost::scanSource("@persist('a')<atom:breadcrumbs :items=\"\$t\"/>", 'persisted-static-crumbs'))->toBe([])
            ->and(DesignHost::scanSource("@@persist('a')<atom:breadcrumbs :items=\"\$t\"/>@endpersist", 'persisted-static-crumbs'))->toBe([]);
    });
});
