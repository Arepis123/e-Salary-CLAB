<?php

namespace App\Auth;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\DB;

class ThirdPartyUserProvider implements UserProvider
{
    /**
     * Retrieve a user by their unique identifier.
     */
    public function retrieveById($identifier): ?Authenticatable
    {
        return User::find($identifier);
    }

    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     */
    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        $user = User::find($identifier);

        return $user && $user->remember_token === $token ? $user : null;
    }

    /**
     * Update the "remember me" token for the given user in storage.
     */
    public function updateRememberToken(Authenticatable $user, $token): void
    {
        $user->remember_token = $token;
        $user->save();
    }

    /**
     * Retrieve a user by the given credentials.
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        if (empty($credentials) || (count($credentials) === 1 && array_key_exists('password', $credentials))) {
            return null;
        }

        $username = $credentials['username'] ?? $credentials['email'] ?? null;

        if (! $username) {
            return null;
        }

        // First, check if user exists locally as admin/super_admin
        $localUser = User::where(function ($query) use ($username) {
            $query->where('username', $username)
                ->orWhere('email', $username);
        })->first();

        // If local user is admin or super_admin, return immediately
        if ($localUser && in_array($localUser->role, ['admin', 'super_admin'])) {
            return $localUser;
        }

        // For non-admin users, check third-party database
        $thirdPartyUser = DB::connection('auth_db')
            ->table('tbl_user')
            ->where(function ($query) use ($username) {
                $query->where('u_username', $username)
                    ->orWhere('u_email1', $username);
            })
            ->first();

        if (! $thirdPartyUser) {
            return null;
        }

        // Sync/create user in local database
        $localUser = User::updateOrCreate(
            ['username' => $thirdPartyUser->u_username],
            [
                'contractor_clab_no' => $thirdPartyUser->u_username, // u_username is the contractor CLAB number
                'name' => $thirdPartyUser->u_fname ?? 'Unknown',
                'email' => $thirdPartyUser->u_email1 ?? '',
                'phone' => $thirdPartyUser->u_contactno ?? null,
                'person_in_charge' => $thirdPartyUser->u_lname ?? null,
                'role' => 'client',
                'email_verified_at' => now(),
            ]
        );

        return $localUser;
    }

    /**
     * Validate a user against the given credentials.
     * This is where we check against the third-party database with MD5.
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        // Get password from credentials
        $password = $credentials['password'] ?? null;

        if (! $password || ! $user->username) {
            return false;
        }

        // For admin and super_admin, validate against local bcrypt password
        if (in_array($user->role, ['admin', 'super_admin'])) {
            return \Illuminate\Support\Facades\Hash::check($password, $user->password);
        }

        // For client users, check against third-party database (MD5 hash)
        $thirdPartyUser = DB::connection('auth_db')
            ->table('tbl_user')
            ->where('u_username', $user->username)
            ->first();

        if (! $thirdPartyUser) {
            return false;
        }

        // Validate MD5 password
        $isValid = md5($password) === $thirdPartyUser->u_password;

        // Log failed login attempts
        if (! $isValid) {
            DB::table('failed_login_attempts')->insert([
                'username' => $user->username,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'attempted_at' => now(),
            ]);
        }

        return $isValid;
    }

    /**
     * Rehash the user's password if required and supported.
     */
    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void
    {
        // We don't store passwords locally, so no rehashing needed
    }
}
