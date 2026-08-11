<?php

declare(strict_types=1);

namespace App\Services\Export;

use RuntimeException;
use ZipArchive;

/**
 * A minimal, dependency-free .xlsx writer.
 *
 * An xlsx file is a zip of XML parts, so this builds those parts directly with
 * ZipArchive (a core PHP extension, present on this server) rather than pulling in
 * PhpSpreadsheet. The reason is deployment risk, not purity: this exists so an
 * organizer can rescue the state of a LIVE auction that has gone wrong, and adding a
 * composer dependency would mean running `composer install` on the production box in
 * the middle of that auction to get the rescue tool.
 *
 * Deliberately small. It writes strings and numbers into one or more sheets with a bold
 * header row, and nothing else — no formulas, no merges, no dates. Anything more and
 * PhpSpreadsheet is the right answer.
 *
 * Strings are written inline (`t="inlineStr"`) instead of into a shared-string table.
 * That costs a few bytes per repeated value and saves the whole second index, which for
 * a few hundred rows is the better trade.
 */
class XlsxWriter
{
    /** @var array<int, array{name: string, rows: array<int, array<int, mixed>>}> */
    private array $sheets = [];

    /**
     * Fill colours in use, ARGB or RGB => cellXfs index.
     *
     * @var array<string, int>
     */
    private array $fillStyles = [];

    /**
     * @param  array<int, array<int, mixed>>  $rows  The first row is treated as headers.
     * @param  array<int, string>  $merges  Optional A1-style ranges, e.g. ['E1:F1'].
     */
    public function addSheet(string $name, array $rows, array $merges = []): self
    {
        $this->sheets[] = [
            'name' => $this->safeSheetName($name),
            'rows' => $rows,
            'merges' => $merges,
        ];

        return $this;
    }

    /** Write the workbook to $path. */
    public function save(string $path): void
    {
        if ($this->sheets === []) {
            throw new RuntimeException('A workbook needs at least one sheet.');
        }

        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Could not create {$path}.");
        }

        /*
         * Sheets are rendered BEFORE styles.xml is written, because writing a cell is what
         * registers its fill colour — styles.xml has to know all of them, and it was being
         * written first, so every colour was silently dropped and the header cells referenced
         * a style that did not exist.
         */
        $bodies = [];

        foreach ($this->sheets as $i => $sheet) {
            $bodies[$i] = $this->sheetXml($sheet['rows'], $sheet['merges'] ?? []);
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRels());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/styles.xml', $this->styles());

        foreach ($bodies as $i => $body) {
            $zip->addFromString('xl/worksheets/sheet' . ($i + 1) . '.xml', $body);
        }

