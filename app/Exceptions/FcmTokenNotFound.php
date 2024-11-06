<?php

namespace App\Exceptions;

class FcmTokenNotFound extends BaseException
{
    protected $code = 400;

    protected $message = 'User does not have a device token';
}
