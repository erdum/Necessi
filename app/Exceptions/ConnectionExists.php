<?php

namespace App\Exceptions;

class ConnectionExists extends BaseException
{
    protected $code = 400;

    protected $message = 'You are already connected with this connection';
}
