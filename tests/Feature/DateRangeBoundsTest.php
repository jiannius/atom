<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Jiannius\Atom\Services\Carbon;
use Jiannius\Atom\Services\DateRange;

/**
 * A date-only picker writes `2026-09-01 to 2026-09-30` (#218). A bare `Y-m-d` end is a whole calendar day in the viewer's timezone:
 * against a DATE column a date comparison, against a DATETIME or TIMESTAMP column (stored in the app's timezone) the half-open interval
 * from the viewer's midnight of the first day up to the viewer's midnight after the last. An ISO instant is read as it always was.
 */
class DateRangeRow extends Model
{
    protected $table = 'range_rows';

    protected $guarded = [];

    public $timestamps = false;
}

/** The columns `show columns` would report (MySQL only), seeded into the cache `tableColumns()` reads so sqlite can be asked too. */
function seedRangeColumns(): void
{
    config()->set('cache.default', 'array');

    cache()->put('table_range_rows_columns', [
        ['Field' => 'id', 'Type' => 'integer'],
        ['Field' => 'name', 'Type' => 'varchar(255)'],
        ['Field' => 'day', 'Type' => 'date'],
        ['Field' => 'at', 'Type' => 'datetime'],
        ['Field' => 'at6', 'Type' => 'datetime(6)'],
        ['Field' => 'stamp', 'Type' => 'timestamp'],
    ], now()->addDays(7));
}

/** A query on a MySQL grammar: compiling needs no server, and it quotes with backticks. */
function mysqlRows(): \Illuminate\Database\Eloquent\Builder
{
    seedRangeColumns();

    config()->set('database.connections.mysql_dry', [
        'driver' => 'mysql', 'host' => '127.0.0.1', 'database' => 'dry', 'username' => 'dry', 'password' => '',
    ]);

    return DateRangeRow::on('mysql_dry');
}

/** Sign in someone whose `timezone` is the viewer's, and set the zone instants are stored in. */
function viewer(?string $timezone, string $app = 'UTC'): void
{
    config()->set('app.timezone', $app);

    if ($timezone) {
        Auth::setUser((new User)->forceFill(['timezone' => $timezone]));
    }
}

/** The bindings of a query, an instant as the wall clock Laravel writes (the grammar's own date format). */
function wall(\Illuminate\Database\Eloquent\Builder $query): array
{
    return array_map(fn ($binding) => $binding instanceof DateTimeInterface ? $binding->format('Y-m-d H:i:s') : $binding, $query->getBindings());
}

/** The four days around the range, the datetime rows at each viewer-day's first and last minute. */
function seedAround(string $viewer, string $app): void
{
    foreach (['2026-08-31', '2026-09-01', '2026-09-30', '2026-10-01'] as $date) {
        DB::table('range_rows')->insert(['name' => $date.' date', 'day' => $date, 'at' => null]);

        foreach (['00:00:00', '23:59:59'] as $time) {
            DB::table('range_rows')->insert([
                'name' => "$date $time",
                'day' => null,
                'at' => Carbon::parse("$date $time", $viewer)->setTimezone($app)->format('Y-m-d H:i:s'),
            ]);
        }
    }
}

it('reads a bare date as a date, and nothing else', function () {
    foreach (['2026-09-01', '2024-02-29', '1000-01-01', '9998-12-31'] as $date) {
        expect(DateRange::isDate($date))->toBeTrue($date);
    }

    foreach (['2026-09-01T00:00:00Z', '2026-09-01 00:00:00', ' 2026-09-01', '2026-09-01 ', "2026-09-01\n", '2026-02-30', '2026-13-01', '9999-12-31', '0999-01-01', '26-09-01', '', null, 20260901, ['2026-09-01'], new DateTimeImmutable('2026-09-01')] as $value) {
        expect(DateRange::isDate($value))->toBeFalse(var_export($value, true));
    }
});

