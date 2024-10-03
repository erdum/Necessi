<?php

namespace App\Exceptions;

use Exception;

class UserNotFound extends BaseException
{
    protected $code = 400;
    protected $message = "No user found";
}
