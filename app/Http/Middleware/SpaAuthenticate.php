<?php

namespace App\Http\Middleware;

use App\Models\UserModel;
use Closure;
use Illuminate\Http\Request;

class SpaAuthenticate
{
    public function handle(Request $request, Closure $next)
    {
        $session = $request->session();
        $id = $session->get('user_id');
        $user = $id ? (new UserModel())->find($id) : null;

        if (!$user || !$user['is_active']) {
            if ($id) {
                $session->invalidate();
                $session->regenerateToken();
            }
            return response()->json([
                'status' => false,
                'message' => 'Silakan login terlebih dahulu.',
            ], 401);
        }

        $session->put(['user_role' => $user['role'], 'therapist_id' => $user['therapist_id']]);
        return $next($request);
    }
}