it('splits a range by "to" or a list', function () {
    expect(DateRange::split('2026-09-01 to 2026-09-30'))->toBe(['2026-09-01', '2026-09-30'])
        ->and(DateRange::split('2026-09-01 to'))->toBe(['2026-09-01', ''])
        ->and(DateRange::split(['2026-09-01', '2026-09-30']))->toBe(['2026-09-01', '2026-09-30'])
        ->and(DateRange::split([]))->toBe(['', ''])
        ->and(DateRange::split(null))->toBe(['', '']);
});

describe('filter()', function () {
    it('compares a DATE column as dates, as it always did', function () {
        viewer('Asia/Kuala_Lumpur');
        $query = mysqlRows()->filter(['day' => '2026-09-01 to 2026-09-30']);

        expect($query->toSql())->toBe('select * from `range_rows` where date(`range_rows`.`day`) >= ? and date(`range_rows`.`day`) <= ?')
            ->and($query->getBindings())->toBe(['2026-09-01', '2026-09-30']);
    });

    it('reads a bare date as a whole day on a DATETIME or TIMESTAMP column', function (string $column) {
        viewer('Asia/Kuala_Lumpur');
        $query = mysqlRows()->filter([$column => '2026-09-01 to 2026-09-30']);

        expect($query->toSql())->toBe('select * from `range_rows` where `range_rows`.`'.$column.'` >= ? and `range_rows`.`'.$column.'` < ?')
            ->and(wall($query))->toBe(['2026-08-31 16:00:00', '2026-09-30 16:00:00']);
    })->with(['datetime' => 'at', 'timestamp' => 'stamp']);

    it('is the viewer\'s days, in the zone the instants are stored in', function (?string $viewer, string $app, string $from, string $to) {
        viewer($viewer, $app);
        $query = mysqlRows()->filter(['at' => '2026-09-01 to 2026-09-30']);

        expect(wall($query))->toBe([$from, $to]);
    })->with([
        'Kuala Lumpur viewer, UTC app' => ['Asia/Kuala_Lumpur', 'UTC', '2026-08-31 16:00:00', '2026-09-30 16:00:00'],
        'UTC viewer, UTC app' => ['UTC', 'UTC', '2026-09-01 00:00:00', '2026-10-01 00:00:00'],
        'New York viewer, UTC app' => ['America/New_York', 'UTC', '2026-09-01 04:00:00', '2026-10-01 04:00:00'],
        'Kuala Lumpur viewer, Kuala Lumpur app' => ['Asia/Kuala_Lumpur', 'Asia/Kuala_Lumpur', '2026-09-01 00:00:00', '2026-10-01 00:00:00'],
        'New York viewer, Kuala Lumpur app' => ['America/New_York', 'Asia/Kuala_Lumpur', '2026-09-01 12:00:00', '2026-10-01 12:00:00'],
        'UTC viewer, Kuala Lumpur app' => ['UTC', 'Asia/Kuala_Lumpur', '2026-09-01 08:00:00', '2026-10-01 08:00:00'],
        'Kiritimati viewer, UTC app' => ['Pacific/Kiritimati', 'UTC', '2026-08-31 10:00:00', '2026-09-30 10:00:00'],
        'Pago Pago viewer, UTC app' => ['Pacific/Pago_Pago', 'UTC', '2026-09-01 11:00:00', '2026-10-01 11:00:00'],
    ]);

    it('falls back to atom.timezone for a viewer with no zone of their own', function () {
        config()->set('atom.timezone', 'America/New_York');
        viewer(null);

        expect(wall(mysqlRows()->filter(['at' => '2026-09-01 to 2026-09-30'])))->toBe(['2026-09-01 04:00:00', '2026-10-01 04:00:00']);
    });

    it('works out the day after on the date, so a day with a clock change is its real length', function () {
        viewer('America/New_York');

        // 1 Nov 2026: the clocks go back, a 25-hour day. EDT (UTC-4) at its start, EST (UTC-5) at the start of the next.
        expect(wall(mysqlRows()->filter(['at' => '2026-11-01 to 2026-11-01'])))->toBe(['2026-11-01 04:00:00', '2026-11-02 05:00:00']);
        // across the change
        expect(wall(mysqlRows()->filter(['at' => '2026-10-31 to 2026-11-05'])))->toBe(['2026-10-31 04:00:00', '2026-11-06 05:00:00']);
        // 8 Mar 2026: the clocks go forward, a 23-hour day
        expect(wall(mysqlRows()->filter(['at' => '2026-03-08 to 2026-03-08'])))->toBe(['2026-03-08 05:00:00', '2026-03-09 04:00:00']);
    });

    it('takes an open-ended range', function () {
        viewer('Asia/Kuala_Lumpur');

        $from = mysqlRows()->filter(['at' => '2026-09-01 to']);
        $to = mysqlRows()->filter(['at' => 'to 2026-09-30']);

        expect($from->toSql())->toBe('select * from `range_rows` where `range_rows`.`at` >= ?')
            ->and(wall($from))->toBe(['2026-08-31 16:00:00'])
            ->and($to->toSql())->toBe('select * from `range_rows` where `range_rows`.`at` < ?')
            ->and(wall($to))->toBe(['2026-09-30 16:00:00']);
    });

    it('reads each end for what it is, so a date and an instant can meet', function () {
        viewer('Asia/Kuala_Lumpur');

        $mixed = mysqlRows()->filter(['at' => '2026-09-01 to 2026-09-30T15:59:59.999Z']);
        $reverse = mysqlRows()->filter(['at' => '2026-08-31T16:00:00.000Z to 2026-09-30']);

        expect($mixed->toSql())->toBe('select * from `range_rows` where `range_rows`.`at` >= ? and `range_rows`.`at` <= ?')
            ->and(wall($mixed))->toBe(['2026-08-31 16:00:00', '2026-09-30T15:59:59.999Z'])
            ->and($reverse->toSql())->toBe('select * from `range_rows` where `range_rows`.`at` >= ? and `range_rows`.`at` < ?')
            ->and(wall($reverse))->toBe(['2026-08-31T16:00:00.000Z', '2026-09-30 16:00:00']);
    });

    it('compares an instant range exactly as it always did', function (string $column, string $value, string $sql, array $bindings) {
        viewer('Asia/Kuala_Lumpur');
        $query = mysqlRows()->filter([$column => $value]);

        expect($query->toSql())->toBe('select * from `range_rows` where '.$sql)
            ->and($query->getBindings())->toBe($bindings);
    })->with([
        'datetime' => ['at', '2026-08-31T16:00:00.000Z to 2026-09-30T15:59:59.999Z', '`range_rows`.`at` >= ? and `range_rows`.`at` <= ?', ['2026-08-31T16:00:00.000Z', '2026-09-30T15:59:59.999Z']],
        'date' => ['day', '2026-08-31T16:00:00.000Z to 2026-09-30T15:59:59.999Z', 'date(`range_rows`.`day`) >= ? and date(`range_rows`.`day`) <= ?', ['2026-08-31T16:00:00.000Z', '2026-09-30T15:59:59.999Z']],
        'a space-separated instant' => ['at', '2026-09-01 00:00:00 to 2026-09-30 23:59:59', '`range_rows`.`at` >= ? and `range_rows`.`at` <= ?', ['2026-09-01 00:00:00', '2026-09-30 23:59:59']],
        'a single day, with an operator' => ['at:>=', '2026-09-01', '`range_rows`.`at` >= ?', ['2026-09-01']],
    ]);

    it('leaves a date that is not one to the old comparison', function (string $value, array $bindings) {
        viewer('Asia/Kuala_Lumpur');

        expect(wall(mysqlRows()->filter(['at' => $value])))->toBe($bindings);
    })->with([
        'no such day' => ['2026-02-30 to 2026-09-30', ['2026-02-30', '2026-09-30 16:00:00']],
        'no day after' => ['2026-09-01 to 9999-12-31', ['2026-08-31 16:00:00', '9999-12-31']],
    ]);

    it('never throws on whatever shape the range has', function () {
        viewer('Asia/Kuala_Lumpur');

        foreach (['to', ' to ', '2026-09-01 to 2026-09-30 to 2026-10-01', '0000-00-00 to 0000-00-00', "2026-09-01\n to 2026-09-30", '2026-9-1 to 2026-9-30', str_repeat('2026-09-01 to ', 50)] as $value) {
            $query = mysqlRows()->filter(['at' => $value]);

            expect(substr_count($query->toSql(), '?'))->toBe(count($query->getBindings()), $value);
        }
    });
});

