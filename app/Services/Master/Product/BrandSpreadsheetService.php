<?php

namespace App\Services\Master\Product;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Collection;
use RuntimeException;
use ZipArchive;

class BrandSpreadsheetService
{
    /** @param Collection<int, object> $categories */
    public function createTemplate(Collection $categories): string
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'brand-template-');
        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to create a temporary Excel template.');
        }

        $archive = new ZipArchive;
        if ($archive->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($temporaryPath);
            throw new RuntimeException('Unable to build the Excel template.');
        }

        $archive->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $archive->addFromString('_rels/.rels', $this->rootRelationshipsXml());
        $archive->addFromString('xl/workbook.xml', $this->workbookXml());
        $archive->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationshipsXml());
        $archive->addFromString('xl/worksheets/sheet1.xml', $this->worksheetXml([
            ['name', 'description', 'category_codes', 'is_active'],
        ]));

        $categoryRows = [['category_code', 'category_name', 'department']];
        foreach ($categories as $category) {
            $categoryRows[] = [
                $category->code,
                $category->name,
                $category->department?->name ?? '',
            ];
        }

        $archive->addFromString('xl/worksheets/sheet2.xml', $this->worksheetXml($categoryRows));
        $archive->close();

        return $temporaryPath;
    }

    /** @return list<array{row_number: int, cells: array<int, string>}> */
    public function readBrands(string $filePath): array
    {
        $archive = new ZipArchive;
        if ($archive->open($filePath) !== true) {
            throw new RuntimeException('File bukan Excel .xlsx yang valid.');
        }

        try {
            $expandedSize = 0;
            if ($archive->numFiles > 20) {
                throw new RuntimeException('Workbook Excel memiliki terlalu banyak bagian yang tidak diperlukan.');
            }
            for ($index = 0; $index < $archive->numFiles; $index++) {
                $fileStat = $archive->statIndex($index);
                $expandedSize += (int) ($fileStat['size'] ?? 0);
                if ($expandedSize > 25 * 1024 * 1024) {
                    throw new RuntimeException('Ukuran isi workbook Excel melebihi batas 25 MB.');
                }
            }

            $sheetXml = $archive->getFromName('xl/worksheets/sheet1.xml');
            if (! is_string($sheetXml)) {
                throw new RuntimeException('Sheet Brands tidak ditemukan di file Excel.');
            }

            $sharedStringsXml = $archive->getFromName('xl/sharedStrings.xml');
            $sharedStrings = is_string($sharedStringsXml)
                ? $this->readSharedStrings($sharedStringsXml)
                : [];

            return $this->readWorksheetRows($sheetXml, $sharedStrings);
        } finally {
            $archive->close();
        }
    }

    /** @return list<string> */
    private function readSharedStrings(string $xml): array
    {
        $document = $this->loadXml($xml);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $strings = [];

        foreach ($xpath->query('//x:si') ?: [] as $item) {
            $value = '';
            foreach ($xpath->query('.//x:t', $item) ?: [] as $text) {
                $value .= $text->textContent;
            }
            $strings[] = $value;
        }

        return $strings;
    }

    /** @param list<string> $sharedStrings
     * @return list<array{row_number: int, cells: array<int, string>}>
     */
    private function readWorksheetRows(string $xml, array $sharedStrings): array
    {
        $document = $this->loadXml($xml);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rows = [];

        foreach ($xpath->query('//x:sheetData/x:row') ?: [] as $row) {
            if (! $row instanceof DOMElement) {
                continue;
            }

            $cells = [];
            foreach ($xpath->query('./x:c', $row) ?: [] as $cell) {
                if (! $cell instanceof DOMElement) {
                    continue;
                }

                $column = preg_replace('/\d+/', '', $cell->getAttribute('r'));
                $columnIndex = $this->columnIndex($column);
                $type = $cell->getAttribute('t');
                $value = $type === 'inlineStr'
                    ? implode('', array_map(
                        static fn ($text): string => $text->textContent,
                        iterator_to_array($xpath->query('./x:is//x:t', $cell) ?: []),
                    ))
                    : ($xpath->query('./x:v', $cell)?->item(0)?->textContent ?? '');

                if ($type === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                }

                $cells[$columnIndex] = trim($value);
            }

            if (array_filter($cells, static fn (string $value): bool => $value !== '') !== []) {
                $rows[] = [
                    'row_number' => (int) $row->getAttribute('r'),
                    'cells' => $cells,
                ];
            }
        }

        return $rows;
    }

    private function loadXml(string $xml): DOMDocument
    {
        $document = new DOMDocument;
        if (! $document->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS)) {
            throw new RuntimeException('Struktur XML di file Excel tidak valid.');
        }

        return $document;
    }

    private function columnIndex(string $column): int
    {
        $index = 0;
        foreach (str_split(strtoupper($column)) as $letter) {
            $index = ($index * 26) + ord($letter) - 64;
        }

        return max(0, $index - 1);
    }

    /** @param list<list<string>> $rows */
    private function worksheetXml(array $rows): string
    {
        $rowXml = '';
        foreach ($rows as $rowIndex => $row) {
            $cellXml = '';
            foreach ($row as $columnIndex => $value) {
                $cellReference = $this->columnName($columnIndex + 1).($rowIndex + 1);
                $escapedValue = htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $cellXml .= '<c r="'.$cellReference.'" t="inlineStr"><is><t xml:space="preserve">'.$escapedValue.'</t></is></c>';
            }
            $rowXml .= '<row r="'.($rowIndex + 1).'">'.$cellXml.'</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>'.$rowXml.'</sheetData></worksheet>';
    }

    private function columnName(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(($index % 26) + 65).$name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'</Types>';
    }

    private function rootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Brands" sheetId="1" r:id="rId1"/><sheet name="Category Reference" sheetId="2" r:id="rId2"/></sheets>'
            .'</workbook>';
    }

    private function workbookRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
            .'</Relationships>';
    }
}
