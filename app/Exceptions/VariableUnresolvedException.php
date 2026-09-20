<?php

namespace App\Exceptions;

use Exception;

class VariableUnresolvedException extends Exception
{
    protected string $variableKey;

    public function __construct(string $variableKey, string $message = '', int $code = 422)
    {
        $this->variableKey = $variableKey;
        $msg = $message ?: "La variable '{{{$variableKey}}}' no pudo ser resuelta porque no existe o no tiene un valor asignado.";
        parent::__construct($msg, $code);
    }

    public function getVariableKey(): string
    {
        return $this->variableKey;
    }
}