describe('whereDateBetween()', function () {
    it('reads a bare date as a whole day on a DATETIME column of an Eloquent builder', function (string $column) {
        viewer('Asia/Kuala_Lumpur');
        $query = mysqlRows()->whereDateBetween($column, '2026-09-01 to 2026-09-30');

        expect($query->toSql())->toBe('select * from `range_rows` where `'.str_replace('.', '`.`', $column).'` >= ? and `'.str_replace('.', '`.`', $column).'` < ?')
            ->and(wall($query))->toBe(['2026-08-31 16:00:00', '2026-09-30 16:00:00']);
    })->with(['bare' => 'at', 'prefixed with its own table' => 'range_rows.at', 'timestamp' => 'stamp', 'datetime(6)' => 'at6']);

    it('takes a list of ends and an open end', function () {
        viewer('America/New_York');

        expect(wall(mysqlRows()->whereDateBetween('at', ['2026-11-01', '2026-11-01'])))->toBe(['2026-11-01 04:00:00', '2026-11-02 05:00:00'])
            ->and(wall(mysqlRows()->whereDateBetween('at', '2026-11-01 to')))->toBe(['2026-11-01 04:00:00'])
            ->and(wall(mysqlRows()->whereDateBetween('at', 'to 2026-11-01')))->toBe(['2026-11-02 05:00:00']);
    });

    it('keeps comparing dates where it cannot tell a datetime', function (callable $make, string $sql) {
        viewer('Asia/Kuala_Lumpur');
        $query = $make();

        expect($query->toSql())->toBe($sql)
            ->and($query->getBindings())->toBe(['2026-09-01', '2026-09-30']);
    })->with([
        'a DATE column' => [fn () => mysqlRows()->whereDateBetween('day', '2026-09-01 to 2026-09-30'), 'select * from `range_rows` where date(`day`) >= ? and date(`day`) <= ?'],
        'another table\'s column' => [fn () => mysqlRows()->whereDateBetween('other.at', '2026-09-01 to 2026-09-30'), 'select * from `range_rows` where date(`other`.`at`) >= ? and date(`other`.`at`) <= ?'],
        'a query builder' => [function () {
            mysqlRows();

            return DB::connection('mysql_dry')->table('range_rows')->whereDateBetween('at', '2026-09-01 to 2026-09-30');
        }, 'select * from `range_rows` where date(`at`) >= ? and date(`at`) <= ?'],
        'a query builder, a column of its table' => [function () {
            mysqlRows();

            return DB::connection('mysql_dry')->table('range_rows')->whereDateBetween('range_rows.at', '2026-09-01 to 2026-09-30');
        }, 'select * from `range_rows` where date(`range_rows`.`at`) >= ? and date(`range_rows`.`at`) <= ?'],
        'a column it does not know' => [fn () => mysqlRows()->whereDateBetween('nope', '2026-09-01 to 2026-09-30'), 'select * from `range_rows` where date(`nope`) >= ? and date(`nope`) <= ?'],
        'a column that is not a plain name' => [fn () => mysqlRows()->whereDateBetween('at) or 1=1 --', '2026-09-01 to 2026-09-30'), 'select * from `range_rows` where date(`at) or 1=1 --`) >= ? and date(`at) or 1=1 --`) <= ?'],
    ]);

    it('keeps comparing dates on a database that cannot say what a column is', function () {
        viewer('Asia/Kuala_Lumpur');
        config()->set('cache.default', 'array');

        // sqlite: no `show columns`, and nothing cached
        $query = DateRangeRow::query()->whereDateBetween('at', '2026-09-01 to 2026-09-30');

        expect($query->toSql())->toContain('strftime')
            ->and($query->getBindings())->toBe(['2026-09-01', '2026-09-30']);
    });

    it('sends no statement to a database that is not MySQL, even with the columns known', function (string $driver) {
        viewer('Asia/Kuala_Lumpur');
        seedRangeColumns();
        config()->set('database.connections.other_dry', ['driver' => $driver, 'host' => '127.0.0.1', 'database' => 'dry', 'username' => 'dry', 'password' => '']);

        $queries = [];
        DB::connection('other_dry')->listen(function ($query) use (&$queries) { $queries[] = $query->sql; });

        // cached or not: the driver decides, so the bare dates are compared as dates
        $query = DateRangeRow::on('other_dry')->whereDateBetween('at', '2026-09-01 to 2026-09-30');
        cache()->forget('table_range_rows_columns');
        $uncached = DateRangeRow::on('other_dry')->whereDateBetween('at', '2026-09-01 to 2026-09-30');

        foreach ([$query, $uncached] as $each) {
            // bare dates, not the Carbon instants of a whole-day comparison
            expect($each->getBindings())->toBe(['2026-09-01', '2026-09-30']);
        }

        expect($queries)->toBe([]);
    })->with(['postgres' => 'pgsql', 'sqlite' => 'sqlite', 'sql server' => 'sqlsrv']);

    it('asks MariaDB as it asks MySQL', function () {
        viewer('Asia/Kuala_Lumpur');
        seedRangeColumns();
        config()->set('database.connections.maria_dry', ['driver' => 'mariadb', 'host' => '127.0.0.1', 'database' => 'dry', 'username' => 'dry', 'password' => '']);

        expect(wall(DateRangeRow::on('maria_dry')->whereDateBetween('at', '2026-09-01 to 2026-09-30')))->toBe(['2026-08-31 16:00:00', '2026-09-30 16:00:00']);
    });

    it('compares an instant range exactly as it always did', function () {
        viewer('Asia/Kuala_Lumpur');
        $query = mysqlRows()->whereDateBetween('at', '2026-08-31T16:00:00.000Z to 2026-09-30T15:59:59.999Z');

        expect($query->toSql())->toBe('select * from `range_rows` where `at` >= ? and `at` <= ?')
            ->and($query->getBindings())->toBe(['2026-08-31T16:00:00.000Z', '2026-09-30T15:59:59.999Z']);
    });
});

