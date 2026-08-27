<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentCustomField extends Model
{
    protected $fillable = [
        'tournament_id',
        'form',
        'label',
        'type',
        'options',
        'validation',
        'conditions',
        'condition_match',
        'help_text',
        'section',
        'required',
        'visible',
        'sort_order',
    ];

    protected $casts = [
        'options' => 'array',
        'validation' => 'array',
        'conditions' => 'array',
        'required' => 'boolean',
        'visible' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Supported field types → human label.
     *
     * The first six predate the rest and must keep their exact keys: they are stored in the
     * `type` column of every custom field already created, and renaming one would blank those
     * fields on the public form.
     */
    public const TYPES = [
        'text' => 'Text',
        'textarea' => 'Paragraph',
        'number' => 'Number',
        'dropdown' => 'Dropdown (one of)',
        'checkbox' => 'Checkbox (Yes/No)',
        'date' => 'Date',
        // Added 2026-08-27
        'radio' => 'Radio buttons (one of)',
        'checkbox_group' => 'Checkboxes (several)',
        'multiselect' => 'Multi-select list',
        'email' => 'Email',
        'tel' => 'Phone',
        'url' => 'Website / URL',
        'time' => 'Time',
        'datetime' => 'Date and time',
        'month' => 'Month and year',
        'year' => 'Year',
        'file' => 'File upload',
        'heading' => 'Heading (no input)',
        'divider' => 'Divider (no input)',
    ];

    /** Types that present a fixed set of choices, so they need `options`. */
    public const OPTION_TYPES = ['dropdown', 'radio', 'checkbox_group', 'multiselect'];

    /** Types whose answer is a list rather than a single value. */
    public const MULTI_VALUE_TYPES = ['checkbox_group', 'multiselect'];

    /** Types that are layout only — they collect nothing and are never validated or stored. */
    public const LAYOUT_TYPES = ['heading', 'divider'];

    /** How a field's conditions combine. NULL/absent means the field is always shown. */
    public const MATCH_ALL = 'all';
    public const MATCH_ANY = 'any';
    public const MATCH_NONE = 'none';

    /** Condition operators → human label, for the builder's dropdown. */
    public const OPERATORS = [
        'equals' => 'is',
        'not_equals' => 'is not',
        'contains' => 'contains',
        'not_contains' => 'does not contain',
        'gt' => 'is greater than',
        'lt' => 'is less than',
        'filled' => 'has any value',
        'empty' => 'is empty',
    ];

    public function needsOptions(): bool
    {
        return in_array($this->type, self::OPTION_TYPES, true);
    }

    public function isMultiValue(): bool
    {
        return in_array($this->type, self::MULTI_VALUE_TYPES, true);
    }

    public function isLayoutOnly(): bool
    {
        return in_array($this->type, self::LAYOUT_TYPES, true);
    }

    public function isFile(): bool
    {
        return $this->type === 'file';
    }

    /**
     * The Laravel validation rules this field contributes, as an array of rule strings.
     *
     * Built from the type plus the curated `validation` array — never from an operator-typed
     * rule string, because a rule an organizer types can reach the database (`unique:users,...`)
     * or throw on every submission. A field created before validation existed has
     * `validation = null` and gets exactly the rules it always got.
     *
     * @return list<string>
     */
    public function validationRules(): array
    {
        if ($this->isLayoutOnly()) {
            return [];   // a heading collects nothing
        }

        $v = is_array($this->validation) ? $this->validation : [];
        $rules = [];

        // A checkbox that is required means "must be ticked", which is `accepted`, not `required`
        // — `required` passes on the string "0".
        if ($this->type === 'checkbox') {
            return [$this->required ? 'accepted' : 'nullable'];
        }

        $rules[] = $this->required ? 'required' : 'nullable';

        if ($this->isMultiValue()) {
            $rules[] = 'array';
            if (isset($v['min_choices']) && $v['min_choices'] !== '') {
                $rules[] = 'min:' . (int) $v['min_choices'];
            }
            if (isset($v['max_choices']) && $v['max_choices'] !== '') {
                $rules[] = 'max:' . (int) $v['max_choices'];
            }

            return $rules;
        }

        switch ($this->type) {
            case 'number':
            case 'year':
                $rules[] = 'numeric';
                if (isset($v['min']) && $v['min'] !== '') {
                    $rules[] = 'min:' . $v['min'];
                }
                if (isset($v['max']) && $v['max'] !== '') {
                    $rules[] = 'max:' . $v['max'];
                }
                break;

            case 'email':
                $rules[] = 'email:rfc';
                $rules[] = 'max:255';
                break;

            case 'url':
                $rules[] = 'url';
                $rules[] = 'max:500';
                break;

            case 'tel':
                $rules[] = 'string';
                $rules[] = 'max:30';
                break;

            case 'date':
            case 'datetime':
            case 'month':
                $rules[] = $this->type === 'datetime' ? 'date' : 'date';
                if (! empty($v['after'])) {
                    $rules[] = 'after_or_equal:' . $v['after'];
                }
                if (! empty($v['before'])) {
                    $rules[] = 'before_or_equal:' . $v['before'];
                }
                break;

            case 'time':
                $rules[] = 'date_format:H:i';
                break;

            case 'file':
                $rules[] = 'file';
                $types = array_filter(array_map('trim', explode(',', (string) ($v['file_types'] ?? ''))));
                if ($types !== []) {
                    $rules[] = 'mimes:' . implode(',', $types);
                }
                $rules[] = 'max:' . (int) ($v['file_max_kb'] ?? 4096);
                break;

            case 'dropdown':
            case 'radio':
                $rules[] = 'string';
                if (! empty($this->options)) {
                    // Options can contain commas, which `in:` treats as a separator — Rule::in
                    // handles the quoting for us.
                    $rules[] = \Illuminate\Validation\Rule::in($this->options);
                }
                break;

            default:   // text, textarea and anything new that is plain text
                $rules[] = 'string';
                if (isset($v['minlength']) && $v['minlength'] !== '') {
                    $rules[] = 'min:' . (int) $v['minlength'];
                }
                $rules[] = 'max:' . (int) ($v['maxlength'] ?? 1000);
                if (! empty($v['pattern'])) {
                    // Delimiters are added here so the stored value is a plain pattern and can
                    // never smuggle in modifiers like /e.
                    $rules[] = 'regex:/^' . str_replace('/', '\\/', $v['pattern']) . '$/u';
                }
                break;
        }

        return $rules;
    }

    /**
     * Should this field be shown, given the answers so far?
     *
     * `all` is AND, `any` is OR, `none` is NOT-any. A field with no conditions is always shown,
     * which is what every field created before this feature reads back as.
     *
     * The same evaluation runs in the browser (to show and hide live) and here on the server —
     * and the server's answer is the one that counts: a hidden field must not be validated, or a
     * required question nobody was asked would block the whole form.
     *
     * @param  array<string, mixed>  $answers  keyed by custom field id and by core form key
     */
    public function isVisibleGiven(array $answers): bool
    {
        $conditions = is_array($this->conditions) ? array_values(array_filter(
            $this->conditions,
            fn ($c) => is_array($c) && ($c['field'] ?? '') !== ''
        )) : [];

        if ($conditions === []) {
            return true;
        }

        $match = $this->condition_match ?: self::MATCH_ALL;
        $results = array_map(fn ($c) => $this->conditionHolds($c, $answers), $conditions);

        return match ($match) {
            self::MATCH_ANY => in_array(true, $results, true),
            self::MATCH_NONE => ! in_array(true, $results, true),
            default => ! in_array(false, $results, true),
        };
    }

    /** @param array<string, mixed> $answers */
    protected function conditionHolds(array $condition, array $answers): bool
    {
        $actual = $answers[$condition['field']] ?? null;
        $expected = (string) ($condition['value'] ?? '');

        // A multi-choice answer "is" X when X is among the things ticked.
        if (is_array($actual)) {
            $haystack = array_map(fn ($v) => mb_strtolower((string) $v), $actual);
            $needle = mb_strtolower($expected);

            return match ($condition['operator'] ?? 'equals') {
                'not_equals', 'not_contains' => ! in_array($needle, $haystack, true),
                'filled' => $actual !== [],
                'empty' => $actual === [],
                default => in_array($needle, $haystack, true),
            };
        }

        $actualStr = mb_strtolower(trim((string) ($actual ?? '')));
        $expectedStr = mb_strtolower($expected);

        return match ($condition['operator'] ?? 'equals') {
            'not_equals' => $actualStr !== $expectedStr,
            'contains' => $expectedStr !== '' && str_contains($actualStr, $expectedStr),
            'not_contains' => $expectedStr === '' || ! str_contains($actualStr, $expectedStr),
            'gt' => is_numeric($actual) && is_numeric($expected) && (float) $actual > (float) $expected,
            'lt' => is_numeric($actual) && is_numeric($expected) && (float) $actual < (float) $expected,
            'filled' => $actualStr !== '',
            'empty' => $actualStr === '',
            default => $actualStr === $expectedStr,
        };
    }

    /**
     * A stored answer, rendered for a human.
     *
     * Every admin screen that shows custom answers used to echo the raw stored value, which was
     * always a string. The multi-choice types store a LIST and `{{ $value }}` on an array throws
     * in PHP 8 — so the read screens would have started 500ing the first time somebody answered
     * a checkbox group. File answers store a path, which is a link, not a sentence.
     */
    public function displayValue(mixed $raw): ?string
    {
        // A heading or divider collects nothing, so it has nothing to show even if a value was
        // somehow stored against it by an older definition of this field.
        if ($this->isLayoutOnly() || $raw === null || $raw === '' || $raw === []) {
            return null;
        }

        if ($this->type === 'checkbox') {
            return in_array($raw, ['1', 1, true], true) ? 'Yes' : 'No';
        }

        if (is_array($raw)) {
            return implode(', ', array_map('strval', $raw));
        }

        if ($this->isFile()) {
            return basename((string) $raw);
        }

        return (string) $raw;
    }

    /** Public URL for a file answer, or null when this field does not hold one. */
    public function fileUrl(mixed $raw): ?string
    {
        if (! $this->isFile() || ! is_string($raw) || $raw === '') {
            return null;
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->url($raw);
    }

    /** Storage/verification key for this custom field (namespaced to avoid clashes). */
    public function getKeyNameAttribute(): string
    {
        return 'cf_' . $this->id;
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }
}
