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

            $redirectAuth = $request->session()->pull('redirect_auth');
            $defaultUrl = $redirectAuth ?: route('catalog.builders', absolute: false);

            return redirect()->intended($defaultUrl);
        } else {
            try {
                $request->authenticate();

                $request->session()->regenerate();

                $redirectAuth = $request->session()->pull('redirect_auth');
                $defaultUrl = $redirectAuth ?: route('catalog.builders', absolute: false);
                $redirectUrl = $request->session()->pull('url.intended', $defaultUrl);

                return response()->json([
                    'message' => 'Авторизация завершена',
                    'redirect' => $redirectUrl,
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
