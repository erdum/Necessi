<?php

namespace App\Exceptions;

class InvalidIdToken extends BaseException
{
    protected $code = 401;

    protected $message = 'Invalid ID token';
}
