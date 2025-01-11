<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void {}

    public function render($request, Throwable $e)
    {
        if ($e instanceof ValidationException) {
            $error = $e->validator->errors()->first();

            return response()->json([
                'message' => $error,
            ], 400);
        }

        if ($e instanceof ModelNotFoundException) {
            $model = explode('\\', $e->getModel());
            $model = end($model);
            $id = $e->getIds()[0];

            return response()->json([
                'message' => "Invalid {$model} ID {$id}, Resource not found."
            ], 404);
        }

        if ($e instanceof BaseException) {
            $response = [
                'message' => $e->get_message(),
            ];

            if (! empty($e->get_data())) {
                $response['data'] = $e->get_data();
            }

            return response()->json($response, $e->get_status_code());
        }

        return parent::render($request, $e);
    }
}