describe('Carbon::getRange()', function () {
    it('runs a bare date range from the viewer\'s first day to the last microsecond of the last', function () {
        viewer('Asia/Kuala_Lumpur');
        $range = Carbon::getRange('2026-09-01 to 2026-09-30');

        expect($range['from']->format('Y-m-d H:i:s.u'))->toBe('2026-08-31 16:00:00.000000')
            ->and($range['to']->format('Y-m-d H:i:s.u'))->toBe('2026-09-30 15:59:59.999999')
            ->and($range['from']->getTimezone()->getName())->toBe('UTC')
            ->and($range['diff']['d'])->toBe(30.0)
            ->and($range['tz'])->toBe('+08:00');
    });

    it('is one day for one date', function () {
        viewer('America/New_York');
        $range = Carbon::getRange('2026-11-01');

        expect($range['from']->format('Y-m-d H:i:s.u'))->toBe('2026-11-01 04:00:00.000000')
            ->and($range['to']->format('Y-m-d H:i:s.u'))->toBe('2026-11-02 04:59:59.999999');
    });

    it('reads an instant as it always did', function () {
        viewer('Asia/Kuala_Lumpur');
        $range = Carbon::getRange('2026-08-31T16:00:00Z to 2026-09-30T15:59:59Z');

        expect($range['from']->toIso8601ZuluString())->toBe('2026-08-31T16:00:00Z')
            ->and($range['to']->toIso8601ZuluString())->toBe('2026-09-30T15:59:59Z');
    });

    it('takes a date with an open end, and none', function () {
        viewer('Asia/Kuala_Lumpur');

        expect(Carbon::getRange('2026-09-01 to')['from']->format('Y-m-d H:i:s'))->toBe('2026-08-31 16:00:00')
            ->and(Carbon::getRange(null)['from']->format('Y-m-d'))->toBe('1970-01-01');
    });
});

