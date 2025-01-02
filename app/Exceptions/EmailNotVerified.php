<?php

namespace App\Exceptions;

class EmailNotVerified extends BaseException
{
    protected $code = 403;

    protected $message = 'User email is not verfied';
}
