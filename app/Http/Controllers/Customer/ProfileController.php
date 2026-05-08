<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function edit()
    {
        return view('customer.profile.edit', ['user' => auth()->user()]);
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'name'             => ['required', 'string', 'max:120'],
            'email'            => ['required', 'email', 'max:160', Rule::unique('users', 'email')->ignore($user->id)],
            'phone'            => ['nullable', 'string', 'max:30'],
            'address'          => ['nullable', 'string', 'max:500'],
            'current_password' => ['nullable', 'required_with:new_password', 'string'],
            'new_password'     => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        if (!empty($data['new_password'])) {
            if (!Hash::check($data['current_password'] ?? '', $user->password)) {
                return back()
                    ->withErrors(['current_password' => 'Kata sandi saat ini salah.'])
                    ->withInput();
            }
            $user->password = $data['new_password'];
        }

        $user->fill([
            'name'    => $data['name'],
            'email'   => $data['email'],
            'phone'   => $data['phone']   ?? null,
            'address' => $data['address'] ?? null,
        ])->save();

        return back()->with('success', 'Profil berhasil diperbarui.');
    }
}
