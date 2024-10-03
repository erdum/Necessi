<?php

namespace App\Exceptions;

use Exception;

class InvalidIdToken extends BaseException
{
    protected $code = 401;
    protected $message = "Invalid ID token";
}
