<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Country Model
 *
 * READ-ONLY: This model connects to the worker_db database.
 * Do not attempt to create, update, or delete records.
 */
class Country extends Model
{
    protected $connection = 'worker_db';

    protected $table = 'mst_countries';

    protected $primaryKey = 'cty_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'cty_id',
        'cty_desc',
    ];
}
