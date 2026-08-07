<?php

namespace App\Models\SalesCrm;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $connection = 'salescrm';

    protected $table = 'personal_access_tokens';
}
