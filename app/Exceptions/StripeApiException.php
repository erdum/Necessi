<?php

namespace App\Exceptions;

class StripeApiException extends BaseException
{
    protected $code = 500;

    protected $message = 'Error from Stripe API: ';

    public function __construct(
        $message = '',
    ) {
        if ($message) {
            $this->message = $this->message.$message;
        }

        parent::__construct($this->message, $this->code);
    }
}
