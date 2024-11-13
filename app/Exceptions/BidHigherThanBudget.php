<?php

namespace App\Exceptions;

class BidHigherThanBudget extends BaseException
{
    protected $code = 400;

    protected $message = 'The bid amount should be less than the budget amount';
}
