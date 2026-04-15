<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DigestUnsubscribeController extends Controller
{
    public function __invoke(Request $request, User $user): View
    {
        abort_unless($request->hasValidSignature(), 403);

        if ($user->digest_enabled) {
            $user->update(['digest_enabled' => false]);
        }

        return view('digest.unsubscribed', ['user' => $user]);
    }
}
