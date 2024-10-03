<?php

namespace App\Exceptions;

use Exception;

class EmailNotVerified extends BaseException
{
    protected $code = 400;
    protected $message = "User is already registered";
}
