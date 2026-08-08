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
     * @param  array<int, array<int, mixed>>  $rows  The first row is treated as headers.
     */
    public function addSheet(string $name, array $rows): self
    {
        $this->sheets[] = ['name' => $this->safeSheetName($name), 'rows' => $rows];

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

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRels());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/styles.xml', $this->styles());

        foreach ($this->sheets as $i => $sheet) {
            $zip->addFromString('xl/worksheets/sheet' . ($i + 1) . '.xml', $this->sheetXml($sheet['rows']));
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

    private function sheetXml(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach (array_values($rows) as $r => $row) {
            $rowNo = $r + 1;
            $xml .= '<row r="' . $rowNo . '">';

            foreach (array_values($row) as $c => $value) {
                $ref = $this->columnLetter($c) . $rowNo;
                // Row 0 is the header, which carries the one bold style in the workbook.
                $style = $r === 0 ? ' s="1"' : '';

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

        return $xml . '</sheetData></worksheet>';
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

    /** Two cell formats: 0 is the default, 1 is bold — used for the header row. */
    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            . '<borders count="1"><border/></borders>'
            . '<cellStyleXfs count="1"><xf/></cellStyleXfs>'
            . '<cellXfs count="2"><xf xfId="0"/><xf xfId="0" fontId="1" applyFont="1"/></cellXfs>'
            . '</styleSheet>';
    }
}
