<?php

namespace Jiannius\Atom\Tests\Support;

/**
 * Boots the test app as `local`, before the providers boot, so routes/web.php registers its local-only routes the way it does
 * on a host. Laravel skips its CSRF check only when the environment is `testing`, so a test using this trait is checked by it.
 */
trait LocalEnvironment
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['env'] = 'local';
        // The .env that `testbench serve` leaves behind sets the cookie driver.
        $app['config']->set('session.driver', 'array');
    }
}
