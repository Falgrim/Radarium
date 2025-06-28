<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Infrastructures\Facades\Repositories;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;

class RegisteredUserController extends Controller
{
    private function checkAllowRegistrations(): bool
    {
        $cronCountPosts = Repositories::setting()->findByName('allow_registration');
        return (bool)$cronCountPosts->value;
    }

    /**
     * Display the registration view.
     */
    public function create(Request $request): View
    {
        if (!$this->checkAllowRegistrations()) {
            abort(403);
        }

        $userRoleList = Repositories::userRole()->getList();

        return view('auth.register', [
            'request'       => $request,
            'userRoleList'  => $userRoleList,
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(UserRequest $request): JsonResponse
    {
        try {
            if (!$this->checkAllowRegistrations()) {
                abort(403);
            }

            $validated = $request->validated();

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'company_title' => $validated['company_title'] ?? '',
                'company_inn' => $validated['company_inn'] ?? '',
                'user_role_id' => $validated['user_role_id'],
                'password' => Hash::make($validated['password']),
            ]);

            try {
                event(new Registered($user));
            } catch (UnexpectedResponseException $e) {
                // Игнорим ошибку отправки письма, если введенная почта с проблемой валидации
            }

            Auth::login($user);

            $redirectTo = session('redirect_auth');
            if ($redirectTo) {
                $redirectTo = redirect($redirectTo);
            }

            return response()->json([
                'message' => 'Регистрация завершена',
                'redirect' => $redirectTo ? $redirectTo : route('dashboard', absolute: false)
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
