<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enum\UserTariffStatusEnum;
use App\Services\Tariff;
use App\Traits\ModelTableName;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Propaganistas\LaravelPhone\Casts\E164PhoneNumberCast;
use Propaganistas\LaravelPhone\Casts\RawPhoneNumberCast;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;
    use ModelTableName;
    use CanResetPassword;

    protected const FREE_CONTACTS = 3;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'user_role_id',
        'tubus_id',
        'company_inn',
        'company_title',
        'free_contacts',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
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
            'phone' => RawPhoneNumberCast::class.':RU',
        ];
    }

    public function userRole(): BelongsTo
    {
        return $this->belongsTo(UserRole::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * @param $permission
     * @return bool
     */
    public function hasPermission(string $permission)
    {
        // https://laravel.demiart.ru/guide-to-roles-and-permissions/
        return true;
    }

    public function getFirstActiveTariff(): ?UserTariff
    {
        return $this->hasMany(UserTariff::class)
            ->where('status', UserTariffStatusEnum::Active)
            ->orderBy('date_start', 'ASC')
            ->first();
    }

    public function getLeftContacts(): array
    {
        $contacts = $this->hasMany(UserTariff::class)
            ->selectRaw('SUM(count_contacts_left) as count_contacts_left, SUM(count_contacts) as count_contacts')
            ->where('status', UserTariffStatusEnum::Active)
            ->first();

        if (!is_null($contacts) AND !is_null($contacts->count_contacts_left)) {
            return [
                'count_contacts_left' => $contacts->count_contacts_left,
                'count_contacts' => $contacts->count_contacts,
            ];
        } else {
            return [
                'count_contacts_left' => $this->free_contacts,
                'count_contacts' => self::FREE_CONTACTS,
            ];
        }
    }

    public function checkAccessToContact(ApiPostUser $postUser)
    {
        $tariffService = new Tariff();
        return $tariffService->checkContactAccess($postUser);
    }

    public function checkOpenContact(ApiPostUser $postUser): bool
    {
        $tariffService = new Tariff();
        return $tariffService->checkOpenContact($postUser);
    }
}
