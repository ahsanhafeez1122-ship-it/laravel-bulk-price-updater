<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The action isn't allowed in the import's current state, e.g. applying an import twice.
 */
class ImportStateException extends RuntimeException
{
}
