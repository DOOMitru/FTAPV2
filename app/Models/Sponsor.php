<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sponsor extends Model
{
    /** @use HasFactory<\Database\Factories\SponsorFactory> */
    use HasFactory, HasUlids;

    protected $fillable = ['name', 'logo_path', 'website_url', 'tier', 'sort_order'];

    /**
     * The schema defaults these too, but a database default is applied on
     * INSERT and never hydrated back: a freshly created instance would report
     * no tier at all until the row was reloaded, so isPremium() would be
     * answering about a null.
     */
    protected $attributes = [
        'tier' => 'regular',
        'sort_order' => 0,
    ];

    /**
     * Display order, defined once.
     *
     * The admin index and the public wall both call this, so they cannot
     * disagree about what comes first -- an admin reordering the list needs to
     * see the same sequence the page will render.
     */
    public function scopeOrdered($query)
    {
        return $query
            ->orderByRaw("CASE WHEN tier = 'premium' THEN 0 ELSE 1 END")
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function isPremium(): bool
    {
        return $this->tier === 'premium';
    }

    /**
     * asset(), not Storage::disk('public')->url().
     *
     * The disk's url() is built from APP_URL, so it hardcodes a host into an
     * in-page image: any mismatch -- a dev port, a staging domain, a proxy,
     * http versus https -- gives a broken image and no error. asset() resolves
     * against the actual request host instead, which is why the avatars have
     * always worked. This matches how User::profileImageUrl() already does it.
     */
    public function logoUrl(): string
    {
        return asset('storage/'.$this->logo_path);
    }
}
