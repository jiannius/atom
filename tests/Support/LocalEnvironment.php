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
        // the dev login is also behind its own switch (`atom.dev_login`); a test of it turns it on, the one that checks it is off turns it back off
        $app['config']->set('atom.dev_login', true);
        // The .env that `testbench serve` leaves behind sets the cookie driver.
        $app['config']->set('session.driver', 'array');
    }
}
