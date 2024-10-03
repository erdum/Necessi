<?php

namespace App\Exceptions;

use Exception;

class TokenAlreadLinked extends BaseException
{
    protected $code = 400;
    protected $message = "Token is already linked to another user";
}
