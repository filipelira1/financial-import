<?php

use App\Application\Import\Exceptions\DuplicateImport;
use App\Application\Import\Exceptions\ImportCannotBeRetried;
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
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(fn (DuplicateImport $e) => response()->json([
            'message' => $e->getMessage(),
            'import_id' => $e->existing->id,
        ], 409));

        $exceptions->render(fn (ImportCannotBeRetried $e) => response()->json([
            'message' => $e->getMessage(),
        ], 409));

        // Do not leak model class names (or whether a record exists) in 404 responses.
        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => $request->is('api/*')
            ? response()->json(['message' => 'Not found.'], 404)
            : null);
    })->create();
