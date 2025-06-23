<?php

namespace App\Http\Controllers;

use App\Enum\PaymentStatusEnum;
use App\Enum\UserTariffStatusEnum;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Payment;
use App\Models\UserTariff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $subscribeActive = UserTariff::where('user_id', Auth::user()->id)
            ->where('status', UserTariffStatusEnum::Active)
            ->orderBy('date_end', 'DESC')
            ->get();

        $subscribeEnded = UserTariff::where('user_id', Auth::user()->id)
            ->where('status', UserTariffStatusEnum::Ended)
            ->orderBy('date_end', 'DESC')
            ->get();

        $payments = Payment::where('user_id', Auth::user()->id)
            ->with('paymentTariff')
            ->where('status', PaymentStatusEnum::New)
            ->orderBy('created_at', 'DESC')
            ->get();

        return view('profile.edit', [
            'user' => $request->user(),
            'subscribeActive' => $subscribeActive,
            'subscribeEnded' => $subscribeEnded,
            'payments' => $payments,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
