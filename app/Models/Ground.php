<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ground extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'address',
        'city',
        'google_maps_link',
        'image',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(Matches::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForOrganization($query, $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    /**
     * "lat,lng" pulled out of the stored Google Maps link, when it has them.
     *
     * A Maps "place" URL carries the location twice: `@25.076,54.897,119085m` is
     * where the *camera* sat when the link was made (often zoomed out over a
     * whole city), while `!3d25.204!4d55.270` is the pin itself. Preferring the
     * pin is the difference between a preview centred on the ground and one
     * centred on the emirate.
     */
    public function getMapCoordinatesAttribute(): ?string
    {
        $link = (string) $this->google_maps_link;

        if ($link === '') {
            return null;
        }

        if (preg_match('/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/', $link, $m)) {
            return $m[1] . ',' . $m[2];
        }

        if (preg_match('/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/', $link, $m)) {
            return $m[1] . ',' . $m[2];
        }

        return null;
    }

    /**
     * What to ask Google to show for this ground.
     *
     * Coordinates win because they are unambiguous; a short link
     * (maps.app.goo.gl/…) hides them behind a redirect, so those fall back to
     * the typed address. Returns null when there is nothing but a name, where a
     * map would just show whatever the name happens to match somewhere.
     */
    public function getMapQueryAttribute(): ?string
    {
        if ($coordinates = $this->map_coordinates) {
            return $coordinates;
        }

        $parts = array_filter([$this->name, $this->address, $this->city]);

        return count($parts) > 1 ? implode(', ', $parts) : null;
    }

    /** Embeddable map URL, or null when there is nothing worth showing. */
    public function getMapEmbedUrlAttribute(): ?string
    {
        if (! $query = $this->map_query) {
            return null;
        }

        // The keyless embed endpoint: no Maps API key needed, and it renders the
        // same pin the share link points at.
        return 'https://www.google.com/maps?q=' . urlencode($query) . '&z=15&output=embed';
    }

    /** Where "Open in Google Maps" should go — the saved link, else a search. */
    public function getMapExternalUrlAttribute(): ?string
    {
        if ($this->google_maps_link) {
            return $this->google_maps_link;
        }

        if (! $query = $this->map_query) {
            return null;
        }

        return 'https://www.google.com/maps/search/?api=1&query=' . urlencode($query);
    }
}
