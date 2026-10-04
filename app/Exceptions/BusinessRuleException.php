<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A domain rule was violated (insufficient stock, over-return, …).
 *
 * Rendered as a flash error (or a 422 JSON response) instead of a 500, and
 * always thrown inside a DB transaction so nothing is partially written.
 */
class BusinessRuleException extends RuntimeException {}
