<?php

namespace App\Exceptions;

class BidAmountTooHigh extends BaseException
{
    protected $code = 400;

    protected $message = 'New bid amount must be less than the previous bid';
}
