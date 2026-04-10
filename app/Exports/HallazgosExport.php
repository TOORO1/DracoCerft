<?php
// File: app/Exports/HallazgosExport.php
namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class HallazgosExport implements
    FromCollection,
    WithHeadings,
    WithStyles,
    WithColumnWidths,
    WithTitle,
    WithMapping
{
    public function collection()
    {
        // hallazgos table: id, titulo, descripcion, prioridad, created_by, created_at, updated_at
        return DB::table('hallazgos')
            ->select('id', 'titulo', 'descripcion', 'prioridad', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function map($row): array
    {
        return [
            $row->id,
            $row->titulo,
            $row->descripcion ?? '',
            $row->prioridad ?? 'media',
            $row->created_at ? date('d/m/Y H:i', strtotime($row->created_at)) : 'N/A',
        ];
    }

    public function headings(): array
    {
        return [
            'ID',
            'Título',
            'Descripción',
            'Prioridad',
            'Fecha Registro',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 8,
            'B' => 40,
            'C' => 55,
            'D' => 14,
            'E' => 20,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Header row
        $sheet->getStyle('A1:E1')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '5C6BC0']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:E1');
        $sheet->getRowDimension(1)->setRowHeight(24);

        $lastRow = $sheet->getHighestRow();
        for ($i = 2; $i <= $lastRow; $i++) {
            $bgColor = ($i % 2 === 0) ? 'F3F4FF' : 'FFFFFF';
            $sheet->getStyle("A{$i}:E{$i}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
            ]);

            // Color priority column (D)
            $prioridad = $sheet->getCell("D{$i}")->getValue();
            [$bg, $fg] = match(strtolower((string)$prioridad)) {
                'alta'  => ['FDECEA', 'C62828'],
                'media' => ['FFF8E1', 'E65100'],
                default => ['E8F5E9', '2E7D32'],
            };
            $sheet->getStyle("D{$i}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
                'font' => ['bold' => true, 'color' => ['rgb' => $fg]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
        }

        $sheet->getStyle("A1:E{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E0E0E0']],
            ],
        ]);

        // Wrap text in description column (C)
        $sheet->getStyle("C2:C{$lastRow}")->getAlignment()->setWrapText(true);

        return [];
    }

    public function title(): string
    {
        return 'Hallazgos de Auditoría';
    }
}
