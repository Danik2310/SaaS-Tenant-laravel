<?php

namespace App\Shared\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (PlanLimitExceededException $e, $request) {
            if ($request->expectsJson() || $request->is('admin/api/*')) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'type' => 'plan_limit',
                ], 403);
            }

            return back()->with('error', $e->getMessage());
        });

        $this->renderable(function (AuthenticationException $e, $request) {
            if ($request->expectsJson() || $request->is('admin/api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
        });

        $this->renderable(function (AuthorizationException $e, $request) {
            if ($request->expectsJson() || $request->is('admin/api/*')) {
                return response()->json([
                    'message' => $e->getMessage() ?: 'This action is unauthorized.',
                ], 403);
            }

            return Inertia::render('Unauthorized', [
                'message' => $e->getMessage() ?: 'You do not have permission to access this resource.',
            ])->toResponse($request)->setStatusCode(403);
        });

        $this->renderable(function (NotFoundHttpException $e, $request) {
            if ($request->expectsJson() || $request->is('admin/api/*')) {
                return response()->json([
                    'message' => 'Resource not found.',
                ], 404);
            }
        });

        $this->renderable(function (ModelNotFoundException $e, $request) {
            if ($request->expectsJson() || $request->is('admin/api/*')) {
                return response()->json([
                    'message' => 'Resource not found.',
                ], 404);
            }
        });

        $this->renderable(function (ValidationException $e, $request) {
            if ($request->expectsJson() || $request->is('admin/api/*')) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        $this->renderable(function (Throwable $e, $request) {
            if ($request->expectsJson() || $request->is('admin/api/*')) {
                $response = [
                    'message' => 'An error occurred.',
                ];

                if (config('app.debug')) {
                    $response['error'] = $e->getMessage();
                }

                $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

                return response()->json($response, $status);
            }
        });
    }
}
