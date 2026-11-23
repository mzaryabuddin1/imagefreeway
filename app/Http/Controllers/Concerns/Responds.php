<?php

namespace App\Http\Controllers\Concerns;

/** One response shape for the AJAX front end; falls back to normal redirects without JS. */
trait Responds
{
    protected function ok(string $message, ?string $redirect = null, array $extra = [])
    {
        if (request()->expectsJson()) {
            return response()->json(['ok' => true, 'message' => $message, 'redirect' => $redirect, 'reload' => $redirect === null] + $extra);
        }

        return ($redirect ? redirect($redirect) : back())->with('success', $message);
    }

    protected function fail(string $message, int $status = 422)
    {
        if (request()->expectsJson()) {
            return response()->json(['ok' => false, 'message' => $message], $status);
        }

        return back()->withErrors(['error' => $message])->withInput();
    }
}
