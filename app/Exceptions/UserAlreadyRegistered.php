<?php

namespace App\Exceptions;

class UserAlreadyRegistered extends BaseException
{
    protected $code = 400;

    protected $message = 'User is already registered';
}
