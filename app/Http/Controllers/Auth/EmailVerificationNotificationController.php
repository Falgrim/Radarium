<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            Log::warning('Не удалось повторно отправить письмо подтверждения', [
                'user_id' => $user->id,
                'email' => $user->email,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            $request->session()->put('verification_mail_failed', true);

            return back()->with('status', 'verification-link-failed');
        }

        $request->session()->forget('verification_mail_failed');

        return back()->with('status', 'verification-link-sent');
    }
}
