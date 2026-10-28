<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
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
    public function register(): void
    {
        $this->renderable(function (AccessDeniedHttpException $exception, Request $request) {
            if ($request->is('api/v1/books*')) {
                return response()->json([
                    'error' => 'この操作を行う権限がありません。',
                ], 403);
            }
        });

        $this->reportable(function (Throwable $e) {
            //
        });
    }
}
