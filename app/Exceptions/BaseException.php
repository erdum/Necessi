<?php

namespace App\Exceptions;

use Exception;

class BaseException extends Exception
{
    protected $message;

    protected $code;

    protected $data;

    public function __construct(
        string $message = 'API Internal Error',
        int $code = 500,
        array $data = []
    ) {
        $this->message = $message;
        $this->code = $code;
        $this->data = $data;

        parent::__construct($this->message);
    }

    public function get_message()
    {
        return $this->message;
    }

    public function get_status_code()
    {
        return $this->code;
    }

    public function get_data()
    {
        return $this->data;
    }
}
