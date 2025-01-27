<?php

namespace App\Exceptions;

class UserLocationNotFound extends BaseException
{
    protected $code = 400;

    protected $message = 'User location coordinates not found.';
}
