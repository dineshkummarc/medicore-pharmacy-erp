<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPharmacy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['pharmacy_id', 'key', 'value'])]
class Setting extends Model
{
    use BelongsToPharmacy;
}
