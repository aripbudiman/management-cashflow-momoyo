<?php

namespace App\Services;

use App\Models\Project;
use App\Models\WorkItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class RabImportService
{
    private const MAX_ROWS = 1000;

    /** @var array<int, string> */
    public const HEADERS = ['kode_bidang', 'nama_bidang', 'uraian', 'deskripsi', 'qty', 'satuan', 'harga_satuan', 'hari'];

    public function __construct(
        private readonly RabService $rab,
        private readonly ProgressService $progress,
    ) {}

    /**
     * Impor item RAB dari Excel ke RAB aktif (draft). All-or-nothing: kalau ada baris error, tidak ada yang disimpan.
     *
     * @return array{sections: int, items: int}
     */
    public function import(Project $project, UploadedFile $file): array
    {
        $version = $this->rab->currentVersion($project);
        $this->rab->assertEditable($version);

        $rows = $this->readRows($file);
        $parsed = $this->parse($rows);

        return DB::transaction(function () use ($project, $version, $parsed) {
            $sections = $version->sections()->get()->keyBy('kode_bidang');
            $newSections = 0;
            $order = (int) $version->sections()->max('urutan');

            foreach ($parsed as $row) {
                $section = $sections->get($row['kode_bidang']);
                if (! $section) {
                    $section = $version->sections()->create([
                        'kode_bidang' => $row['kode_bidang'],
                        'nama' => $row['nama_bidang'],
                        'urutan' => ++$order,
                    ]);
                    $sections->put($row['kode_bidang'], $section);
                    $newSections++;
                }

                $section->items()->create([
                    'uraian' => $row['uraian'],
                    'deskripsi' => $row['deskripsi'],
                    'qty' => $row['qty'],
                    'satuan' => $row['satuan'],
                    'harga_satuan' => $row['harga_satuan'],
                    'durasi_hari' => $row['hari'],
                    'status' => WorkItem::STATUS_BELUM_MULAI,
                    'urutan' => (int) $section->items()->max('urutan') + 1,
                ]);
            }

            $this->progress->recalculate($project);

            return ['sections' => $newSections, 'items' => count($parsed)];
        });
    }

    public function template(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet()->setTitle('RAB');
        $sheet->fromArray(self::HEADERS, null, 'A1');
        $sheet->fromArray([
            ['II', 'Plumbing', 'Pemasangan pipa air bersih', 'Pipa PVC 3/4 inch', 25, 'm1', 85000, 3],
            ['II', 'Plumbing', 'Instalasi wastafel', null, 2, 'unit', 450000, 1],
            ['III', 'Elektrikal', 'Titik lampu LED', 'Termasuk kabel dan saklar', 12, 'pcs', 150000, 2],
        ], null, 'A2');

        $sheet->getStyle('A1:H1')->getFont()->setBold(true);
        $sheet->getStyle('A1:H1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFDE2E4');
        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setWidth($col === 'C' || $col === 'D' ? 40 : 16);
        }

        $help = $spreadsheet->createSheet()->setTitle('Petunjuk');
        $help->fromArray([
            ['Kolom', 'Wajib', 'Keterangan'],
            ['kode_bidang', 'Ya', 'Kode bidang, contoh II, III, IV. Baris dengan kode sama masuk ke bidang yang sama.'],
            ['nama_bidang', 'Ya', 'Nama bidang, contoh Plumbing. Dipakai saat bidang baru dibuat.'],
            ['uraian', 'Ya', 'Nama item pekerjaan.'],
            ['deskripsi', 'Tidak', 'Keterangan tambahan item.'],
            ['qty', 'Ya', 'Angka, boleh desimal. Contoh 12 atau 2.5'],
            ['satuan', 'Ya', 'unit, pcs, m2, m1, set, dll.'],
            ['harga_satuan', 'Ya', 'Angka tanpa "Rp" dan tanpa pemisah ribuan. Contoh 85000'],
            ['hari', 'Tidak', 'Durasi pekerjaan dalam hari (angka bulat).'],
            ['', '', 'Baris 1 harus header persis seperti sheet RAB. Total dihitung sistem (qty x harga). Jangan pakai sel gabungan atau baris subtotal.'],
        ], null, 'A1');
        $help->getStyle('A1:C1')->getFont()->setBold(true);
        $help->getColumnDimension('A')->setWidth(16);
        $help->getColumnDimension('C')->setWidth(90);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function readRows(UploadedFile $file): array
    {
        try {
            $sheet = IOFactory::load($file->getRealPath())->getSheet(0);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['file' => 'File tidak bisa dibaca. Gunakan file .xlsx sesuai template.']);
        }

        $rows = $sheet->toArray(null, true, false, false);

        $header = array_map(fn ($v) => strtolower(trim((string) $v)), array_slice($rows[0] ?? [], 0, count(self::HEADERS)));
        if ($header !== self::HEADERS) {
            throw ValidationException::withMessages([
                'file' => 'Header baris 1 tidak sesuai. Harus: '.implode(', ', self::HEADERS).'. Unduh template untuk contoh.',
            ]);
        }

        $rows = array_slice($rows, 1, null, true);
        if (count($rows) > self::MAX_ROWS) {
            throw ValidationException::withMessages(['file' => 'Maksimal '.self::MAX_ROWS.' baris per impor.']);
        }

        return $rows;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows  key = indeks baris (0-based)
     * @return array<int, array<string, mixed>>
     */
    private function parse(array $rows): array
    {
        $parsed = [];
        $errors = [];
        $names = [];

        foreach ($rows as $index => $cells) {
            $line = $index + 1;
            $cells = array_pad($cells, count(self::HEADERS), null);
            if (collect($cells)->every(fn ($v) => trim((string) $v) === '')) {
                continue;
            }

            $row = array_combine(self::HEADERS, array_slice($cells, 0, count(self::HEADERS)));
            $kode = trim((string) $row['kode_bidang']);
            $nama = trim((string) $row['nama_bidang']);
            $uraian = trim((string) $row['uraian']);
            $satuan = trim((string) $row['satuan']);
            $qty = $this->number($row['qty']);
            $harga = $this->number($row['harga_satuan']);
            $hari = trim((string) $row['hari']) === '' ? null : $this->number($row['hari']);

            $problems = [];
            if ($kode === '' || mb_strlen($kode) > 20) {
                $problems[] = 'kode_bidang kosong/terlalu panjang';
            }
            if ($uraian === '' || mb_strlen($uraian) > 255) {
                $problems[] = 'uraian kosong/terlalu panjang';
            }
            if ($satuan === '' || mb_strlen($satuan) > 30) {
                $problems[] = 'satuan kosong/terlalu panjang';
            }
            if ($qty === null || $qty < 0) {
                $problems[] = 'qty bukan angka';
            }
            if ($harga === null || $harga < 0) {
                $problems[] = 'harga_satuan bukan angka';
            }
            if ($hari !== null && ($hari < 0 || floor($hari) !== $hari)) {
                $problems[] = 'hari harus angka bulat';
            }
            if ($kode !== '' && ! isset($names[$kode])) {
                if ($nama === '') {
                    $problems[] = 'nama_bidang kosong (bidang baru '.$kode.')';
                } else {
                    $names[$kode] = $nama;
                }
            }

            if ($problems) {
                $errors[] = "Baris {$line}: ".implode(', ', $problems);

                continue;
            }

            $parsed[] = [
                'kode_bidang' => $kode,
                'nama_bidang' => $names[$kode],
                'uraian' => $uraian,
                'deskripsi' => trim((string) $row['deskripsi']) ?: null,
                'qty' => $qty,
                'satuan' => $satuan,
                'harga_satuan' => $harga,
                'hari' => $hari !== null ? (int) $hari : null,
            ];
        }

        if ($errors) {
            $shown = array_slice($errors, 0, 10);
            $more = count($errors) > 10 ? ' ... dan '.(count($errors) - 10).' baris error lain.' : '';
            throw ValidationException::withMessages(['file' => 'Tidak ada data yang disimpan. '.implode('; ', $shown).'.'.$more]);
        }

        if (! $parsed) {
            throw ValidationException::withMessages(['file' => 'File tidak berisi data pekerjaan.']);
        }

        return $parsed;
    }

    /** Angka dari sel Excel; string diterima dalam format Indonesia ("1.500.000,50"). */
    private function number(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $text = trim(str_ireplace(['Rp', ' '], '', (string) $value));
        if ($text === '') {
            return null;
        }
        if (str_contains($text, ',')) {
            $text = str_replace(',', '.', str_replace('.', '', $text));
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $text)) {
            $text = str_replace('.', '', $text);
        }

        return is_numeric($text) ? (float) $text : null;
    }
}
