<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckEmailController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('auth.check-email', [
            'email' => $request->user()?->email
                ?? $request->session()->get('email', $request->query('email')),
        ]);
    }
}
