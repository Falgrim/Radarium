<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Infrastructures\Facades\Repositories;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

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
    public function store(UserRequest $request): RedirectResponse
    {
        if (!$this->checkAllowRegistrations()) {
            abort(403);
        }

        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'user_role_id' => $validated['user_role_id'],
            'password' => Hash::make($validated['password']),
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
