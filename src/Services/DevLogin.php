<?php

namespace Jiannius\Atom\Services;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use LogicException;

/**
 * The local-only sign-in behind `<atom:dev-login>` and `POST /atom/dev/login/{atomUser?}` (routes/web.php,
 * registered only when the app environment is local). The accounts it lists and accepts are the default guard's
 * own Eloquent provider's, narrowed by an optional `scopeDevLogin()` on the host's model.
 */
class DevLogin
{
    /**
     * The default guard, which must be a session guard: dev login signs a user into it.
     */
    public static function guard(): StatefulGuard
    {
        $guard = Auth::guard();

        if (! $guard instanceof StatefulGuard || ! method_exists($guard, 'getProvider')) {
            throw new LogicException('Dev login needs the default auth guard to be a session guard with a user provider; ['.config('auth.defaults.guard').'] is a '.$guard::class.'.');
        }

        return $guard;
    }

    /**
     * The accounts dev login offers and accepts, in identifier order: the provider's own query (its `withQuery()` callback,
     * called for what it does to the builder with its return value ignored, as `EloquentUserProvider::newModelQuery()` does),
     * ordered by the identifier first and then narrowed by the model's `scopeDevLogin()` when it has one (which may `reorder()`).
     *
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public static function query(): Builder
    {
        $guard = static::guard();
        $provider = $guard->getProvider();

        if (! $provider instanceof EloquentUserProvider) {
            throw new LogicException('Dev login needs an Eloquent user provider; the default guard uses '.$provider::class.'.');
        }

        $model = $provider->createModel();
        $query = $model->newQuery();

        if ($callback = $provider->getQueryCallback()) {
            $callback($query);
        }

        $query->orderBy($model->qualifyColumn($model->getAuthIdentifierName()));

        if ($model->hasNamedScope('devLogin')) {
            $query->devLogin();
        }

        return $query;
    }

    /**
     * The first accounts the buttons offer.
     *
     * @return Collection<int, \Illuminate\Database\Eloquent\Model>
     */
    public static function users(int $limit): Collection
    {
        return static::query()->limit(max(1, $limit))->get();
    }

    /**
     * The account with this identifier among the ones dev login accepts, or the first one when none is named; null when there is none.
     * When the identifier is an auto-incrementing integer key, a segment that is not a whole number within PHP_INT_MAX is none and is not
     * queried (PostgreSQL throws on it, where SQLite and MySQL match nothing). Any other key is queried as given: a malformed uuid on
     * PostgreSQL surfaces as the database's error, a 500.
     */
    public static function find(?string $id): ?Authenticatable
    {
        $query = static::query();

        if ($id === null) {
            return $query->first();
        }

        $model = $query->getModel();
        $name = $model->getAuthIdentifierName();

        if (
            $name === $model->getKeyName()
            && $model->getIncrementing()
            && in_array($model->getKeyType(), ['int', 'integer'], true)
            && ! (ctype_digit($id) && filter_var($id, FILTER_VALIDATE_INT) !== false)
        ) {
            return null;
        }

        return $query->where($model->qualifyColumn($name), $id)->first();
    }

    /**
     * Where a signed-in visitor goes when no intended URL was kept: the framework's own answer for a guest middleware
     * (the host's `redirectUsersTo()`, else its `dashboard` or `home` route, else `/`).
     */
    public static function home(Request $request): string
    {
        $middleware = new class extends RedirectIfAuthenticated
        {
            /**
             * The protected `redirectTo()`, which holds the host's `redirectUsersTo()` callback and the default route lookup.
             */
            public function target(Request $request): ?string
            {
                return $this->redirectTo($request);
            }
        };

        return $middleware->target($request) ?: '/';
    }

    /**
     * Queue the removal of every remember-me cookie the request carries (`remember_<guard>_<sha1 of the guard class>`, which is how
     * `SessionGuard` names it and any subclass too), found by name so no guard is built from the config, and of the default guard's
     * own recaller cookie when it names it differently (a subclass overriding `getRecallerName()`). The sign-in does not remember
     * the visitor, and a cookie left behind would sign the previous account back in once the new session expires.
     */
    protected static function forgetRecallers(Request $request): void
    {
        foreach (array_keys($request->cookies->all()) as $name) {
            if (preg_match('/^remember_.+_[0-9a-f]{40}$/', (string) $name)) {
                Cookie::queue(Cookie::forget((string) $name));
            }
        }

        $guard = static::guard();

        if (method_exists($guard, 'getRecallerName') && $request->cookies->has($guard->getRecallerName())) {
            Cookie::queue(Cookie::forget($guard->getRecallerName()));
        }
    }

    /**
     * Sign the visitor in as this account (404 when dev login does not accept it) from a clean session, as after a logout: the
     * session is wiped and the remember-me cookies are forgotten, without logging the previous user out (no `Logout` event).
     */
    public static function login(Request $request, ?string $id): RedirectResponse
    {
        $account = static::find($id);

        abort_if($account === null, 404);

        $intended = $request->session()->pull('url.intended');

        static::forgetRecallers($request);

        $request->session()->invalidate();

        static::guard()->login($account);

        return redirect()->to($intended ?? static::home($request));
    }
}
