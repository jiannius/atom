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
     * The accounts dev login offers and accepts, in identifier order: the provider's own query (its `withQuery()` callback
     * included), narrowed by the model's `scopeDevLogin()` when it has one.
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
            $result = $callback($query);
            $query = $result instanceof Builder ? $result : $query;
        }

        $query->orderBy($model->getAuthIdentifierName());

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
     * The account with this identifier among the ones dev login accepts, or the first one when none is named.
     */
    public static function find(?string $id): ?Authenticatable
    {
        $query = static::query();

        if ($id !== null) {
            $query->where($query->getModel()->getAuthIdentifierName(), $id);
        }

        return $query->first();
    }

    /**
     * Where a signed-in visitor goes when no intended URL was kept: the framework's own answer for a guest middleware
     * (the host's `redirectUsersTo()`, else its `dashboard` or `home` route, else `/`).
     */
    public static function home(Request $request): string
    {
        $middleware = new class extends RedirectIfAuthenticated
        {
            public function target(Request $request): ?string
            {
                return $this->redirectTo($request);
            }
        };

        return $middleware->target($request) ?: '/';
    }

    /**
     * Sign the visitor in as this account (404 when dev login does not accept it) from a clean session, as after a logout.
     */
    public static function login(Request $request, ?string $id): RedirectResponse
    {
        $account = static::find($id);

        abort_if($account === null, 404);

        $intended = $request->session()->pull('url.intended');

        $request->session()->invalidate();

        static::guard()->login($account);

        return redirect()->to($intended ?? static::home($request));
    }
}
