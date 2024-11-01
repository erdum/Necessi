<?php

namespace App\Exceptions;

class CannotBidOnOwnPost extends BaseException
{
    protected $code = 400;

    protected $message = 'You cannot place a bid on your own post.';
}
