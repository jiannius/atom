<?php

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Jiannius\Atom\Services\DevLogin;
use Jiannius\Atom\Tests\Fixtures\DevLoginActiveMember;
use Jiannius\Atom\Tests\Fixtures\DevLoginMember;
use Jiannius\Atom\Tests\Fixtures\DevLoginSoftMember;
use Jiannius\Atom\Tests\Fixtures\DevLoginUuidMember;
use Jiannius\Atom\Tests\Support\LocalEnvironment;

// The app boots as `local` here, so the real routes file registers the route, and Laravel's CSRF check is on (it is skipped
// only in `testing`): every POST must carry a token.
uses(LocalEnvironment::class);

/**
 * POST with a CSRF token the session holds.
 */
function devLoginPost($test, string $uri, array $session = [], bool $withToken = true)
{
    return $test->withSession(['_token' => 'token', ...$session])->post($uri, $withToken ? ['_token' => 'token'] : []);
}

beforeEach(function () {
    Schema::create('members', function ($table) {
        $table->id();
        $table->string('name');
        $table->string('email')->nullable();
        $table->string('password')->nullable();
        $table->timestamp('blocked_at')->nullable();
        $table->softDeletes();
        $table->rememberToken();
        $table->timestamps();
    });

    config(['auth.providers.users.model' => DevLoginMember::class]);
    Auth::forgetGuards();

    $this->alex = DevLoginMember::create(['name' => 'Alex Doe', 'email' => 'alex@example.com']);
    $this->sam = DevLoginMember::create(['name' => 'Sam Lee', 'email' => 'sam@example.com']);
});

afterEach(function () {
    (new ReflectionProperty(RedirectIfAuthenticated::class, 'redirectToCallback'))->setValue(null, null);
});

it('registers the sign-in route in local as POST only', function () {
    expect(Route::has('atom.dev.login'))->toBeTrue()
        ->and(Route::getRoutes()->getByName('atom.dev.login')->methods())->toBe(['POST']);

    $this->get('/atom/dev/login/'.$this->alex->id)->assertStatus(405);

    expect(Auth::check())->toBeFalse();
});

it('signs in as the given account through the default guard\'s own provider', function () {
    Event::fake([Login::class]);

    devLoginPost($this, '/atom/dev/login/'.$this->sam->id)->assertRedirect('/');

    $this->assertAuthenticatedAs($this->sam);

    expect(Auth::user())->toBeInstanceOf(DevLoginMember::class)
        ->and(class_exists('App\Models\User', false))->toBeFalse();

    Event::assertDispatched(Login::class, fn (Login $event) => $event->user->is($this->sam));
});

it('signs in as the first account by identifier when none is named', function () {
    devLoginPost($this, '/atom/dev/login')->assertRedirect('/');

    $this->assertAuthenticatedAs($this->alex);
});

it('refuses a POST without a CSRF token', function () {
    devLoginPost($this, '/atom/dev/login/'.$this->alex->id, withToken: false)->assertStatus(419);

    expect(Auth::check())->toBeFalse();
});

it('returns 404 for an account that does not exist', function () {
    devLoginPost($this, '/atom/dev/login/999')->assertNotFound();

    expect(Auth::check())->toBeFalse();
});

it('lets the model\'s scopeDevLogin be both the list and the gate', function () {
    config(['auth.providers.users.model' => DevLoginActiveMember::class]);
    Auth::forgetGuards();

    $blocked = DevLoginMember::create(['name' => 'Aaron Blocked', 'blocked_at' => now()]);
    $zed = DevLoginMember::create(['name' => 'Zed Last']);

    devLoginPost($this, '/atom/dev/login/'.$blocked->id)->assertNotFound();

    expect(Auth::check())->toBeFalse();

    // first by the scope's order (name), and not by id: Aaron is blocked, so Alex comes first
    devLoginPost($this, '/atom/dev/login')->assertRedirect('/');

    expect(Auth::user()->name)->toBe('Alex Doe');
});

it('honours the provider\'s withQuery() callback', function () {
    Auth::guard()->getProvider()->withQuery(fn ($query) => $query->where('email', '!=', 'sam@example.com'));

    devLoginPost($this, '/atom/dev/login/'.$this->sam->id)->assertNotFound();

    expect(Auth::check())->toBeFalse();

    devLoginPost($this, '/atom/dev/login/'.$this->alex->id)->assertRedirect('/');

    $this->assertAuthenticatedAs($this->alex);
});

it('ignores what the withQuery() callback returns, as the user provider does', function () {
    Auth::guard()->getProvider()->withQuery(function ($query) {
        $query->where('email', '!=', 'sam@example.com');

        return DevLoginMember::query();
    });

    devLoginPost($this, '/atom/dev/login/'.$this->sam->id)->assertNotFound();

    expect(Auth::check())->toBeFalse();
});

