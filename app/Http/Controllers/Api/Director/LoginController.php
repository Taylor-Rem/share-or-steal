<?php

namespace App\Http\Controllers\Api\Director;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** POST /api/director/login — lets the panel validate the password before storing it as its key. */
class LoginController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate(['password' => ['required', 'string']]);

        if (! hash_equals((string) config('game.director_password'), $data['password'])) {
            return response()->json(['message' => 'Wrong password.'], 401);
        }

        return response()->json((object) []);
    }
}
