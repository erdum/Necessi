<?php

namespace App\Exceptions;

class EmailNotVerified extends BaseException
{
    protected $code = 400;

    protected $message = 'User is already registered';
}
