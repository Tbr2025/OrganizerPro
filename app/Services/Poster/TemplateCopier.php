<?php

declare(strict_types=1);

namespace App\Services\Poster;

use App\Models\TournamentTemplate;
use Illuminate\Support\Facades\Storage;

/**
 * A copy of a poster template whose files are its OWN.
 *
 * Every image the template references — the background, overlay images, and
 * uploaded-image layers inside layout_json — is copied to a new path. Sharing a
 * path is not safe here: replacing a background deletes the old file, which
 * would blank every other template still pointing at it.
 *
 * Used by "Duplicate" on the templates page, by starting a new season, and by
 * the template library (TemplateLibrary) — a null tournament is the library.
 */
class TemplateCopier
{
    public function copy(TournamentTemplate $template, ?int $toTournamentId, ?string $name = null, bool $keepDefault = false): TournamentTemplate
    {
        $new = $template->replicate();
        $new->tournament_id = $toTournamentId;
        $new->name = $name ?? $template->name;
        $new->is_default = $keepDefault ? $template->is_default : false;

        if ($template->background_image && Storage::disk('public')->exists($template->background_image)) {
            $ext = pathinfo($template->background_image, PATHINFO_EXTENSION);
            $newPath = 'tournament_templates/' . uniqid('bg_') . '.' . $ext;
            Storage::disk('public')->copy($template->background_image, $newPath);
            $new->background_image = $newPath;
        }

        // Copy every uploaded-image file the template references and remap the
        // paths in BOTH overlay_images AND layout_json. Uploaded-image layers embed
        // an `imagePath`; previously only plain-string overlay entries were copied,
        // so object-form overlays (and the layout_json copies) kept the ORIGINAL
        // paths and rendered blank on the duplicate — the "missing layers" bug.
        $pathMap = [];
        $copyPath = function (?string $old) use (&$pathMap) {
            if (! $old || ! is_string($old)) {
                return $old;
            }
            if (array_key_exists($old, $pathMap)) {
                return $pathMap[$old];
            }
            if (! Storage::disk('public')->exists($old)) {
                return $pathMap[$old] = $old;
            }
            $ext = pathinfo($old, PATHINFO_EXTENSION);
            $dir = str_contains($old, '/overlays/') ? 'tournament_templates/overlays/' : 'tournament_templates/';
            $newPath = $dir . uniqid('ov_') . ($ext ? '.' . $ext : '');
            Storage::disk('public')->copy($old, $newPath);

            return $pathMap[$old] = $newPath;
        };

        // overlay_images: array of string paths (legacy) or objects with imagePath.
        if (is_array($template->overlay_images)) {
            $overlays = [];
            foreach ($template->overlay_images as $overlay) {
                if (is_string($overlay)) {
                    $overlays[] = $copyPath($overlay);
                } elseif (is_array($overlay) && isset($overlay['imagePath'])) {
                    $overlay['imagePath'] = $copyPath($overlay['imagePath']);
                    $overlays[] = $overlay;
                } else {
                    $overlays[] = $overlay;
                }
            }
            $new->overlay_images = $overlays;
        }

        // layout_json: remap any element (e.g. uploadedImage) that embeds imagePath.
        if (is_array($template->layout_json)) {
            $layout = $template->layout_json;
            foreach ($layout as &$el) {
                if (is_array($el) && ! empty($el['imagePath'])) {
                    $el['imagePath'] = $copyPath($el['imagePath']);
                }
            }
            unset($el);
            $new->layout_json = $layout;
        }

        $new->save();

        return $new;
    }
}
