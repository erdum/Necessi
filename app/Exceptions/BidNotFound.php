<?php

namespace App\Exceptions;

class BidNotFound extends BaseException
{
    protected $code = 400;

    protected $message = 'Bid Not Found';
}
