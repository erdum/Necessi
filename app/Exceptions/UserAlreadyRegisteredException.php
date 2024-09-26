<?php

namespace App\Exceptions;

use Exception;

class UserAlreadyRegisteredException extends Exception
{
    protected $code = 400;

    protected $message = 'User is already registered';
}
