<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Item;
use App\Repositories\StockRepository;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class StockExportService
{
    private const NUMBER_FORMAT = '#,##0.##';

    public function __construct(private readonly StockRepository $stocks) {}

    /**
     * Rekap stok saat ini: barang × cabang. Per cabang ada kolom angka (satuan terkecil, mudah dijumlah)
     * dan kolom teks campuran ("1 karton 5 box"). Sel stok yang <= stok minimum diberi warna merah muda.
     *
     * @param  Collection<int, Branch>  $branches
     */
    public function currentStock(Collection $branches, ?string $search = null): Spreadsheet
    {
        $items = $this->stocks->stockMatrix($branches, $search);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Stok Saat Ini');

        $fixed = ['No', 'Barang', 'Satuan Terkecil', 'Stok Minimum'];
        $multi = $branches->count() > 1;
        $lastCol = count($fixed) + $branches->count() * 2 + ($multi ? 1 : 0);

        $sheet->setCellValue('A1', 'Rekap Stok Saat Ini');
        $sheet->setCellValue('A2', 'Per '.now()->format('d/m/Y H:i'));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setItalic(true);

        // Header dua tingkat: kolom tetap digabung vertikal, tiap cabang digabung 2 kolom
        foreach ($fixed as $i => $label) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue("{$col}4", $label);
            $sheet->mergeCells("{$col}4:{$col}5");
        }

        $col = count($fixed) + 1;
        foreach ($branches as $branch) {
            $a = Coordinate::stringFromColumnIndex($col);
            $b = Coordinate::stringFromColumnIndex($col + 1);
            $sheet->setCellValue("{$a}4", $branch->branch_name);
            $sheet->mergeCells("{$a}4:{$b}4");
            $sheet->setCellValue("{$a}5", 'Qty (satuan terkecil)');
            $sheet->setCellValue("{$b}5", 'Campuran');
            $col += 2;
        }

        if ($multi) {
            $total = Coordinate::stringFromColumnIndex($lastCol);
            $sheet->setCellValue("{$total}4", 'Total Semua Cabang (satuan terkecil)');
            $sheet->mergeCells("{$total}4:{$total}5");
        }

        $headerRange = 'A4:'.Coordinate::stringFromColumnIndex($lastCol).'5';
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFE5E7EB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);

        $row = 6;
        foreach ($items as $index => $item) {
            /** @var Item $item */
            $options = $item->unit_options;
            $sheet->setCellValue("A{$row}", $index + 1);
            $sheet->setCellValue("B{$row}", $item->name);
            $sheet->setCellValue("C{$row}", $item->unit);
            $sheet->setCellValue("D{$row}", (float) $item->min_stock);

            $col = count($fixed) + 1;
            $sum = 0.0;
            foreach ($branches as $branch) {
                $qty = (float) ($item->stocks[$branch->id] ?? 0);
                $sum += $qty;
                $a = Coordinate::stringFromColumnIndex($col);
                $b = Coordinate::stringFromColumnIndex($col + 1);
                $sheet->setCellValue("{$a}{$row}", $qty);
                $sheet->setCellValue("{$b}{$row}", $this->mixed($qty, $options));

                if ($qty <= (float) $item->min_stock) {
                    $sheet->getStyle("{$a}{$row}:{$b}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFECACA');
                }
                $col += 2;
            }

            if ($multi) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($lastCol).$row, $sum);
            }
            $row++;
        }

        $lastRow = max($row - 1, 5);
        $sheet->getStyle("D6:{$this->col($lastCol)}{$lastRow}")->getNumberFormat()->setFormatCode(self::NUMBER_FORMAT);
        $sheet->getStyle("A4:{$this->col($lastCol)}{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        for ($c = 1; $c <= $lastCol; $c++) {
            $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
        }
        $sheet->freezePane('C6');

        return $spreadsheet;
    }

    private function col(int $index): string
    {
        return Coordinate::stringFromColumnIndex($index);
    }

    /**
     * Pecah qty (satuan terkecil) ke satuan terbesar → terkecil, mis. "1 karton 5 box". Opsi diurut kecil → besar.
     *
     * @param  array<int, array{unit_id: int, name: string, factor: float}>  $options
     */
    private function mixed(float $base, array $options): string
    {
        if ($options === []) {
            return (string) $base;
        }

        $sign = $base < 0 ? '-' : '';
        $rest = (int) round(abs($base) * 100);
        $parts = [];

        foreach (array_reverse($options) as $unit) {
            $factor = (int) round($unit['factor'] * 100);
            $isBase = $factor === 100;
            $count = $isBase ? $rest / 100 : intdiv($rest, $factor);
            $rest = $isBase ? 0 : $rest - $count * $factor;

            if ($count > 0) {
                $parts[] = rtrim(rtrim(number_format($count, 2, ',', '.'), '0'), ',').' '.$unit['name'];
            }
        }

        return $parts === [] ? '0 '.$options[0]['name'] : $sign.implode(' ', $parts);
    }
}
