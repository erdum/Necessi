<?php

namespace App\Exceptions;

class ConnectionRequestNotFound extends BaseException
{
    protected $code = 400;

    protected $message = 'No Request found';
}
