<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SpaRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        if (!in_array($request->session()->get('user_role'), $roles, true)) {
            return response()->json(['status' => false, 'message' => 'Akses ditolak.'], 403);
        }

        return $next($request);
    }
}
