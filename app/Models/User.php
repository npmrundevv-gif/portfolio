<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable implements FilamentUser, HasAvatar
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
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
        ];
    }

    /**
     * Only allow users whose email is listed in the ADMIN_EMAILS env var
     * to access the Filament admin panel. ADMIN_EMAILS should be a
     * comma-separated list of email addresses.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        $adminEmailsConfig = config('app.admin_emails', '');

        if (empty(trim($adminEmailsConfig))) {
            return false;
        }

        $allowedEmails = array_filter(
            array_map('trim', explode(',', $adminEmailsConfig))
        );

        return in_array(strtolower($this->email), array_map('strtolower', $allowedEmails), true);
    }

    public function getFilamentAvatarUrl(): ?string
    {
        $settings = \App\Models\SiteSetting::first();

        return $settings && $settings->favicon_path
            ? Storage::url($settings->favicon_path)
            : null;
    }
}
