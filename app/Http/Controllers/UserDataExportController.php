<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserDataExportController extends Controller
{
    public function download(Request $request, User $user, string $file): StreamedResponse
    {
        abort_unless(Auth::id() === $user->id, 403);

        $path = "exports/{$user->id}/{$file}";
        abort_unless(Storage::exists($path), 404);

        return Storage::download($path, "data-export-{$user->id}-{$file}");
    }
}
