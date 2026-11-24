<?php

use App\Application\Import\Exceptions\DuplicateImport;
use App\Application\Import\Exceptions\ImportCannotBeRetried;
use App\Http\Middleware\SetLocaleFromHeader;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // API only: guests get a 401, never a redirect to a login page.
        $middleware->redirectGuestsTo(null);

        // Validation and error messages follow the client's Accept-Language (English or Portuguese).
        $middleware->api(prepend: [SetLocaleFromHeader::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(fn (DuplicateImport $e) => response()->json([
            'message' => __('This file was already uploaded (import #:id).', ['id' => $e->existing->id]),
            'import_id' => $e->existing->id,
        ], 409));

        $exceptions->render(fn (ImportCannotBeRetried $e) => response()->json([
            'message' => __($e->getMessage()),
        ], 409));

        // Do not leak model class names (or whether a record exists) in 404 responses.
        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => $request->is('api/*')
            ? response()->json(['message' => __('Not found.')], 404)
            : null);
    })->create();
