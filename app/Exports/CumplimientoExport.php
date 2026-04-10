<?php
// File: app/Exports/CumplimientoExport.php
namespace App\Exports;

use Illuminate\Support\Collection;
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

class CumplimientoExport implements
    FromCollection,
    WithHeadings,
    WithStyles,
    WithColumnWidths,
    WithTitle,
    WithMapping
{
    protected Collection $data;

    public function __construct(Collection $data)
    {
        $this->data = $data;
    }

    public function collection(): Collection
    {
        return $this->data;
    }

    /**
     * Mapea cada fila según su fuente:
     * - 'evaluacion': usa cláusulas ISO (total_cl, conformes, no_conformes, observaciones, na)
     * - 'documentos': usa vigencia de documentos (total, vigente, por_vencer, caducado)
     */
    public function map($row): array
    {
        if (($row['fuente'] ?? '') === 'evaluacion') {
            return [
                $row['norma'],
                'Evaluación ISO',
                $row['total_cl'],
                $row['conformes'],
                $row['no_conformes'],
                $row['observaciones'],
                $row['na'],
                $row['compliance'] . '%',
            ];
        }

        // Fallback: documentos vinculados
        return [
            $row['norma'],
            'Documentos',
            $row['total'],
            $row['vigente'],
            $row['por_vencer'],
            $row['caducado'],
            '—',
            $row['compliance'] . '%',
        ];
    }

    public function headings(): array
    {
        return [
            'Norma ISO',
            'Fuente',
            'Total',
            'Conformes / Vigentes',
            'No Conf. / Por Vencer',
            'Obs. / Caducados',
            'N/A',
            '% Cumplimiento',
        ];
    }

    public function columnWidths(): array
    {
        return ['A' => 18, 'B' => 16, 'C' => 10, 'D' => 22, 'E' => 22, 'F' => 20, 'G' => 8, 'H' => 16];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FF8A00']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->freezePane('A2');
        $sheet->getRowDimension(1)->setRowHeight(24);

        $lastRow = $sheet->getHighestRow();
        for ($i = 2; $i <= $lastRow; $i++) {
            $sheet->getStyle("A{$i}:H{$i}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $i % 2 === 0 ? 'FFF8F0' : 'FFFFFF']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            // Alinear nombre de norma a la izquierda
            $sheet->getStyle("A{$i}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }

        $sheet->getStyle("A1:H{$lastRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E0E0E0']]],
        ]);

        return [];
    }

    public function title(): string
    {
        return 'Cumplimiento ISO';
    }
}