it('returns 404 for a segment the key type cannot hold, and for a query that fails', function () {
    devLoginPost($this, '/atom/dev/login/not-a-number')->assertNotFound();

    Auth::guard()->getProvider()->withQuery(fn ($query) => $query->whereRaw('this is not sql ('));

    devLoginPost($this, '/atom/dev/login/'.$this->sam->id)->assertNotFound();

    expect(Auth::check())->toBeFalse();
});

it('does not hide a failing query when no account is named', function () {
    Auth::guard()->getProvider()->withQuery(fn ($query) => $query->whereRaw('this is not sql ('));

    $this->withoutExceptionHandling();

    expect(fn () => devLoginPost($this, '/atom/dev/login'))->toThrow(QueryException::class);
});

it('leaves out accounts a global scope hides (soft-deleted), in the list and at sign-in', function () {
    config(['auth.providers.users.model' => DevLoginSoftMember::class]);
    Auth::forgetGuards();

    $this->alex->delete();

    devLoginPost($this, '/atom/dev/login/'.$this->alex->id)->assertNotFound();

    expect(Auth::check())->toBeFalse();

    $names = array_map(devLoginLabel(...), devLoginButtons());

    expect($names)->toBe(['Log in as Sam Lee']);

    devLoginPost($this, '/atom/dev/login')->assertRedirect('/');

    expect(Auth::id())->toBe($this->sam->id);
});

it('works with a string identifier that is not called id', function () {
    Schema::create('uuid_members', function ($table) {
        $table->uuid('uuid')->primary();
        $table->string('name');
        $table->rememberToken();
        $table->timestamps();
    });

    config(['auth.providers.users.model' => DevLoginUuidMember::class]);
    Auth::forgetGuards();

    $a = DevLoginUuidMember::create(['uuid' => '11111111-1111-4111-8111-111111111111', 'name' => 'Ana Uuid']);
    $b = DevLoginUuidMember::create(['uuid' => '00000000-0000-4000-8000-000000000000', 'name' => 'Bo Uuid']);

    $buttons = devLoginButtons();

    // identifier order: Bo's uuid sorts first
    expect($buttons)->toHaveCount(2)
        ->and($buttons[0]->getAttribute('formaction'))->toBe(route('atom.dev.login', ['atomUser' => $b->uuid]));

    devLoginPost($this, '/atom/dev/login/'.$a->uuid)->assertRedirect('/');

    $this->assertAuthenticatedAs($a);
});

it('is not reached by a host\'s global {user} binding', function () {
    Route::bind('user', fn () => abort(500));

    devLoginPost($this, '/atom/dev/login/'.$this->sam->id)->assertRedirect('/');

    $this->assertAuthenticatedAs($this->sam);
});

it('redirects to the intended url, else the guest middleware\'s target, else the dashboard route, else /', function (string $case, string $expected) {
    $session = [];

    if ($case === 'intended') {
        $session = ['url.intended' => 'http://localhost/reports'];
        RedirectIfAuthenticated::redirectUsing(fn () => '/ignored');
    } else if ($case === 'callback') {
        RedirectIfAuthenticated::redirectUsing(fn () => '/members/home');
    } else if ($case === 'dashboard') {
        Route::get('/overview', fn () => 'ok')->name('dashboard');
    }

    devLoginPost($this, '/atom/dev/login', $session)->assertRedirect($expected);
})->with([
    'intended' => ['intended', 'http://localhost/reports'],
    'callback' => ['callback', '/members/home'],
    'dashboard' => ['dashboard', 'http://localhost/overview'],
    'root' => ['root', '/'],
]);

it('starts from a clean session', function () {
    $this->actingAs($this->alex);

    $response = devLoginPost($this, '/atom/dev/login/'.$this->sam->id, [
        'password_hash_web' => 'stale',
        'url.intended' => '/x',
    ]);

    $response->assertRedirect('/x');

    $this->assertAuthenticatedAs($this->sam);
    $response->assertSessionMissing('password_hash_web');

    expect(session()->token())->toBeString()->not->toBeEmpty()->not->toBe('token');
});

it('needs a session guard with an Eloquent provider', function (callable $configure, string $named) {
    $configure();
    Auth::forgetGuards();

    expect(fn () => DevLogin::query())->toThrow(LogicException::class, $named);
})->with([
    'a token guard' => [
        function () {
            config(['auth.guards.api' => ['driver' => 'token', 'provider' => 'users'], 'auth.defaults.guard' => 'api']);
        },
        '[api]',
    ],
    'a database provider' => [
        function () {
            config(['auth.providers.users' => ['driver' => 'database', 'table' => 'members']]);
        },
        'Illuminate\Auth\DatabaseUserProvider',
    ],
]);

/**
 * The text of a button's label (the loading icon inside it carries a style block of its own).
 */
