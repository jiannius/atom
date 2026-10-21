<?php

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Carbon as IlluminateCarbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Jiannius\Atom\Services\Calendar;
use Jiannius\Atom\Services\Carbon as AtomCarbon;

describe('the date', function () {
    afterEach(fn () => Date::useDefault());

    it('is an Illuminate date until a host opts in', function () {
        expect(now())->toBeInstanceOf(IlluminateCarbon::class)
            ->and(now())->not->toBeInstanceOf(AtomCarbon::class);
    });

    it('is atom\'s immutable date once a host asks for it', function () {
        Date::use(AtomCarbon::class);

        expect(now())->toBeInstanceOf(AtomCarbon::class)
            ->and(now()->pretty())->toBeString()
            ->and(now()->local())->toBeInstanceOf(AtomCarbon::class);
    });

    it('does not give the Illuminate date atom\'s methods', function () {
        expect(method_exists(now(), 'pretty'))->toBeFalse()
            ->and(method_exists(now(), 'local'))->toBeFalse();
    });

    it('lets the calendar read the viewer\'s now whichever date the host uses', function () {
        expect(Calendar::now())->toBeInstanceOf(AtomCarbon::class);

        Date::use(AtomCarbon::class);

        expect(Calendar::now())->toBeInstanceOf(AtomCarbon::class);
    });

    it('keeps the calendar\'s now in the viewer\'s zone', function () {
        // the signed-in user's timezone outranks the TIMEZONE environment variable, so the zone is pinned here
        Auth::setUser(new GenericUser(['id' => 1, 'timezone' => 'Asia/Kuala_Lumpur']));
        IlluminateCarbon::setTestNow('2026-10-09 20:00:00 UTC');

        try {
            config(['app.timezone' => 'UTC']);

            expect(Calendar::timezone())->toBe('Asia/Kuala_Lumpur')
                ->and(Calendar::now()->format('Y-m-d H:i'))->toBe('2026-10-10 04:00');
        } finally {
            IlluminateCarbon::setTestNow();
            Auth::forgetUser();
        }
    });

    it('prints a date differently until the host opts in: ISO with a Z for atom\'s, Y-m-d H:i:s for Illuminate\'s', function () {
        $illuminate = IlluminateCarbon::parse('2026-03-01 10:00:00', 'UTC');
        $atom = AtomCarbon::parse('2026-03-01 10:00:00', 'UTC');

        expect((string) $illuminate)->toBe('2026-03-01 10:00:00')
            ->and((string) $atom)->toBe('2026-03-01T10:00:00Z')
            ->and(json_encode($illuminate))->toBe(json_encode($atom));
    });
});

describe('breakdown()', function () {
    beforeEach(function () {
        // the macro writes MySQL; teach the test database the three functions it calls
        $pdo = DB::connection()->getPdo();
        $pdo->sqliteCreateFunction('year', fn ($date) => (int) substr($date, 0, 4));
        $pdo->sqliteCreateFunction('day', fn ($date) => (int) substr($date, 8, 2));
        $pdo->sqliteCreateFunction('date_format', fn ($date) => (string) (int) substr($date, 5, 2));

        Schema::create('breakdown_sales', function ($table) {
            $table->id();
            $table->date('date');
            $table->integer('total');
        });

        DB::table('breakdown_sales')->insert([
            ['date' => '2026-03-02', 'total' => 10],
            ['date' => '2026-03-02', 'total' => 5],
            ['date' => '2026-03-04', 'total' => 7],
        ]);
    });

    it('breaks a plain Illuminate date down by day', function () {
        $start = IlluminateCarbon::parse('2026-03-01 00:00:00', 'UTC');

        $breakdown = DB::table('breakdown_sales')->breakdown(['y' => 0, 'm' => 0, 'd' => 4], $start);

        expect($breakdown->all())->toBe([1 => 0, 2 => 15, 3 => 0, 4 => 7, 5 => 0]);
    });

    it('breaks a plain Illuminate date down by month and year', function () {
        $byMonth = DB::table('breakdown_sales')->breakdown(['y' => 0, 'm' => 3, 'd' => 0], IlluminateCarbon::parse('2026-02-01', 'UTC'));
        $byYear = DB::table('breakdown_sales')->breakdown(['y' => 2, 'm' => 0, 'd' => 0], IlluminateCarbon::parse('2025-01-01', 'UTC'));

        expect($byMonth->all())->toBe(['Feb 26' => 0, 'Mar 26' => 22, 'Apr 26' => 0, 'May 26' => 0])
            ->and($byYear->all())->toBe([2025 => 0, 2026 => 22, 2027 => 0]);
    });

    it('does not move the date the caller passed in', function () {
        $start = IlluminateCarbon::parse('2026-03-01 00:00:00', 'UTC');

        DB::table('breakdown_sales')->breakdown(['y' => 0, 'm' => 0, 'd' => 4], $start);

        expect($start->toDateTimeString())->toBe('2026-03-01 00:00:00');
    });

    it('still takes atom\'s date', function () {
        $breakdown = DB::table('breakdown_sales')->breakdown(['y' => 0, 'm' => 0, 'd' => 4], AtomCarbon::parse('2026-03-01 00:00:00', 'UTC'));

        expect($breakdown->all())->toBe([1 => 0, 2 => 15, 3 => 0, 4 => 7, 5 => 0]);
    });
});
