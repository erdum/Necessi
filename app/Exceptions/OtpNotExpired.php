<?php

namespace App\Exceptions;

class OtpNotExpired extends BaseException
{
    protected $code = 429;

    protected $message = 'You have recently requested a OTP. Please try again after some time.';
}
