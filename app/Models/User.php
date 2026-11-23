<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable([
    'name',
    'email',
    'phone',
    'password',
    'role',
    'membership_tier',
    'prescription_od',
    'prescription_os',
    'last_exam_date',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_exam_date' => 'date:Y-m-d',
            'password' => 'hashed',
        ];
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(UserToken::class);
    }

    /**
     * Membuat personal API token baru untuk autentikasi Flutter.
     */
    public function createApiToken(string $deviceName = 'flutter-app', int $daysValid = 30): string
    {
        $plainTextToken = Str::random(64);

        $this->tokens()->create([
            'name' => $deviceName,
            'token' => hash('sha256', $plainTextToken),
            'expires_at' => now()->addDays($daysValid),
        ]);

        return $plainTextToken;
    }
}
