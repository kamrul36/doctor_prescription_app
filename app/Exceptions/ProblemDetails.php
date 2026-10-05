<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Exception\RequestExceptionInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/** Renders API errors as RFC 9457 `application/problem+json`. */
class ProblemDetails
{
    public static function shouldRender(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }

    public static function render(Throwable $e, Request $request): JsonResponse
    {
        [$status, $title] = self::classify($e);

        $body = [
            'type' => 'about:blank',
            'title' => $title,
            'status' => $status,
            'detail' => self::detail($e, $status),
            'instance' => '/'.ltrim($request->path(), '/'),
        ];

        if ($e instanceof ValidationException) {
            $body['errors'] = $e->errors();
        }

        $headers = ['Content-Type' => 'application/problem+json'];
        if ($e instanceof HttpExceptionInterface) {
            $headers = array_merge($e->getHeaders(), $headers);
        }

        return new JsonResponse($body, $status, $headers);
    }

    /** @return array{int, string} */
    private static function classify(Throwable $e): array
    {
        return match (true) {
            $e instanceof ValidationException => [422, 'Validation failed'],
            $e instanceof AuthenticationException => [401, 'Unauthenticated'],
            $e instanceof AuthorizationException => [403, 'Forbidden'],
            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException => [404, 'Not found'],
            $e instanceof HttpExceptionInterface => [
                $e->getStatusCode(),
                Response::$statusTexts[$e->getStatusCode()] ?? 'Error',
            ],
            $e instanceof RequestExceptionInterface => [400, 'Bad request'],
            default => [500, 'Server error'],
        };
    }

    private static function detail(Throwable $e, int $status): string
    {
        if ($status >= 500) {
            return config('app.debug') ? $e->getMessage() : 'An unexpected error occurred.';
        }

        return match (true) {
            $e instanceof ModelNotFoundException => 'The requested resource was not found.',
            $e instanceof ValidationException => 'One or more fields are invalid.',
            default => $e->getMessage() !== '' ? $e->getMessage() : 'Request could not be completed.',
        };
    }
}