describe('against a database', function () {
    beforeEach(function () {
        Schema::create('range_rows', function ($table) {
            $table->id();
            $table->string('name');
            $table->date('day')->nullable();
            $table->dateTime('at')->nullable();
        });

        seedRangeColumns();
    });

    it('finds exactly the days picked, in a DATE column and in a DATETIME column', function (string $viewer, string $app) {
        viewer($viewer, $app);
        seedAround($viewer, $app);

        $dates = ['2026-09-01 date', '2026-09-30 date'];
        $moments = ['2026-09-01 00:00:00', '2026-09-01 23:59:59', '2026-09-30 00:00:00', '2026-09-30 23:59:59'];

        foreach ([
            'filter day' => DateRangeRow::query()->filter(['day' => '2026-09-01 to 2026-09-30']),
            'whereDateBetween day' => DateRangeRow::query()->whereDateBetween('day', '2026-09-01 to 2026-09-30'),
        ] as $label => $query) {
            expect($query->orderBy('id')->pluck('name')->all())->toBe($dates, $label);
        }

        foreach ([
            // whereDateBetween() asks MySQL and MariaDB only (its SQL is checked on their grammar above), so on sqlite it compares dates
            'filter at' => DateRangeRow::query()->filter(['at' => '2026-09-01 to 2026-09-30']),
        ] as $label => $query) {
            expect($query->orderBy('id')->pluck('name')->all())->toBe($moments, $label);
        }
    })->with([
        'Kuala Lumpur viewer, UTC app' => ['Asia/Kuala_Lumpur', 'UTC'],
        'UTC viewer, UTC app' => ['UTC', 'UTC'],
        'New York viewer, UTC app' => ['America/New_York', 'UTC'],
        'New York viewer, Kuala Lumpur app' => ['America/New_York', 'Asia/Kuala_Lumpur'],
        'Kuala Lumpur viewer, Kuala Lumpur app' => ['Asia/Kuala_Lumpur', 'Asia/Kuala_Lumpur'],
        'Kiritimati viewer, UTC app' => ['Pacific/Kiritimati', 'UTC'],
        'Pago Pago viewer, UTC app' => ['Pacific/Pago_Pago', 'UTC'],
    ]);

    it('finds a single day and the last day of a range, last moment included', function () {
        viewer('America/New_York');
        seedAround('America/New_York', 'UTC');

        expect(DateRangeRow::query()->filter(['at' => '2026-09-30 to 2026-09-30'])->orderBy('id')->pluck('name')->all())
            ->toBe(['2026-09-30 00:00:00', '2026-09-30 23:59:59'])
            ->and(DateRangeRow::query()->filter(['at' => '2026-09-30 to'])->count())->toBe(4)
            ->and(DateRangeRow::query()->filter(['at' => 'to 2026-08-31'])->count())->toBe(2);
    });

    it('still finds rows by an instant range, as before', function () {
        viewer('Asia/Kuala_Lumpur');
        seedAround('Asia/Kuala_Lumpur', 'UTC');

        // the browser's old range for the same days, in Kuala Lumpur
        $names = DateRangeRow::query()->filter(['at' => '2026-08-31 16:00:00 to 2026-09-30 15:59:59'])->orderBy('id')->pluck('name')->all();

        expect($names)->toBe(['2026-09-01 00:00:00', '2026-09-01 23:59:59', '2026-09-30 00:00:00', '2026-09-30 23:59:59']);
    });
});

