<?php

namespace App\Exceptions;

class UserNotConnected extends BaseException
{
    protected $code = 403;

    protected $message = 'The specified user is not in your connections.';
}
