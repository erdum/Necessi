<?php

namespace App\Exceptions;

class InvalidPostId extends BaseException
{
    protected $code = 401;

    protected $message = 'Invalid Post ID';
}
