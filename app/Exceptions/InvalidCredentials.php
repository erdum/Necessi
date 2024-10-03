<?php

namespace App\Exceptions;

use Exception;

class InvalidCredentials extends BaseException
{
    protected $code = 401;
    protected $message = "Invalid credentials";
}
