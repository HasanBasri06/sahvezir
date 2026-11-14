<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'google_id',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'google_id',
        'remember_token',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'has_password',
    ];

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /** Favorilerim */
    public function favoriteProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'favorites')
            ->using(Favorite::class)
            ->withPivot('favorite_list_id')
            ->withTimestamps();
    }

    /** Favorileri gruplamak için açtığı kategoriler */
    public function favoriteLists(): HasMany
    {
        return $this->hasMany(FavoriteList::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Google ile kayıt olup henüz şifre belirlemeyen kullanıcılar için false.
     */
    protected function hasPassword(): Attribute
    {
        return Attribute::get(fn () => $this->password !== null);
    }

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
}
