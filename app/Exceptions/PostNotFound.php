<?php

namespace App\Exceptions;

class PostNotFound extends BaseException
{
    protected $code = 400;

    protected $message = 'User has no posts available to receive bids.';
}
