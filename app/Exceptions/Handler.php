<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
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
        $this->reportable(function (Throwable $e) {
            // Log all exceptions with context
            if (!$this->shouldntReport($e)) {
                Log::error($e->getMessage(), [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'user_id' => Auth::id() ?? null,
                    'url' => request()->fullUrl(),
                    'method' => request()->method(),
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e)
    {
        // API responses (JSON)
        if ($request->expectsJson() || $request->is('api/*')) {
            return $this->handleApiException($request, $e);
        }

        return parent::render($request, $e);
    }

    /**
     * Handle API exceptions
     */
    protected function handleApiException($request, Throwable $e)
    {
        $status = 500;
        $message = 'Internal Server Error';
        $errors = null;

        // Validation Exception
        if ($e instanceof ValidationException) {
            $status = 422;
            $message = 'Validation Error';
            $errors = $e->errors();
        }
        // Authentication Exception
        elseif ($e instanceof AuthenticationException) {
            $status = 401;
            $message = 'Unauthenticated';
        }
        // Model Not Found
        elseif ($e instanceof ModelNotFoundException) {
            $status = 404;
            $message = 'Resource not found';
        }
        // Not Found HTTP Exception
        elseif ($e instanceof NotFoundHttpException) {
            $status = 404;
            $message = 'Endpoint not found';
        }
        // HTTP Exception
        elseif ($e instanceof HttpException) {
            $status = $e->getStatusCode();
            $message = $e->getMessage() ?: 'HTTP Error';
        }
        // General Exception
        else {
            $message = config('app.debug') ? $e->getMessage() : 'Internal Server Error';
        }

        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        // Add debug info in development
        if (config('app.debug')) {
            $response['debug'] = [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => collect($e->getTrace())->take(5)->map(function ($trace) {
                    return [
                        'file' => $trace['file'] ?? null,
                        'line' => $trace['line'] ?? null,
                        'function' => $trace['function'] ?? null,
                    ];
                }),
            ];
        }

        return response()->json($response, $status);
    }

    /**
     * Handle unauthenticated exception
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        return redirect()->guest(route('login'));
    }
}
