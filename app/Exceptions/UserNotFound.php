<?php

namespace App\Exceptions;

class UserNotFound extends BaseException
{
    protected $code = 400;

    protected $message = 'No user found';
}
