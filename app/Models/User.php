<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'contractor_clab_no',
        'name',
        'email',
        'phone',
        'person_in_charge',
        'password',
        'role',
        'email_verified_at',
        'tutorial_completed',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'tutorial_completed' => 'array',
        ];
    }

    /**
     * Check if a specific tutorial page has been completed
     */
    public function hasTutorialCompleted(string $page): bool
    {
        $completed = $this->tutorial_completed ?? [];

        return isset($completed[$page]) && $completed[$page] === true;
    }

    /**
     * Mark a tutorial page as completed
     */
    public function markTutorialCompleted(string $page): void
    {
        $completed = $this->tutorial_completed ?? [];
        $completed[$page] = true;
        $this->update(['tutorial_completed' => $completed]);
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->map(fn (string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }

    /**
     * Check if user is a super admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /**
     * Check if user is an admin (regular admin)
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is finance
     */
    public function isFinance(): bool
    {
        return $this->role === 'finance';
    }

    /**
     * Check if user has admin privileges (super admin or admin only)
     */
    public function hasAdminAccess(): bool
    {
        return in_array($this->role, ['super_admin', 'admin']);
    }

    /**
     * Check if user is a client
     */
    public function isClient(): bool
    {
        return $this->role === 'client';
    }

    /**
     * Get all payroll submissions for this contractor
     */
    public function payrollSubmissions()
    {
        return $this->hasMany(PayrollSubmission::class, 'contractor_clab_no', 'contractor_clab_no');
    }
}
