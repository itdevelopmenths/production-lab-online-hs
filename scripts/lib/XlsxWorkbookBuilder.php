<?php

declare(strict_types=1);

namespace App\QaExcel;

use XMLWriter;
use ZipArchive;
use RuntimeException;

/**
 * Native PHP 8.4 Office Open XML (.xlsx) Workbook Builder for Senior QA STLC Test Suites.
 * Zero external library dependencies — uses built-in ZipArchive and XMLWriter.
 */
class XlsxWorkbookBuilder
{
    public const STYLE_DEFAULT = 0;
    public const STYLE_BANNER_TITLE = 1;
    public const STYLE_BANNER_SUBTITLE = 2;
    public const STYLE_TABLE_HEADER = 3;
    public const STYLE_SECTION_HEADER = 4;
    public const STYLE_DATA_WRAP = 5;
    public const STYLE_DATA_ZEBRA = 6;
    public const STYLE_CODE_ID = 7;
    public const STYLE_PRIORITY_P0 = 8;
    public const STYLE_PRIORITY_P1 = 9;
    public const STYLE_PRIORITY_P2 = 10;
    public const STYLE_STATUS_PASS = 11;
    public const STYLE_STATUS_NEUTRAL = 12;
    public const STYLE_DATA_BOLD = 13;
    public const STYLE_METRIC_NUMBER = 14;
    public const STYLE_TESTER_INPUT = 15;
    public const STYLE_CALC_INPUT = 16;
    public const STYLE_PERCENT = 17;

    /** @var array<int, array<string, mixed>> */
    private array $sheets = [];

    /**
     * Add a worksheet to the workbook.
     *
     * @param string $name Sheet tab name (max 31 chars)
     * @param array<int, array{header: string, width: int|float}> $columns
     * @param array<int, array<int, mixed>> $rows
     * @param array{
     *   title?: string,
     *   subtitle?: string,
     *   freezeRow?: int,
     *   autoFilter?: string,
     *   statusDropdownRange?: string,
     *   isTestCaseSheet?: bool
     * } $options
     */
    public function addSheet(string $name, array $columns, array $rows, array $options = []): void
    {
        $cleanName = preg_replace('/[:\\\\\\/?*\\[\\]]/', '_', $name) ?? $name;
        $cleanName = mb_substr($cleanName, 0, 31);

        $this->sheets[] = [
            'name' => $cleanName,
            'columns' => array_values($columns),
            'rows' => $rows,
            'options' => $options,
        ];
    }

    public static function colLetter(int $colIndex1Based): string
    {
        $letter = '';
        while ($colIndex1Based > 0) {
            $mod = ($colIndex1Based - 1) % 26;
            $letter = chr(65 + $mod) . $letter;
            $colIndex1Based = intdiv($colIndex1Based - $mod, 26);
        }
        return $letter;
    }

    public function save(string $outputPath): void
    {
        $dir = dirname($outputPath);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException("Failed to create directory: {$dir}");
        }

        if (file_exists($outputPath)) {
            @unlink($outputPath);
        }

        $zip = new ZipArchive();
        if ($zip->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Cannot open {$outputPath} for writing.");
        }

        $zip->addFromString('[Content_Types].xml', $this->buildContentTypesXml());
        $zip->addFromString('_rels/.rels', $this->buildRootRelsXml());
        $zip->addFromString('xl/workbook.xml', $this->buildWorkbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->buildWorkbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->buildStylesXml());

        foreach ($this->sheets as $idx => $sheet) {
            $sheetNum = $idx + 1;
            $zip->addFromString(
                "xl/worksheets/sheet{$sheetNum}.xml",
                $this->buildWorksheetXml($sheet)
            );
        }