describe('the viewer\'s timezone', function () {
    it('is the user\'s, else atom.timezone, else Asia/Kuala_Lumpur', function () {
        expect(Carbon::getLocalTimezone())->toBe('Asia/Kuala_Lumpur');

        config()->set('atom.timezone', 'Asia/Tokyo');
        expect(Carbon::getLocalTimezone())->toBe('Asia/Tokyo');

        viewer('America/New_York');
        expect(Carbon::getLocalTimezone())->toBe('America/New_York');
    });

    it('prefers the config to a TIMEZONE in the environment', function () {
        $_ENV['TIMEZONE'] = $_SERVER['TIMEZONE'] = 'America/Chicago';

        try {
            expect(env('TIMEZONE'))->toBe('America/Chicago');

            config()->set('atom.timezone', 'Asia/Tokyo');
            expect(Carbon::getLocalTimezone())->toBe('Asia/Tokyo');

            viewer('America/New_York');
            expect(Carbon::getLocalTimezone())->toBe('America/New_York');
        } finally {
            unset($_ENV['TIMEZONE'], $_SERVER['TIMEZONE']);
        }
    });

    it('uses a real TIMEZONE process variable when the config has none, as when the config was cached at image build time', function () {
        $_ENV['TIMEZONE'] = $_SERVER['TIMEZONE'] = 'America/Chicago';

        try {
            config()->set('atom.timezone', null);
            expect(Carbon::getLocalTimezone())->toBe('America/Chicago');
        } finally {
            unset($_ENV['TIMEZONE'], $_SERVER['TIMEZONE']);
        }
    });

    it('reads atom.timezone from the config with env() unset, as config:cache leaves it', function () {
        unset($_ENV['TIMEZONE'], $_SERVER['TIMEZONE']);
        putenv('TIMEZONE');
        expect(env('TIMEZONE'))->toBeNull();

        config()->set('atom.timezone', 'Asia/Tokyo');
        expect(Carbon::getLocalTimezone())->toBe('Asia/Tokyo');

        config()->set('atom.timezone', null);
        expect(Carbon::getLocalTimezone())->toBe('Asia/Kuala_Lumpur');
    });

    it('ships a config/atom.php that the service provider merges, and fills timezone from TIMEZONE', function () {
        expect(config('atom'))->toHaveKey('timezone')
            ->and(require __DIR__.'/../../config/atom.php')->toBe(['timezone' => env('TIMEZONE')]);

        $_ENV['TIMEZONE'] = $_SERVER['TIMEZONE'] = 'Asia/Seoul';

        try {
            config()->set('atom', []);
            (new Jiannius\Atom\AtomServiceProvider($this->app))->register();

            expect(config('atom.timezone'))->toBe('Asia/Seoul')
                ->and(Carbon::getLocalTimezone())->toBe('Asia/Seoul');
        } finally {
            unset($_ENV['TIMEZONE'], $_SERVER['TIMEZONE']);
        }
    });

    it('lets the host\'s own config/atom.php win, and keeps its other keys', function () {
        $_ENV['TIMEZONE'] = $_SERVER['TIMEZONE'] = 'Asia/Seoul';

        try {
            config()->set('atom', ['timezone' => 'Europe/London', 'editor' => ['disk' => 's3']]);
            (new Jiannius\Atom\AtomServiceProvider($this->app))->register();

            expect(config('atom.timezone'))->toBe('Europe/London')
                ->and(config('atom.editor.disk'))->toBe('s3')
                ->and(Carbon::getLocalTimezone())->toBe('Europe/London');
        } finally {
            unset($_ENV['TIMEZONE'], $_SERVER['TIMEZONE']);
        }
    });

    it('is overridden by a host\'s explicit null (a shallow merge), which leaves the environment', function () {
        $_ENV['TIMEZONE'] = $_SERVER['TIMEZONE'] = 'Asia/Seoul';

        try {
            config()->set('atom', ['timezone' => null]);
            (new Jiannius\Atom\AtomServiceProvider($this->app))->register();

            expect(config('atom.timezone'))->toBeNull()
                ->and(Carbon::getLocalTimezone())->toBe('Asia/Seoul');
        } finally {
            unset($_ENV['TIMEZONE'], $_SERVER['TIMEZONE']);
        }
    });
});
