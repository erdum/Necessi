<?php

namespace App\Exceptions;

use Exception;

class BaseException extends Exception
{
    protected $message;

    protected $code;

    public function __construct(
        $message = 'API Internal Error',
        $code = 500
    ) {
        $this->message = $this->message ?: $message;
        $this->code = $this->code ?: $code;

        parent::__construct($this->message, $this->code);
    }

    public function get_status_code()
    {
        return $this->code;
    }
}