        if (!$zip->close()) {
            throw new RuntimeException("Failed closing ZIP archive: {$outputPath}");
        }
    }

    private function buildContentTypesXml(): string
    {
        $xw = new XMLWriter();
        $xw->openMemory();
        $xw->startDocument('1.0', 'UTF-8', 'yes');
        $xw->startElement('Types');
        $xw->writeAttribute('xmlns', 'http://schemas.openxmlformats.org/package/2006/content-types');

        $xw->startElement('Default');
        $xw->writeAttribute('Extension', 'rels');
        $xw->writeAttribute('ContentType', 'application/vnd.openxmlformats-package.relationships+xml');
        $xw->endElement();

        $xw->startElement('Default');
        $xw->writeAttribute('Extension', 'xml');
        $xw->writeAttribute('ContentType', 'application/xml');
        $xw->endElement();

        $xw->startElement('Override');
        $xw->writeAttribute('PartName', '/xl/workbook.xml');
        $xw->writeAttribute('ContentType', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml');
        $xw->endElement();

        $xw->startElement('Override');
        $xw->writeAttribute('PartName', '/xl/styles.xml');
        $xw->writeAttribute('ContentType', 'application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml');
        $xw->endElement();

        foreach ($this->sheets as $idx => $_) {
            $sheetNum = $idx + 1;
            $xw->startElement('Override');
            $xw->writeAttribute('PartName', "/xl/worksheets/sheet{$sheetNum}.xml");
            $xw->writeAttribute('ContentType', 'application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml');
            $xw->endElement();
        }

        $xw->endElement();
        $xw->endDocument();
        return $xw->outputMemory();
    }

    private function buildRootRelsXml(): string
    {
        $xw = new XMLWriter();
        $xw->openMemory();
        $xw->startDocument('1.0', 'UTF-8', 'yes');
        $xw->startElement('Relationships');
        $xw->writeAttribute('xmlns', 'http://schemas.openxmlformats.org/package/2006/relationships');

        $xw->startElement('Relationship');
        $xw->writeAttribute('Id', 'rId1');
        $xw->writeAttribute('Type', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument');
        $xw->writeAttribute('Target', 'xl/workbook.xml');
        $xw->endElement();

        $xw->endElement();
        $xw->endDocument();
        return $xw->outputMemory();
    }

    private function buildWorkbookXml(): string
    {
        $xw = new XMLWriter();
        $xw->openMemory();
        $xw->startDocument('1.0', 'UTF-8', 'yes');
        $xw->startElement('workbook');
        $xw->writeAttribute('xmlns', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $xw->writeAttribute('xmlns:r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

        $xw->startElement('sheets');
        foreach ($this->sheets as $idx => $sheet) {
            $sheetNum = $idx + 1;
            $xw->startElement('sheet');
            $xw->writeAttribute('name', $sheet['name']);
            $xw->writeAttribute('sheetId', (string) $sheetNum);
            $xw->writeAttribute('r:id', "rId{$sheetNum}");
            $xw->endElement();
        }
        $xw->endElement(); // sheets

        $xw->endElement(); // workbook
        $xw->endDocument();
        return $xw->outputMemory();
    }

    private function buildWorkbookRelsXml(): string
    {
        $xw = new XMLWriter();
        $xw->openMemory();
        $xw->startDocument('1.0', 'UTF-8', 'yes');
        $xw->startElement('Relationships');
        $xw->writeAttribute('xmlns', 'http://schemas.openxmlformats.org/package/2006/relationships');

        foreach ($this->sheets as $idx => $_) {
            $sheetNum = $idx + 1;
            $xw->startElement('Relationship');
            $xw->writeAttribute('Id', "rId{$sheetNum}");
            $xw->writeAttribute('Type', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet');
            $xw->writeAttribute('Target', "worksheets/sheet{$sheetNum}.xml");
            $xw->endElement();
        }

        $xw->startElement('Relationship');
        $xw->writeAttribute('Id', 'rIdStyles');
        $xw->writeAttribute('Type', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles');
        $xw->writeAttribute('Target', 'styles.xml');
        $xw->endElement();

        $xw->endElement();
        $xw->endDocument();
        return $xw->outputMemory();
    }

    private function buildStylesXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="10">
    <font><sz val="10"/><color rgb="FF18181B"/><name val="Segoe UI"/></font>
    <font><b/><sz val="13"/><color rgb="FFFFFFFF"/><name val="Segoe UI"/></font>
    <font><sz val="10"/><color rgb="FFE2E8F0"/><name val="Segoe UI"/></font>
    <font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Segoe UI"/></font>
    <font><b/><sz val="10"/><color rgb="FF0F172A"/><name val="Segoe UI"/></font>
    <font><b/><sz val="9.5"/><color rgb="FF1E3A8A"/><name val="Consolas"/></font>
    <font><b/><sz val="9.5"/><color rgb="FF991B1B"/><name val="Segoe UI"/></font>
    <font><b/><sz val="9.5"/><color rgb="FF92400E"/><name val="Segoe UI"/></font>
    <font><b/><sz val="9.5"/><color rgb="FF166534"/><name val="Segoe UI"/></font>
    <font><b/><sz val="10"/><color rgb="FF1D4ED8"/><name val="Segoe UI"/></font>
  </fonts>
  <fills count="13">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF0F172A"/><bgColor indexed="64"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF1E293B"/><bgColor indexed="64"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF334155"/><bgColor indexed="64"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFE2E8F0"/><bgColor indexed="64"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFF8FAFC"/><bgColor indexed="64"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFFEE2E2"/><bgColor indexed="64"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFFEF3C7"/><bgColor indexed="64"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFDBEAFE"/><bgColor indexed="64"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFDCFCE7"/><bgColor indexed="64"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFFEF9C3"/><bgColor indexed="64"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFFEF08A"/><bgColor indexed="64"/></patternFill></fill>
  </fills>
  <borders count="3">
    <border><left/><right/><top/><bottom/><diagonal/></border>
    <border>
      <left style="thin"><color rgb="FFCBD5E1"/></left>
      <right style="thin"><color rgb="FFCBD5E1"/></right>
      <top style="thin"><color rgb="FFCBD5E1"/></top>
      <bottom style="thin"><color rgb="FFCBD5E1"/></bottom>
      <diagonal/>
    </border>
    <border>
      <left style="thin"><color rgb="FF475569"/></left>
      <right style="thin"><color rgb="FF475569"/></right>
      <top style="medium"><color rgb="FF0F172A"/></top>
      <bottom style="medium"><color rgb="FF0F172A"/></bottom>
      <diagonal/>
    </border>
  </borders>
  <cellStyleXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  </cellStyleXfs>
  <cellXfs count="18">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="left" vertical="center" wrapText="1"/>
    </xf>
    <xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="left" vertical="center" wrapText="1"/>
    </xf>
    <xf numFmtId="0" fontId="3" fillId="4" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="center" vertical="center" wrapText="1"/>
    </xf>
    <xf numFmtId="0" fontId="4" fillId="5" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="left" vertical="center" wrapText="1"/>
    </xf>
    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="left" vertical="top" wrapText="1"/>
    </xf>
    <xf numFmtId="0" fontId="0" fillId="6" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="left" vertical="top" wrapText="1"/>
    </xf>
    <xf numFmtId="0" fontId="5" fillId="0" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="center" vertical="top" wrapText="1"/>
    </xf>
    <xf numFmtId="0" fontId="6" fillId="7" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="center" vertical="top" wrapText="1"/>
    </xf>
    <xf numFmtId="0" fontId="7" fillId="8" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="center" vertical="top" wrapText="1"/>
    </xf>
    <xf numFmtId="0" fontId="5" fillId="9" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="center" vertical="top" wrapText="1"/>
    </xf>
    <xf numFmtId="0" fontId="8" fillId="10" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="center" vertical="top" wrapText="1"/>
    </xf>
    <xf numFmtId="0" fontId="4" fillId="6" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="center" vertical="top" wrapText="1"/>
    </xf>
    <xf numFmtId="0" fontId="4" fillId="0" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="left" vertical="top" wrapText="1"/>
    </xf>
    <xf numFmtId="0" fontId="4" fillId="6" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="center" vertical="center" wrapText="1"/>
    </xf>
    <xf numFmtId="0" fontId="0" fillId="11" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="left" vertical="top" wrapText="1"/>
    </xf>
    <xf numFmtId="0" fontId="9" fillId="12" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="right" vertical="center" wrapText="1"/>
    </xf>
    <xf numFmtId="10" fontId="4" fillId="6" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="center" vertical="center" wrapText="1"/>
    </xf>
  </cellXfs>
  <cellStyles count="1">
    <cellStyle name="Normal" xfId="0" builtinId="0"/>
  </cellStyles>
  <dxfs count="4">
    <dxf>
      <font><b/><color rgb="FF166534"/></font>
      <fill><patternFill patternType="solid"><fgColor rgb="FFDCFCE7"/><bgColor rgb="FFDCFCE7"/></patternFill></fill>
    </dxf>
    <dxf>
      <font><b/><color rgb="FF991B1B"/></font>
      <fill><patternFill patternType="solid"><fgColor rgb="FFFEE2E2"/><bgColor rgb="FFFEE2E2"/></patternFill></fill>
    </dxf>
    <dxf>
      <font><b/><color rgb="FF92400E"/></font>
      <fill><patternFill patternType="solid"><fgColor rgb="FFFEF3C7"/><bgColor rgb="FFFEF3C7"/></patternFill></fill>
    </dxf>
    <dxf>
      <font><b/><color rgb="FF1E3A8A"/></font>
      <fill><patternFill patternType="solid"><fgColor rgb="FFDBEAFE"/><bgColor rgb="FFDBEAFE"/></patternFill></fill>
    </dxf>
  </dxfs>
</styleSheet>
XML;
    }

    /**
     * @param array<string, mixed> $sheet
     */
    private function buildWorksheetXml(array $sheet): string
    {
        $columns = $sheet['columns'];
        $rows = $sheet['rows'];
        $options = $sheet['options'];

        $colCount = max(1, count($columns));
        $lastColLetter = self::colLetter($colCount);
        $freezeRow = $options['freezeRow'] ?? 3;
        $isTestCaseSheet = (bool) ($options['isTestCaseSheet'] ?? false);

        $xw = new XMLWriter();
        $xw->openMemory();
        $xw->startDocument('1.0', 'UTF-8', 'yes');
        $xw->startElement('worksheet');
        $xw->writeAttribute('xmlns', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $xw->writeAttribute('xmlns:r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

        // 1. sheetViews
        $xw->startElement('sheetViews');
        $xw->startElement('sheetView');
        $xw->writeAttribute('workbookViewId', '0');
        if ($freezeRow > 0) {
            $xw->startElement('pane');
            $xw->writeAttribute('ySplit', (string) $freezeRow);
            $xw->writeAttribute('topLeftCell', 'A' . ($freezeRow + 1));
            $xw->writeAttribute('activePane', 'bottomLeft');
            $xw->writeAttribute('state', 'frozen');
            $xw->endElement();
        }
        $xw->endElement(); // sheetView
        $xw->endElement(); // sheetViews

        // 2. sheetFormatPr
        $xw->startElement('sheetFormatPr');
        $xw->writeAttribute('defaultRowHeight', '22');
        $xw->endElement();

        // 3. cols
        $xw->startElement('cols');
        foreach ($columns as $idx => $col) {
            $colNum = $idx + 1;
            $xw->startElement('col');
            $xw->writeAttribute('min', (string) $colNum);
            $xw->writeAttribute('max', (string) $colNum);
            $xw->writeAttribute('width', (string) ($col['width'] ?? 18));
            $xw->writeAttribute('customWidth', '1');
            $xw->endElement();
        }
        $xw->endElement(); // cols

        // 4. sheetData
        $xw->startElement('sheetData');
        $currentRowNum = 1;
        $mergeRefs = [];

        // Optional Banner Row 1 (Title) & Row 2 (Subtitle)
        if (!empty($options['title'])) {
            $xw->startElement('row');
            $xw->writeAttribute('r', (string) $currentRowNum);
            $xw->writeAttribute('ht', '30');
            $xw->writeAttribute('customHeight', '1');
            for ($c = 1; $c <= $colCount; $c++) {
                $cellRef = self::colLetter($c) . $currentRowNum;
                $this->writeInlineStringCell(
                    $xw,
                    $cellRef,
                    $c === 1 ? (string) $options['title'] : '',
                    self::STYLE_BANNER_TITLE
                );
            }
            $xw->endElement();
            if ($colCount > 1) {
                $mergeRefs[] = "A{$currentRowNum}:{$lastColLetter}{$currentRowNum}";
            }
            $currentRowNum++;
        }

        if (!empty($options['subtitle'])) {
            $xw->startElement('row');
            $xw->writeAttribute('r', (string) $currentRowNum);
            $xw->writeAttribute('ht', '22');
            $xw->writeAttribute('customHeight', '1');
            for ($c = 1; $c <= $colCount; $c++) {
                $cellRef = self::colLetter($c) . $currentRowNum;
                $this->writeInlineStringCell(
                    $xw,
                    $cellRef,
                    $c === 1 ? (string) $options['subtitle'] : '',
                    self::STYLE_BANNER_SUBTITLE
                );
            }
            $xw->endElement();
            if ($colCount > 1) {
                $mergeRefs[] = "A{$currentRowNum}:{$lastColLetter}{$currentRowNum}";
            }
            $currentRowNum++;
        }

        // Table Header Row
        $headerRowNum = $currentRowNum;
        $xw->startElement('row');
        $xw->writeAttribute('r', (string) $headerRowNum);
        $xw->writeAttribute('ht', '28');
        $xw->writeAttribute('customHeight', '1');
        foreach ($columns as $idx => $col) {
            $cellRef = self::colLetter($idx + 1) . $headerRowNum;
            $this->writeInlineStringCell($xw, $cellRef, (string) $col['header'], self::STYLE_TABLE_HEADER);
        }
        $xw->endElement();
        $currentRowNum++;

        // Data Rows
        foreach ($rows as $rowIdx => $rowCells) {
            $xw->startElement('row');
            $xw->writeAttribute('r', (string) $currentRowNum);

            $isSectionRow = isset($rowCells['_section']) && $rowCells['_section'] === true;
            if ($isSectionRow) {
                $xw->writeAttribute('ht', '24');
                $xw->writeAttribute('customHeight', '1');
                $label = (string) ($rowCells['label'] ?? '');
                for ($c = 1; $c <= $colCount; $c++) {
                    $cellRef = self::colLetter($c) . $currentRowNum;
                    $this->writeInlineStringCell($xw, $cellRef, $c === 1 ? $label : '', self::STYLE_SECTION_HEADER);
                }
                if ($colCount > 1) {
                    $mergeRefs[] = "A{$currentRowNum}:{$lastColLetter}{$currentRowNum}";
                }
                $xw->endElement();
                $currentRowNum++;
                continue;
            }

            if ($isTestCaseSheet) {
                $xw->writeAttribute('ht', '62');
                $xw->writeAttribute('customHeight', '1');
            }

            $baseStyle = ($rowIdx % 2 === 1) ? self::STYLE_DATA_ZEBRA : self::STYLE_DATA_WRAP;
            $sheetType = (string) ($options['sheetType'] ?? ($isTestCaseSheet ? 'stlc_19' : 'default'));

            for ($c = 0; $c < $colCount; $c++) {
                $cellRef = self::colLetter($c + 1) . $currentRowNum;
                $val = $rowCells[$c] ?? '';

                $style = $baseStyle;
                if ($sheetType === 'role_tc') {
                    if ($c === 1) {
                        $style = self::STYLE_CODE_ID;
                    } elseif ($c === 7) {
                        $strVal = is_string($val) ? $val : '';
                        if ($strVal === 'Critical') {
                            $style = self::STYLE_PRIORITY_P0;
                        } elseif ($strVal === 'High') {
                            $style = self::STYLE_PRIORITY_P1;
                        } else {
                            $style = self::STYLE_PRIORITY_P2;
                        }
                    } elseif ($c >= 13 && $c <= 17) {
                        $style = self::STYLE_TESTER_INPUT;
                    }
                } elseif ($sheetType === 'e2e_tc') {
                    if ($c === 1) {
                        $style = self::STYLE_CODE_ID;
                    } elseif ($c >= 9 && $c <= 13) {
                        $style = self::STYLE_TESTER_INPUT;
                    }
                } elseif ($sheetType === 'rbac_matrix') {
                    if ($c === 2) {
                        $style = self::STYLE_CODE_ID;
                    } elseif ($c >= 8 && $c <= 9) {
                        $style = self::STYLE_TESTER_INPUT;
                    }
                } elseif ($isTestCaseSheet) {
                    if ($c === 0) {
                        $style = self::STYLE_CODE_ID;
                    } elseif ($c === 6 || $c === 7) {
                        $strVal = is_string($val) ? $val : '';
                        if (str_starts_with($strVal, 'P0') || str_starts_with($strVal, 'S1') || $strVal === 'Critical') {
                            $style = self::STYLE_PRIORITY_P0;
                        } elseif (str_starts_with($strVal, 'P1') || str_starts_with($strVal, 'S2') || $strVal === 'High') {
                            $style = self::STYLE_PRIORITY_P1;
                        } else {
                            $style = self::STYLE_PRIORITY_P2;
                        }
                    } elseif ($c === 15) {
                        $style = self::STYLE_STATUS_NEUTRAL;
                    }
                }

                if (is_array($val) && isset($val['formula'])) {
                    $fStyle = $val['style'] ?? self::STYLE_METRIC_NUMBER;
                    $this->writeFormulaCell($xw, $cellRef, (string) $val['formula'], (int) $fStyle);
                } elseif (is_array($val) && isset($val['value'])) {
                    $vStyle = $val['style'] ?? $style;
                    $this->writeValueCell($xw, $cellRef, $val['value'], (int) $vStyle);
                } else {
                    $this->writeValueCell($xw, $cellRef, $val, $style);
                }
            }

            $xw->endElement(); // row
            $currentRowNum++;
        }

        $lastDataRowNum = max($headerRowNum, $currentRowNum - 1);
        $xw->endElement(); // sheetData

        // 5. autoFilter (must precede mergeCells per ECMA-376 CT_Worksheet sequence)
        if (!empty($options['autoFilter'])) {
            $xw->startElement('autoFilter');
            $xw->writeAttribute('ref', (string) $options['autoFilter']);
            $xw->endElement();
        } elseif ($isTestCaseSheet && $lastDataRowNum > $headerRowNum) {
            $xw->startElement('autoFilter');
            $xw->writeAttribute('ref', "A{$headerRowNum}:{$lastColLetter}{$lastDataRowNum}");
            $xw->endElement();
        }

        // 6. mergeCells
        if (!empty($mergeRefs)) {
            $xw->startElement('mergeCells');
            $xw->writeAttribute('count', (string) count($mergeRefs));
            foreach ($mergeRefs as $ref) {
                $xw->startElement('mergeCell');
                $xw->writeAttribute('ref', $ref);
                $xw->endElement();
            }
            $xw->endElement();
        }

        // 7. conditionalFormatting (dynamic coloring for Execution Status column)
        $statusColLetter = match ($options['sheetType'] ?? '') {
            'role_tc' => 'O',
            'e2e_tc' => 'K',
            'rbac_matrix' => 'I',
            default => 'P',
        };
        $dropdownRange = $options['statusDropdownRange'] ?? ($isTestCaseSheet && $lastDataRowNum > $headerRowNum ? "{$statusColLetter}" . ($headerRowNum + 1) . ":{$statusColLetter}{$lastDataRowNum}" : null);
        if (!empty($dropdownRange)) {
            $xw->startElement('conditionalFormatting');
            $xw->writeAttribute('sqref', (string) $dropdownRange);

            $rules = [
                ['val' => '"Pass"', 'dxfId' => '0', 'priority' => '1'],
                ['val' => '"Fail"', 'dxfId' => '1', 'priority' => '2'],
                ['val' => '"Blocked"', 'dxfId' => '2', 'priority' => '3'],
                ['val' => '"N/A"', 'dxfId' => '3', 'priority' => '4'],
            ];
            foreach ($rules as $rule) {
                $xw->startElement('cfRule');
                $xw->writeAttribute('type', 'cellIs');
                $xw->writeAttribute('dxfId', $rule['dxfId']);
                $xw->writeAttribute('priority', $rule['priority']);
                $xw->writeAttribute('operator', 'equal');
                $xw->writeElement('formula', $rule['val']);
                $xw->endElement();
            }
            $xw->endElement(); // conditionalFormatting

            // 8. dataValidations
            $xw->startElement('dataValidations');
            $xw->writeAttribute('count', '1');
            $xw->startElement('dataValidation');
            $xw->writeAttribute('type', 'list');
            $xw->writeAttribute('allowBlank', '1');
            $xw->writeAttribute('showInputMessage', '1');
            $xw->writeAttribute('showErrorMessage', '1');
            $xw->writeAttribute('sqref', (string) $dropdownRange);
            $xw->writeElement('formula1', '"Not Run,Pass,Fail,Blocked,N/A"');
            $xw->endElement();
            $xw->endElement();
        }

        $xw->endElement(); // worksheet
        $xw->endDocument();
        return $xw->outputMemory();
    }

    private function writeValueCell(XMLWriter $xw, string $cellRef, mixed $value, int $style): void
    {
        if (is_int($value) || is_float($value)) {
            $xw->startElement('c');
            $xw->writeAttribute('r', $cellRef);
            $xw->writeAttribute('s', (string) $style);
            $xw->writeElement('v', (string) $value);
            $xw->endElement();
            return;
        }

        $this->writeInlineStringCell($xw, $cellRef, (string) $value, $style);
    }

    private function writeInlineStringCell(XMLWriter $xw, string $cellRef, string $text, int $style): void
    {
        $xw->startElement('c');
        $xw->writeAttribute('r', $cellRef);
        $xw->writeAttribute('s', (string) $style);
        $xw->writeAttribute('t', 'inlineStr');
        $xw->startElement('is');
        $xw->startElement('t');
        $xw->writeAttribute('xml:space', 'preserve');
        $xw->text($text);
        $xw->endElement(); // t
        $xw->endElement(); // is
        $xw->endElement(); // c
    }

    private function writeFormulaCell(XMLWriter $xw, string $cellRef, string $formula, int $style): void
    {
        $xw->startElement('c');
        $xw->writeAttribute('r', $cellRef);
        $xw->writeAttribute('s', (string) $style);
        $xw->writeElement('f', $formula);
        $xw->endElement();
    }
}
