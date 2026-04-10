<?php
// File: app/Exports/DocumentosExport.php
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

class DocumentosExport implements
    FromCollection,
    WithHeadings,
    WithStyles,
    WithColumnWidths,
    WithTitle,
    WithMapping
{
    protected string $today;
    protected string $in30;

    public function __construct()
    {
        $this->today = now()->toDateString();
        $this->in30  = now()->addDays(30)->toDateString();
    }

    public function collection()
    {
        $today = $this->today;
        $in30  = $this->in30;

        // documento columns: idDocumento, Nombre_Doc, Ruta, Fecha_creacion,
        //   Fecha_Caducidad, Fecha_Revision, Tipo_Documento_idTipo_Documento,
        //   Version_idVersion, Usuario_idUsuarioCreador
        return DB::table('documento')
            ->leftJoin('version', 'documento.Version_idVersion', '=', 'version.idVersion')
            ->leftJoin('tipo_documento', 'tipo_documento.idTipo_Documento', '=', 'documento.Tipo_Documento_idTipo_Documento')
            ->leftJoin('documento_has_norma', 'documento.idDocumento', '=', 'documento_has_norma.Documento_idDocumento')
            ->leftJoin('norma', 'norma.idNorma', '=', 'documento_has_norma.Norma_idNorma')
            ->select(
                'documento.idDocumento',
                'documento.Nombre_Doc',
                DB::raw("COALESCE(tipo_documento.Nombre_Tipo, 'General') as tipo"),
                'version.numero_Version',
                'documento.Fecha_creacion',
                'documento.Fecha_Caducidad',
                DB::raw("COALESCE(norma.Codigo_norma, 'General') as norma"),
                DB::raw("
                    CASE
                        WHEN documento.Fecha_Caducidad IS NULL THEN 'Vigente'
                        WHEN documento.Fecha_Caducidad > '{$in30}' THEN 'Vigente'
                        WHEN documento.Fecha_Caducidad BETWEEN '{$today}' AND '{$in30}' THEN 'Por Vencer'
                        ELSE 'Caducado'
                    END as estado
                ")
            )
            ->orderBy('documento.idDocumento', 'desc')
            ->get();
    }

    public function map($row): array
    {
        return [
            $row->idDocumento,
            $row->Nombre_Doc,
            $row->tipo,
            $row->numero_Version ? number_format($row->numero_Version, 1) : '1.0',
            $row->Fecha_creacion ? date('d/m/Y', strtotime($row->Fecha_creacion)) : 'N/A',
            $row->Fecha_Caducidad ? date('d/m/Y', strtotime($row->Fecha_Caducidad)) : 'Sin caducidad',
            $row->norma,
            $row->estado,
        ];
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nombre del Documento',
            'Tipo',
            'Versión',
            'Fecha de Creación',
            'Fecha de Caducidad',
            'Norma ISO',
            'Estado',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 8,
            'B' => 42,
            'C' => 18,
            'D' => 10,
            'E' => 18,
            'F' => 20,
            'G' => 14,
            'H' => 16,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FF8A00']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:H1');
        $sheet->getRowDimension(1)->setRowHeight(24);

        $lastRow = $sheet->getHighestRow();
        for ($i = 2; $i <= $lastRow; $i++) {
            $bgColor = ($i % 2 === 0) ? 'FFF8F0' : 'FFFFFF';
            $sheet->getStyle("A{$i}:H{$i}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
            ]);

            $estado = $sheet->getCell("H{$i}")->getValue();
            $color = match($estado) {
                'Por Vencer' => 'FFF3E0',
                'Caducado'   => 'FDECEA',
                default      => $bgColor,
            };
            $fontColor = match($estado) {
                'Por Vencer' => 'E65100',
                'Caducado'   => 'C62828',
                default      => '333333',
            };
            $sheet->getStyle("H{$i}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $color]],
                'font' => ['bold' => true, 'color' => ['rgb' => $fontColor]],
            ]);
        }

        $sheet->getStyle("A1:H{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color'       => ['rgb' => 'E0E0E0'],
                ],
            ],
        ]);

        return [];
    }

    public function title(): string
    {
        return 'Inventario de Documentos';
    }
}
