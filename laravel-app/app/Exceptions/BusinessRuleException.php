<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A request that is well-formed but breaks a business rule (duplicate code, cancelling a shipped order).
 * Rendered as 409 with the message. The messages match the legacy stored procedures word for word,
 * because v1 clients may show them to users.
 */
class BusinessRuleException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 409)
    {
        parent::__construct($message);
    }
}
