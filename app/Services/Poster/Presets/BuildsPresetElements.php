<?php

declare(strict_types=1);

namespace App\Services\Poster\Presets;

/**
 * The editor's element shapes (text, image, shape) with every field filled,
 * for layouts written in code. x / y are PERCENTAGES of the canvas and address
 * the element's centre; width, height and fontSize are pixels at the preset's
 * canvas size — TemplateRenderService reads them that way.
 */
trait BuildsPresetElements
{

    // ---------------------------------------------------------------------
    // Element builders. These exist so a layout below reads as a layout and
    // not as forty repetitions of the same fifteen default keys.
    // ---------------------------------------------------------------------

    protected function text(array $attrs): array
    {
        return array_merge([
            'type' => 'text',
            'placeholder' => null,
            'x' => 50,
            'y' => 50,
            'rotation' => 0,
            'opacity' => 100,
            'zIndex' => 0,
            'layerName' => null,
            'hidden' => false,
            'locked' => false,
            'text' => '',
            'fontSize' => 32,
            'autoSize' => false,
            'width' => 400,
            'fontFamily' => 'Montserrat',
            'fontWeight' => '700',
            'fontStyle' => 'normal',
            'underline' => false,
            'linethrough' => false,
            'skewX' => 0,
            'color' => '#14306b',
            'textAlign' => 'left',
            'textTransform' => 'none',
            'shadow' => null,
            'stroke' => null,
            'strokeWidth' => 0,
            'dependsOn' => null,
        ], $attrs, ['baseFontSize' => $attrs['fontSize'] ?? 32]);
    }

    protected function image(array $attrs): array
    {
        return array_merge([
            'type' => 'image',
            'placeholder' => null,
            'x' => 50,
            'y' => 50,
            'rotation' => 0,
            'opacity' => 100,
            'zIndex' => 0,
            'layerName' => null,
            'hidden' => false,
            'locked' => false,
            'width' => 100,
            'height' => 100,
            'borderRadius' => 0,
        ], $attrs);
    }

    protected function shape(array $attrs): array
    {
        return array_merge([
            'type' => 'shape',
            'placeholder' => null,
            'x' => 50,
            'y' => 50,
            'rotation' => 0,
            'opacity' => 100,
            'zIndex' => 0,
            'layerName' => null,
            'hidden' => false,
            'locked' => false,
            'shapeType' => 'rect',
            'iconName' => null,
            'fill' => 'rgba(255,255,255,0.94)',
            'fillOpacity' => 1,
            'stroke' => null,
            'strokeWidth' => 0,
            'width' => 200,
            'height' => 100,
            'scaleX' => 1,
            'scaleY' => 1,
            'rx' => 0,
            'ry' => 0,
            'shadow' => null,
            'borderRadii' => null,
        ], $attrs);
    }
}
