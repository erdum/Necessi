<?php

namespace App\Exceptions;

class AccessForbidden extends BaseException
{
    protected $code = 403;

    protected $message = 'Access forbidden';
}
