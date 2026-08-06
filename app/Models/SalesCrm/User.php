<?php

namespace App\Models\SalesCrm;

use App\Support\SalesCrmRoles;
use Database\Factories\SalesCrmUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * Sales CRM user (`cm_users`) — also the HRMS CRM login identity.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'email_verified_at', 'remember_token'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<SalesCrmUserFactory> */
    use HasFactory, Notifiable;

    protected $connection = 'salescrm';

    protected $table = 'users';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function newFactory(): SalesCrmUserFactory
    {
        return SalesCrmUserFactory::new();
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class, 'user_id');
    }

    public function canAccessHrms(): bool
    {
        return SalesCrmRoles::userHasLoginAccess($this->id);
    }

    /**
     * @return list<string>
     */
    public function roleNames(): array
    {
        return SalesCrmRoles::roleNamesForUser($this->id);
    }
}
