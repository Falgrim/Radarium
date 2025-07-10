<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): JsonResponse|RedirectResponse
    {
        if(!$request->ajax()){
            $request->authenticate();

            $request->session()->regenerate();

            $redirectTo = session('redirect_auth');
            if ($redirectTo) {
                return redirect()->intended(redirect($redirectTo));
            }

            return redirect()->intended(route('catalog.specialists', absolute: false));
        } else {
            try {
                $request->authenticate();

                $request->session()->regenerate();

                $redirectTo = session('redirect_auth');
                if ($redirectTo) {
                    $redirectTo = redirect($redirectTo);
                }

                return response()->json([
                    'message' => 'Авторизация завершена',
                    'redirect' => $redirectTo ? $redirectTo : route('catalog.specialists', absolute: false),
                ]);
            } catch (ValidationException $e) {
                return response()->json([
                    'message' => 'Ошибка!',
                    'errors' => $e->validator->getMessageBag(),
                ], 422);
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Ошибка!',
                    'error' => $e->getMessage(),
                ], 500);
            }
        }
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
