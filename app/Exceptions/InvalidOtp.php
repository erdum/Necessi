<?php

namespace App\Exceptions;

class InvalidOtp extends BaseException
{
    protected $code = 400;

    protected $message = 'Invalid or expired OTP';
}
