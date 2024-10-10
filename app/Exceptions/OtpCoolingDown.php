<?php

namespace App\Exceptions;

class OtpCoolingDown extends BaseException
{
    protected $code = 429;

    protected $message = 'You have exceeded the maximum OTP retry limit. Please try again after ';

    public function __construct($time)
    {
        $this->message = $message.' '.$time;
        parent::__construct($this->message, $this->code);
    }
}
