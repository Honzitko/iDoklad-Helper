<?php
namespace Mervit\iDoklad\Exceptions;

use Exception;

class IDokladException extends Exception
{
    /**
     * Additional context for debugging failed API calls.
     *
     * @var array<string,mixed>
     */
    protected $context = [];

    public function __construct($message, $code = 0, ?Exception $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * Return contextual information captured with the exception.
     *
     * @return array<string,mixed>
     */
    public function getContext()
    {
        return $this->context;
    }
}
