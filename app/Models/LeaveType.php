<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'leave_name',
    'code',
    'is_paid',
    'requires_approval',
    'gender',
    'religion',
    'status',
])]
class LeaveType extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    public static function normalizeGender(?string $gender): ?string
    {
        return match (strtolower(trim($gender ?? ''))) {
            '', 'all' => null,
            'm', 'male' => 'male',
            'f', 'female' => 'female',
            default => strtolower(trim($gender ?? '')),
        };
    }

    public static function normalizeReligion(?string $religion): ?string
    {
        $normalizedReligion = strtolower(trim($religion ?? ''));

        if ($normalizedReligion === '') {
            return null;
        }

        $religionOptions = config('params.leave_type.religion_options', []);
        if (is_array($religionOptions)) {
            foreach ($religionOptions as $religionOption) {
                if (is_string($religionOption) && strtolower($religionOption) === $normalizedReligion) {
                    return $religionOption;
                }
            }
        }

        return match ($normalizedReligion) {
            'hindu' => 'Hinduism',
            'muslim', 'islamic' => 'Islam',
            'christian' => 'Christianity',
            'sikh' => 'Sikhism',
            'buddhist' => 'Buddhism',
            'jain' => 'Jainism',
            'jewish' => 'Judaism',
            default => $religion,
        };
    }

    public static function religionsMatch(?string $firstReligion, ?string $secondReligion): bool
    {
        $firstReligion = self::normalizeReligion($firstReligion);
        $secondReligion = self::normalizeReligion($secondReligion);

        $normalizedFirstReligion = strtolower($firstReligion ?? '');
        $normalizedSecondReligion = strtolower($secondReligion ?? '');

        return $normalizedFirstReligion === $normalizedSecondReligion
            || ($firstReligion !== null
                && $secondReligion !== null
                && (str_contains($normalizedFirstReligion, $normalizedSecondReligion)
                    || str_contains($normalizedSecondReligion, $normalizedFirstReligion)));
    }

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'requires_approval' => 'boolean',
            'status' => 'integer',
            'religion' => 'string',
        ];
    }

    public function policies(): HasMany
    {
        return $this->hasMany(LeavePolicy::class);
    }

    public function policyForOrganisation(?int $organisationId): ?LeavePolicy
    {
        if ($organisationId === null) {
            return null;
        }

        if ($this->relationLoaded('policies')) {
            return $this->policies->first(
                fn (LeavePolicy $policy): bool => (int) $policy->organisation_id === $organisationId,
            );
        }

        return $this->policies()
            ->where('organisation_id', $organisationId)
            ->first();
    }

    public function requests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }
}
