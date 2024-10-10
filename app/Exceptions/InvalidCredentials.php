<?php

namespace App\Exceptions;

class InvalidCredentials extends BaseException
{
    protected $code = 401;

    protected $message = 'Invalid credentials';
}
