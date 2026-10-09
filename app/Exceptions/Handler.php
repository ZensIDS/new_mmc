<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpException;
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
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // Page Expired (419) -> langsung arahkan ke login, tanpa halaman error
        $this->renderable(function (TokenMismatchException $e, $request) {
            return $this->redirectToLoginOnExpired($request);
        });

        $this->renderable(function (HttpException $e, $request) {
            if ($e->getStatusCode() === 419) {
                return $this->redirectToLoginOnExpired($request);
            }
        });
    }

    protected function redirectToLoginOnExpired($request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message'  => 'Sesi berakhir, silakan login kembali.',
                'redirect' => route('login'),
            ], 401);
        }

        return redirect()->route('login');
    }
}