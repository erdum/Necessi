<?php

namespace App\Exceptions;

class SameAsOldPassword extends BaseException
{
    protected $code = 400;

    protected $message = 'New password cannot be the same as the old password.';
}
