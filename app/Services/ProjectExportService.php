<?php

namespace App\Services;

use App\Models\Project;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ProjectExportService
{
    private const STATUS_LABEL = ['belum_mulai' => 'Belum Mulai', 'berjalan' => 'Berjalan', 'selesai' => 'Selesai'];

    public function __construct(private readonly ProgressService $progress) {}

    /** Laporan progress pekerjaan lapangan (pengganti Excel monitoring). */
    public function progressReport(Project $project): Spreadsheet
    {
        $project->load(['branch:id,branch_name', 'currentRab.sections.items']);
        $summary = $this->progress->summary($project);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Progress');
        $sheet->fromArray([
            ['Monitoring Progress Pekerjaan Lapangan'],
            ['Proyek', "{$project->kode} - {$project->nama}"],
            ['Cabang', $project->branch?->branch_name],
            ['Total Pekerjaan', $summary['total']],
            ['Rata-rata Progress (%)', $summary['avg_progress']],
            ['Selesai', $summary['selesai']],
            ['Belum Selesai', $summary['belum_selesai']],
        ], null, 'A1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $row = 9;
        $sheet->fromArray(['No', 'Uraian', 'Deskripsi', 'Qty', 'Satuan', 'Progress (%)', 'Status'], null, "A{$row}");
        $sheet->getStyle("A{$row}:G{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:G{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFDE2E4');
        $row++;

        foreach ($project->currentRab?->sections ?? [] as $section) {
            $sectionProgress = $this->progress->average($section->items, $project->progress_mode);
            $sheet->setCellValue("A{$row}", $section->kode_bidang);
            $sheet->setCellValue("B{$row}", $section->nama);
            $sheet->setCellValue("F{$row}", $sectionProgress);
            $sheet->getStyle("A{$row}:G{$row}")->getFont()->setBold(true);
            $row++;

            foreach ($section->items as $i => $item) {
                $sheet->fromArray([
                    $i + 1, $item->uraian, $item->deskripsi, $item->qty, $item->satuan,
                    $item->progress, self::STATUS_LABEL[$item->status] ?? $item->status,
                ], null, "A{$row}");
                $row++;
            }
        }

        foreach (['A' => 8, 'B' => 45, 'C' => 40, 'D' => 10, 'E' => 10, 'F' => 14, 'G' => 14] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        return $spreadsheet;
    }
}
