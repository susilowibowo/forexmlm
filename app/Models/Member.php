<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Member extends Model
{
    use SoftDeletes;

    protected $fillable =[
        'name',
        'role_id',
        'parent_id',
    ];

    public function parentId()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function grandChildren()
    {
        return $this->children()->with('grandChildren');
    }

    public static function calculateBonus($memberId)
    {
        $bonus = 0;
        $childBonus = 0;
        $totalBonus = 0;
        $member = self::where('id', $memberId)->firstOrFail();

        $bonus = count($member->grandChildren()->get());
        foreach ($member->grandChildren()->get() as $child) {
            $childBonus = $childBonus + (count($child->children()->get()) * 0.5);
        }
        $totalBonus = $bonus + $childBonus;
        return $totalBonus;
    }
}
