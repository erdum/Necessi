<?php

namespace App\Exceptions;

class WrongPassword extends BaseException
{
    protected $code = 400;

    protected $message = 'The current password you entered is incorrect. Please try again.';
}
