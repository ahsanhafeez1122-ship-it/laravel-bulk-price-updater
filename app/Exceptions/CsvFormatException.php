<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The file as a whole can't be read (missing columns, wrong format, too many rows).
 * Problems in single rows are reported per row instead.
 */
class CsvFormatException extends RuntimeException
{
}
