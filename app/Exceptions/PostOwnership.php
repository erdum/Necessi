<?php

namespace App\Exceptions;

class PostOwnership extends BaseException
{
    protected $code = 403;

    protected $message = 'You do not have permission to access this post.';
}
