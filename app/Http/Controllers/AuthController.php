<?php

// app/Http/Controllers/Api/AuthController.php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use App\Models\Favorite;
use App\Models\Cart;


use Illuminate\Validation\Rules\Password as PasswordRule;

class AuthController extends Controller
{
    public function register(Request $request) {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', PasswordRule::defaults()],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;
        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function login(Request $request) {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($credentials)) {
            return response()->json(['message' => 'بيانات الاعتماد غير صحيحة'], 401);
        }

        $user = auth()->user();

        $token = $user->createToken('auth_token')->plainTextToken;

        $sessionFavorites = $request->session()->get('favorites', []);
        if (!empty($sessionFavorites)) {
            $user->favorites()->syncWithoutDetaching($sessionFavorites);
            session()->forget('favorites');
        }

        $guestUuid = $request->cookie('guest_uuid');
        if ($guestUuid) {
            Favorite::where('guest_uuid', $guestUuid)
                    ->update([
                        'user_id' => $user->id,
                        'guest_uuid' => null
                    ]);
        }
        $guestUuid = $request->cookie('guest_uuid');
    if ($guestUuid) {
        Cart::where('guest_uuid', $guestUuid)
            ->update([
                'user_id' => $user->id,
                'guest_uuid' => null
            ]);
    }

        return response()->json([
            'user' => $user,
            'token' => $token
        ])->withoutCookie('guest_uuid');
    }


    public function logout(Request $request) {
        $request->user()->tokens()->delete();

        $token = auth()->user()->currentAccessToken();

     if ($token && method_exists($token, 'delete')) {
    $token->delete();
    }

    }

    public function sendResetLink(Request $request) {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => 'تم إرسال الرابط إلى بريدك'])
            : response()->json(['message' => 'حدث خطأ أثناء الإرسال'], 500);
    }

    public function resetPassword(Request $request) {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->save();
                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => 'تم تحديث كلمة المرور'])
            : response()->json(['message' => 'فشل التحديث'], 500);
    }
}
