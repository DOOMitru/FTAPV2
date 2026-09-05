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

    /**
     * Integer columns, cast so they arrive as integers.
     *
     * Not cosmetic and not test-only. PDO's MySQL driver returns every column
     * as a STRING by default, where SQLite returns typed values -- so without
     * this the same row is 5 in development and "5" in production, and every
     * identity comparison and int-typed parameter behaves differently on the
     * two. The casts make the model the authority on its own types rather than
     * the driver.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'place' => 'integer',
            'points' => 'integer',
        ];
    }
}
