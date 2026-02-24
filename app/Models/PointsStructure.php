<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PointsStructure extends Model
{
    /** @use HasFactory<\Database\Factories\PointsStructureFactory> */
    use HasFactory, HasUlids;

    protected $table = 'points_structure';

    protected $fillable = [
        'place',
        'points',
    ];
}
