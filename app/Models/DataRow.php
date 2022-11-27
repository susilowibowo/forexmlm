<?php

namespace App\Models;

use TCG\Voyager\Models\DataRow as BaseModel;

class DataRow extends BaseModel
{
    public function setDetailsAttribute($value)
    {
        // Override BaseModel's that'd  break compatibility because of json_encode
        $this->attributes['details'] = $value;
    }
}
