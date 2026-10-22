<?php

namespace Jiannius\Atom\Services;

/**
 * A failure of atom's own backup handling whose message is a fixed string (it
 * never holds a row's content), so it is safe to print
 */
class EditorBackupException extends \RuntimeException {}
