<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
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
    public function store(Request $request): RedirectResponse
    {
        if (!$this->checkAllowRegistrations()) {
            abort(403);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'phone:mobile,RU'],
            'user_role_id' => ['exists:App\Models\UserRole,id'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'user_role_id' => $request->user_role_id,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
