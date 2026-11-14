---
name: qa-stlc-excel
description: Generate enterprise STLC (ISTQB / ISO-29119 / IEEE 829) QA Master Test Plan, RTM, and Multi-Sheet Excel (.xlsx) Test Case Workbooks using native PHP OpenXML (ZipArchive + XMLWriter).
---

# QA STLC Excel Workbook Generator Skill

Use this skill whenever creating, updating, or regenerating QA Test Plans, Requirements Traceability Matrices (RTM), or STLC Test Case Excel (`.xlsx`) workbooks for **SC Online — CV. Heaven Scent**.

## Core Architecture

1. **Zero External Dependencies:** Uses PHP 8.4 native `ZipArchive` and `XMLWriter` via `scripts/lib/XlsxWorkbookBuilder.php`.
2. **OOXML Schema Ordering (`CT_Worksheet`):**
   Every worksheet XML (`xl/worksheets/sheet{N}.xml`) strictly follows the OpenXML element sequence:
   - `sheetViews` (with frozen header panes)
   - `sheetFormatPr`
   - `cols` (explicit column widths)
   - `sheetData` (rows and typed cells: `inlineStr`, numeric `<v>`, or formula `<f>`)
   - `mergeCells` (title banners)
   - `autoFilter`
   - `dataValidations` (dropdown lists for QA Execution Status)
3. **19-Column Senior QA STLC Standard (Sheets 05–12):**
   - `A`: TC ID
   - `B`: Ref PRD / FR / BR / Lampiran B
   - `C`: Module & Sub-Module (Route)
   - `D`: Test Scenario (High-Level)
   - `E`: Test Case Title
   - `F`: Test Type (`Positive`, `Negative`, `Boundary/BVA`, `Security/RBAC`, `Formula/Calculation`, `DB Atomicity`, `E2E`)
   - `G`: Priority (`P0 - Critical`, `P1 - High`, `P2 - Medium`, `P3 - Low`)
   - `H`: Severity (`S1 - Blocker`, `S2 - Critical`, `S3 - Major`, `S4 - Minor`)
   - `I`: Actor / Role Persona
   - `J`: Pre-Conditions
   - `K`: Test Data / Input Parameters
   - `L`: Test Steps
   - `M`: Expected Result
   - `N`: Post-Conditions
   - `O`: Actual Result
   - `P`: Status (`Not Executed`, `Pass`, `Fail`, `Blocked`, `In Progress`, `Deferred`)
   - `Q`: Executed By
   - `R`: Execution Date
   - `S`: Defect ID / Evidence / Remarks

## Regenerating the Workbook

Run:
```powershell
php scripts/generate-qa-test-cases-xlsx.php
```
Outputs:
- `QA_Test_Cases_SC_Online_PRD_v2.4.xlsx`
- `docs/qa/QA_Test_Cases_SC_Online_PRD_v2.4.xlsx`
