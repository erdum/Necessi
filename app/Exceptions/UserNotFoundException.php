<?php

namespace App\Exceptions;

use Exception;

class UserNotFoundException extends Exception
{
    protected $code = 400;

    protected $message = 'No user found';
}
