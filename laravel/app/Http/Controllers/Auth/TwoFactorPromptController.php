<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class TwoFactorPromptController extends Controller
{
    public function __invoke(): View
    {
        return view('auth.two-factor');
    }
}
