<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'google_id',
        'password',
        'phone',
        'phone_verify',
        'type',
        // Araç sahibi alt üyelik: 'yes' = Çoklu Araç Yönetim modu, 'no' = tek araç
        'is_multi_cargo_owner',
        'code',
        'status',
        'panel_tour_completed_at',
        'email_verified_at',
        'email_verify',
        'verified',
        'with_admin',
        'driving_licance_doc',
        'psikoteknik_doc',
        'src_doc',
        'id_image',
        'profile_image',
        'payment_confirm',
        'end_payment_date',
        'phoneFormatted',
        'suitable_postings',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    /** Aldığı paketler (en fazla bir tanesi `active`) */
    public function subscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }

    protected $hidden = [
        // PayTR kayıtlı kart grubu kimliği (otomatik ödeme)
        'paytr_utoken',
        'password',
        'remember_token',
        'google_id',
    ];

    /**
     * Accessor'ların JSON çıktısına eklenmesi.
     *
     * @var list<string>
     */
    protected $appends = [
        'full_name',
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
            'email_verify' => 'boolean',
            'phone_verify' => 'boolean',
            'password' => 'hashed',
            'panel_tour_completed_at' => 'datetime',
        ];
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function userDetail(): HasOne
    {
        return $this->hasOne(UserDetail::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    /** Kullanıcı başına tek kayıt (`user_devices`, updateOrCreate). */
    public function pushDevice(): HasOne
    {
        return $this->hasOne(UserDevice::class);
    }

    public function activePushDevice(): ?UserDevice
    {
        return $this->pushDevice;
    }

    /** Push bildirimi yalnızca `users.status = active` hesaplara gider. */
    public function isActiveAccount(): bool
    {
        return (string) $this->status === UserStatus::ACTIVE->value;
    }

    public function isPendingAccount(): bool
    {
        return (string) $this->status === UserStatus::PENDING->value;
    }

    public function isInactiveAccount(): bool
    {
        return (string) $this->status === UserStatus::INACTIVE->value;
    }

    /** Tüm Sanctum oturumlarını sonlandırır. */
    public function terminateAllSessions(): void
    {
        $this->tokens()->delete();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'user_id', 'id');
    }

    /** Bu kullanıcının yazdığı değerlendirmeler (`comments.sender_user_id`). */
    public function commentsSent(): HasMany
    {
        return $this->hasMany(Comment::class, 'sender_user_id', 'id');
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(function () {
            return ucfirst($this->first_name) . " " . Str::upper(Str::substr($this->last_name, 0, 1)) . ".";
        });
    }
    public function phoneFormatted(): Attribute
    {
        return Attribute::get(function () {
            return '+90 ' . Str::substr($this->phone, 0, 3) . ' ' . Str::substr($this->phone, 3, 3) . ' ** **';
        });
    }
    public function fullNameOnlyFirstChars(): Attribute
    {
        return Attribute::get(function () {
            $result = collect(explode(' ', $this->full_name))->map(function ($name) {
                return Str::upper(Str::substr($name, 0, 1)) . Str::repeat('*', Str::length($name) - 1);
            })->implode('');
            return $result;
        });
    }
    public function orders(): HasMany
    {
        return $this->hasMany(UserOrder::class, 'user_id', 'id');
    }
}