function devLoginLabel(DOMElement $button): string
{
    foreach ($button->getElementsByTagName('span') as $span) {
        if ($span->hasAttribute('data-atom-button-label')) {
            return trim($span->textContent);
        }
    }

    return '';
}

/**
 * The buttons <atom:dev-login> prints for the members in the table.
 *
 * @return list<DOMElement>
 */
function devLoginButtons(string $attributes = ''): array
{
    session()->start();

    return domQuery(renderBlade('<atom:dev-login '.$attributes.' />'), '//form[@data-atom-dev-login]//button[@data-atom-button]');
}

it('prints one POST form with a token and a submit button per account, in identifier order, up to the limit', function () {
    foreach (range(1, 5) as $i) {
        DevLoginMember::create(['name' => 'Extra '.$i]);
    }

    session()->start();

    $html = renderBlade('<atom:dev-login limit="3" />');
    $forms = domQuery($html, '//form[@data-atom-dev-login]');
    $buttons = domQuery($html, '//form[@data-atom-dev-login]//button[@data-atom-button]');

    expect($forms)->toHaveCount(1)
        ->and(strtolower($forms[0]->getAttribute('method')))->toBe('post')
        ->and(domQuery($html, '//form[@data-atom-dev-login]//input[@name="_token"]'))->toHaveCount(1)
        ->and($buttons)->toHaveCount(3);

    foreach ($buttons as $i => $button) {
        expect($button->getAttribute('type'))->toBe('submit')
            ->and($button->getAttribute('formaction'))->toBe(route('atom.dev.login', ['atomUser' => $i + 1]));
    }
});

it('labels each button with the label attribute, falling back to the identifier, and prints it as text', function () {
    $this->sam->update(['email' => null, 'name' => '<img src=x onerror=alert(1)>']);

    $byName = devLoginButtons();

    expect(devLoginLabel($byName[0]))->toBe('Log in as Alex Doe')
        ->and(devLoginLabel($byName[1]))->toBe('Log in as <img src=x onerror=alert(1)>');

    $html = renderBlade('<atom:dev-login label="email" />');

    expect(domQuery($html, '//form[@data-atom-dev-login]//img'))->toBe([])
        ->and(devLoginLabel(domQuery($html, '//button[@data-atom-button]')[0]))->toBe('Log in as alex@example.com')
        ->and(devLoginLabel(domQuery($html, '//button[@data-atom-button]')[1]))->toBe('Log in as '.$this->sam->id);
});

it('does not spin while a Livewire login form submits', function () {
    foreach (devLoginButtons() as $button) {
        expect($button->getAttribute('wire:target'))->not->toBe('submit')
            ->and($button->getAttribute('wire:target'))->toBe('atom-dev-login');
    }
});

it('draws no primary button', function () {
    foreach (devLoginButtons() as $button) {
        expect(domClasses($button))->not->toContain('bg-accent');
    }
});

it('lists only what the provider\'s withQuery() callback allows', function () {
    Auth::guard()->getProvider()->withQuery(fn ($query) => $query->where('email', '!=', 'alex@example.com'));

    expect(array_map(devLoginLabel(...), devLoginButtons()))->toBe(['Log in as Sam Lee']);
});

it('leaves out the accounts scopeDevLogin leaves out, and still fills the limit in the scope\'s order', function () {
    config(['auth.providers.users.model' => DevLoginActiveMember::class]);
    Auth::forgetGuards();

    $this->alex->update(['blocked_at' => now()]);
    DevLoginMember::create(['name' => 'Bea Archer']);
    DevLoginMember::create(['name' => 'Cal Dune']);

    $names = array_map(devLoginLabel(...), devLoginButtons('limit="2"'));

    expect($names)->toBe(['Log in as Bea Archer', 'Log in as Cal Dune']);
});

it('prints nothing, not even a separator, when no account qualifies', function () {
    DevLoginMember::query()->delete();

    session()->start();

    expect(trim(renderBlade('<atom:dev-login />')))->toBe('');
});

it('prints a button that signs in as its account', function () {
    session()->start();

    $second = devLoginButtons()[1]->getAttribute('formaction');

    $this->withSession(['_token' => 'token'])->post($second, ['_token' => 'token'])->assertRedirect('/');

    $this->assertAuthenticatedAs($this->sam);
});

it('throws the same LogicException from the component for a non-Eloquent provider', function () {
    config(['auth.providers.users' => ['driver' => 'database', 'table' => 'members']]);
    Auth::forgetGuards();

    try {
        renderBlade('<atom:dev-login />');
        $this->fail('Expected a LogicException.');
    } catch (Throwable $e) {
        while ($e->getPrevious() && ! $e instanceof LogicException) {
            $e = $e->getPrevious();
        }

        expect($e)->toBeInstanceOf(LogicException::class)
            ->and($e->getMessage())->toContain('Illuminate\Auth\DatabaseUserProvider');
    }
});
