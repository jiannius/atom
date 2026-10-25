<?php

namespace App\Models;

/**
 * Extends a class from a package that is not installed: declaring it throws an
 * Error. It is reached only by the test that passes its --path, and loaded only
 * by that test's autoloader.
 */
class MissingParentModel extends \Vendor\NotInstalled\Models\Base
{
}