        $zip->close();
    }

    /**
     * Excel refuses these characters in a tab name and truncates past 31 chars, and it
     * reports a corrupt file rather than telling you which sheet was at fault.
     */
    private function safeSheetName(string $name): string
    {
        $name = str_replace(['\\', '/', '?', '*', '[', ']', ':'], '-', $name);

        return mb_substr(trim($name) ?: 'Sheet', 0, 31);
    }

    private function sheetXml(array $rows, array $merges = []): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach (array_values($rows) as $r => $row) {
            $rowNo = $r + 1;
            $xml .= '<row r="' . $rowNo . '">';

            foreach (array_values($row) as $c => $value) {
                $ref = $this->columnLetter($c) . $rowNo;

                /*
                 * A cell is a plain value, or ['v' => value, 'fill' => 'FFFF00'].
                 *
                 * The array form exists for the squad board, where a team's colour is what
                 * separates its column pair from its neighbour's — the same four colours the
                 * finance workbook this board replaces uses.
                 */
                $fill = null;

                if (is_array($value)) {
                    $fill = $value['fill'] ?? null;
                    $value = $value['v'] ?? null;
                }

                // Row 0 is the header, which carries the one bold style in the workbook.
                $style = $r === 0 ? ' s="1"' : '';

                if ($fill !== null && $fill !== '') {
                    $style = ' s="' . $this->fillStyleIndex((string) $fill) . '"';
                }

                if ($this->isNumeric($value)) {
                    $xml .= '<c r="' . $ref . '"' . $style . '><v>' . (0 + $value) . '</v></c>';

                    continue;
                }

                if ($value === null || $value === '') {
                    $xml .= '<c r="' . $ref . '"' . $style . '/>';

                    continue;
                }

                $xml .= '<c r="' . $ref . '"' . $style . ' t="inlineStr"><is><t xml:space="preserve">'
                    . $this->escape((string) $value) . '</t></is></c>';
            }

            $xml .= '</row>';
        }

        $xml .= '</sheetData>';

        /*
         * mergeCells MUST come after sheetData — the schema fixes the order of a
         * worksheet's children, and Excel rejects the file outright if it does not.
         */
        if ($merges !== []) {
            $xml .= '<mergeCells count="' . count($merges) . '">';
            foreach ($merges as $ref) {
                $xml .= '<mergeCell ref="' . $this->escape($ref) . '"/>';
            }
            $xml .= '</mergeCells>';
        }

        return $xml . '</worksheet>';
    }

    /**
     * Numbers go in as numbers so they can be summed in the spreadsheet.
     *
     * Numeric STRINGS are deliberately excluded: a lot number, a phone number or an id
     * with a leading zero is a label, and Excel silently eats the zero if it is handed a
     * number. Only real ints and floats are treated as numeric.
     */
    private function isNumeric(mixed $value): bool
    {
        return is_int($value) || is_float($value);
    }

    private function escape(string $text): string
    {
        // Control characters are illegal in XML 1.0 and make Excel declare the file
        // corrupt; player names pasted from elsewhere have carried them before.
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $text) ?? '';

        return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /** 0 -> A, 25 -> Z, 26 -> AA. */
    private function columnLetter(int $index): string
    {
        $letters = '';

        for ($i = $index; $i >= 0; $i = intdiv($i, 26) - 1) {
            $letters = chr(65 + ($i % 26)) . $letters;
        }

        return $letters;
    }

    private function contentTypes(): string
    {
        $overrides = '';

        foreach ($this->sheets as $i => $sheet) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet' . ($i + 1) . '.xml" '
                . 'ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . $overrides
            . '</Types>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbook(): string
    {
        $tabs = '';

        foreach ($this->sheets as $i => $sheet) {
            $tabs .= '<sheet name="' . $this->escape($sheet['name']) . '" sheetId="' . ($i + 1) . '" r:id="rId' . ($i + 1) . '"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $tabs . '</sheets></workbook>';
    }

    private function workbookRels(): string
    {
        $rels = '';

        foreach ($this->sheets as $i => $sheet) {
            $rels .= '<Relationship Id="rId' . ($i + 1) . '" '
                . 'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" '
                . 'Target="worksheets/sheet' . ($i + 1) . '.xml"/>';
        }

        // Styles takes the id after the last sheet, so it never collides with one.
        $stylesId = count($this->sheets) + 1;

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $rels
            . '<Relationship Id="rId' . $stylesId . '" '
            . 'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    /**
     * Cell formats: 0 default, 1 bold (the header row), then one per colour asked for.
     *
     * Colours are collected as cells are written rather than declared up front, so a caller
     * says `['v' => 'x', 'fill' => 'FFFF00']` and never has to know what a cellXf index is.
     */
    private function styles(): string
    {
        /*
         * fillId 0 and 1 are reserved by the format: Excel expects `none` then `gray125` in
         * those slots and misreads every later fill if they are missing — the symptom is
         * colours landing on the wrong cells rather than an error. So real fills start at 2.
         */
        $fills = '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>';
        $xfs = '<xf xfId="0"/><xf xfId="0" fontId="1" applyFont="1"/>';
        $fillId = 2;

        foreach ($this->fillStyles as $argb => $_index) {
            // 6-digit hex is what a designer quotes; Excel wants 8, with the alpha byte first.
            $rgb = strlen($argb) === 6 ? 'FF' . $argb : $argb;
            $fills .= '<fill><patternFill patternType="solid">'
                . '<fgColor rgb="' . $rgb . '"/><bgColor indexed="64"/>'
                . '</patternFill></fill>';

            // Bold as well as filled: every colour here is a header or a total.
            $xfs .= '<xf xfId="0" fontId="1" fillId="' . $fillId . '" applyFont="1" applyFill="1"/>';
            $fillId++;
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="' . $fillId . '">' . $fills . '</fills>'
            . '<borders count="1"><border/></borders>'
            . '<cellStyleXfs count="1"><xf/></cellStyleXfs>'
            . '<cellXfs count="' . (2 + count($this->fillStyles)) . '">' . $xfs . '</cellXfs>'
            // Named styles. Excel copes without them, but stricter readers warn that the
            // workbook has no default style, and this file has to open first time on
            // whatever laptop is to hand.
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    /**
     * The cellXfs index for a fill colour, registering it the first time it is seen.
     *
     * Deduplicated because every cell resolves its style through this one table: a fresh entry
     * per cell would work, and would grow the part by one row per cell in the workbook.
     */
    private function fillStyleIndex(string $argb): int
    {
        $argb = strtoupper(ltrim($argb, '#'));

        if (! isset($this->fillStyles[$argb])) {
            // 0 and 1 are the default and bold formats, so colours start at 2.
            $this->fillStyles[$argb] = 2 + count($this->fillStyles);
        }

        return $this->fillStyles[$argb];
    }
}
