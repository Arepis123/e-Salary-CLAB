<?php

namespace App\Livewire\Actions;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class Logout
{
    /**
     * Log the current user out of the application.
     */
    public function __invoke()
    {
        // Capture user info BEFORE logout (since auth()->user() will be null after logout)
        $user = Auth::user();

        if ($user) {
            // Log the logout activity
            ActivityLog::create([
                'user_id' => $user->id,
                'contractor_clab_no' => $user->contractor_clab_no,
                'user_name' => $user->name ?? $user->company_name,
                'user_email' => $user->email,
                'module' => 'authentication',
                'action' => 'logout',
                'description' => 'User logged out successfully',
                'subject_type' => get_class($user),
                'subject_id' => $user->id,
                'properties' => [],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'url' => request()->fullUrl(),
                'method' => request()->method(),
            ]);
        }

        Auth::guard('web')->logout();

        Session::invalidate();
        Session::regenerateToken();

        return redirect('/');
    }
}
