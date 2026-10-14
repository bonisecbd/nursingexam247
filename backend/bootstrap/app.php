<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

$env = new class
{
    public function env($key, $default = null)
    {
        // Check $_SERVER first
        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }
        // Check .env file
        $envFile = __DIR__.'/../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                $parts = explode('=', $line, 2);
                if (count($parts) === 2 && trim($parts[0]) === $key) {
                    return trim($parts[1]);
                }
            }
        }
        // Check getenv
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        return $default;
    }
};

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: [
            __DIR__.'/../routes/api.php',
            __DIR__.'/../routes/api_progress.php',
            __DIR__.'/../routes/api_challenge.php',
            __DIR__.'/../routes/api_notifications.php',
            __DIR__.'/../routes/api_settings.php',
        ],
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API model-binding misses return a clean JSON error instead of the raw
        // "No query results for model [App\Models\X] {id}" exception text.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') && $e->getPrevious() instanceof ModelNotFoundException) {
                return response()->json(['message' => 'Resource not found.'], 404);
            }
        });
    })->create();
