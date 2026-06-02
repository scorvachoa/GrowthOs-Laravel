<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit()
    {
        return Inertia::render(
            'Profile/Edit',
            [
                'user' => auth()->user(),
            ]
        );
    }

    public function update(
        ProfileUpdateRequest $request
    ) {

        $user = $request->user();

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
        ]);

        if ($request->filled('password')) {

            $user->update([
                'password' => Hash::make(
                    $request->password
                ),
            ]);
        }

        return redirect()
            ->back()
            ->with(
                'success',
                'Profile updated successfully.'
            );
    }
}