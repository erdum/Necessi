<?php

namespace App\Exceptions;

use Exception;

class InvalidOtp extends BaseException
{
    protected $code = 400;
    protected $message = "Invalid or expired OTP";
}
