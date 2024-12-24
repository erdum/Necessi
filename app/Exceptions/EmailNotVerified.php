<?php

namespace App\Exceptions;

class EmailNotVerified extends BaseException
{
    protected $code = 400;

    protected $message = 'User email is not verfied';
}
