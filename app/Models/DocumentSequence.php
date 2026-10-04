<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPharmacy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['pharmacy_id', 'type', 'next_number'])]
class DocumentSequence extends Model
{
    use BelongsToPharmacy;
}
