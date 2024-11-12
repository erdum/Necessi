<?php

namespace App\Exceptions;

class InvalidPostId extends BaseException
{
    protected $code = 400;

    protected $message = 'Invalid Post ID';
}
