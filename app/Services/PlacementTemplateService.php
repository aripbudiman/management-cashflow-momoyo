<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\ProjectPlacement;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Carbon\CarbonInterface;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class PlacementTemplateService
{
    public const DEFAULT_NUMBER_FORMAT = 'SPK/{kode-proyek}/{bulan-romawi}/{tahun}/{urut}';

    public const DEFAULT_MARGIN_CM = 2.5;

    /** @var array<string, string> variabel => keterangan */
    public const VARIABLES = [
        'nama_perusahaan' => 'Nama perusahaan (dari nama aplikasi)',
        'nomor_surat' => 'Nomor surat',
        'tanggal_surat' => 'Tanggal surat diterbitkan',
        'kode_proyek' => 'Kode proyek',
        'nama_proyek' => 'Nama proyek',
        'klien' => 'Klien / brand',
        'cabang' => 'Cabang / outlet',
        'alamat_proyek' => 'Alamat proyek',
        'lokasi' => 'Lokasi penempatan',
        'periode_mulai' => 'Tanggal mulai penempatan',
        'periode_selesai' => 'Tanggal selesai penempatan',
        'catatan' => 'Catatan penempatan',
        'tabel_karyawan' => 'Tabel karyawan (No, Nama, NIK, Peran)',
        'nama_penandatangan' => 'Nama penandatangan',
        'jabatan_penandatangan' => 'Jabatan penandatangan',
    ];

    private const ALLOWED_TAGS = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'h1', 'h2', 'h3', 'ul', 'ol', 'li', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'hr', 'blockquote', 'span', 'div'];

    private const ALLOWED_ATTRS = ['style', 'colspan', 'rowspan'];

    private const ROMAN = ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

    public function __construct(private readonly AppSettingService $app) {}

    /**
     * @return array{html: string, number_format: string, margin_cm: float}
     */
    public function get(): array
    {
        $stored = Schema::hasTable('app_settings')
            ? AppSetting::whereIn('key', ['placement_template', 'placement_number_format', 'placement_margin_cm'])->pluck('value', 'key')->all()
            : [];

        return [
            'html' => $stored['placement_template'] ?? $this->defaultTemplate(),
            'number_format' => $stored['placement_number_format'] ?? self::DEFAULT_NUMBER_FORMAT,
            'margin_cm' => (float) ($stored['placement_margin_cm'] ?? self::DEFAULT_MARGIN_CM),
        ];
    }

    public function save(string $html, string $numberFormat, float $marginCm): void
    {
        AppSetting::updateOrCreate(['key' => 'placement_template'], ['value' => $this->sanitize($html)]);
        AppSetting::updateOrCreate(['key' => 'placement_number_format'], ['value' => trim($numberFormat)]);
        AppSetting::updateOrCreate(['key' => 'placement_margin_cm'], ['value' => (string) $marginCm]);
    }

    public function resetHtml(): void
    {
        AppSetting::where('key', 'placement_template')->delete();
    }

    /** Nomor surat dari pola, contoh: SPK/MM002535/X/2026/001. */
    public function number(string $format, string $kodeProyek, CarbonInterface $date, int $sequence): string
    {
        return strtr($format, [
            '{kode-proyek}' => $kodeProyek,
            '{bulan-romawi}' => self::ROMAN[$date->month],
            '{tahun}' => (string) $date->year,
            '{urut}' => str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
        ]);
    }

    /** Dokumen HTML lengkap (siap dirender ke PDF / preview) dari template dan nilai variabel. */
    public function document(string $html, array $values, float $marginCm): string
    {
        $body = $this->fill($this->sanitize($html), $values);
        $margin = number_format($marginCm, 2, '.', '').'cm';

        return <<<HTML
        <!doctype html>
        <html><head><meta charset="utf-8">
        <style>
            @page { margin: {$margin}; }
            body { font-family: "Times New Roman", serif; font-size: 12pt; line-height: 1.4; color: #000; }
            p { margin: 0 0 8px; }
            h1, h2, h3 { margin: 0 0 8px; }
            table { border-collapse: collapse; width: 100%; margin-bottom: 8px; }
            td, th { border: 1px solid #000; padding: 4px 6px; vertical-align: top; text-align: left; }
            td p, th p { margin: 0; }
            hr { border: 0; border-top: 1.5px solid #000; margin: 8px 0 12px; }
        </style></head><body>{$body}</body></html>
        HTML;
    }

    public function pdf(ProjectPlacement $placement): DomPdf
    {
        $settings = $this->get();
        $html = $placement->template_snapshot ?? $settings['html'];

        return Pdf::loadHTML($this->document($html, $this->valuesFor($placement), $settings['margin_cm']))->setPaper('a4');
    }

    /**
     * @return array<string, string>
     */
    public function valuesFor(ProjectPlacement $placement): array
    {
        $placement->loadMissing(['project.branch', 'employees.employee']);
        $project = $placement->project;

        return $this->values([
            'nomor_surat' => $placement->nomor_surat ?? '(nomor belum terbit)',
            'tanggal_surat' => $this->date($placement->tgl_surat ?? today()),
            'kode_proyek' => $project->kode,
            'nama_proyek' => $project->nama,
            'klien' => $project->client_name ?? '-',
            'cabang' => $project->branch?->branch_name ?? '-',
            'alamat_proyek' => $project->alamat ?? '-',
            'lokasi' => $placement->lokasi ?: ($project->alamat ?? '-'),
            'periode_mulai' => $this->date($placement->tgl_mulai),
            'periode_selesai' => $this->date($placement->tgl_selesai),
            'catatan' => $placement->catatan ?? '-',
            'nama_penandatangan' => $placement->penandatangan_nama ?? '................',
            'jabatan_penandatangan' => $placement->penandatangan_jabatan ?? '................',
        ], $placement->employees->map(fn ($e) => [
            'nama' => $e->employee->full_name,
            'nik' => $e->employee->nik,
            'peran' => $e->peran,
        ])->all());
    }

    /**
     * @return array<string, string>
     */
    public function sampleValues(): array
    {
        return $this->values([
            'nomor_surat' => $this->number(self::DEFAULT_NUMBER_FORMAT, 'MM002535', today(), 1),
            'tanggal_surat' => $this->date(today()),
            'kode_proyek' => 'MM002535',
            'nama_proyek' => 'MMY-JTU-WINONG KIDUL',
            'klien' => 'MOMOYO',
            'cabang' => 'Pondok Cabe',
            'alamat_proyek' => 'Jl. Contoh No. 1, Winong Kidul',
            'lokasi' => 'Outlet Winong Kidul',
            'periode_mulai' => $this->date(today()),
            'periode_selesai' => $this->date(today()->addDays(30)),
            'catatan' => 'Contoh catatan penempatan.',
            'nama_penandatangan' => 'Nama Penandatangan',
            'jabatan_penandatangan' => 'Direktur',
        ], [
            ['nama' => 'Budi Santoso', 'nik' => '3201010101010001', 'peran' => 'Penanggung Jawab'],
            ['nama' => 'Siti Aminah', 'nik' => '3201010101010002', 'peran' => 'Pengawas'],
        ]);
    }

    /**
     * @param  array<string, string>  $values
     * @param  array<int, array{nama: string, nik: string, peran: string}>  $employees
     * @return array<string, string>
     */
    private function values(array $values, array $employees): array
    {
        $rows = '';
        foreach ($employees as $i => $e) {
            $rows .= '<tr><td>'.($i + 1).'</td><td>'.e($e['nama']).'</td><td>'.e($e['nik']).'</td><td>'.e($e['peran']).'</td></tr>';
        }

        return array_map('e', $values + ['nama_perusahaan' => $this->app->name()])
            + ['tabel_karyawan' => '<table><thead><tr><th>No</th><th>Nama</th><th>NIK</th><th>Peran</th></tr></thead><tbody>'.$rows.'</tbody></table>'];
    }

    /**
     * Ganti {{variabel}}. Nilai sudah di-escape oleh values(); tabel_karyawan berupa HTML.
     *
     * @param  array<string, string>  $values
     */
    private function fill(string $html, array $values): string
    {
        // Tabel tidak boleh berada di dalam <p>
        $html = preg_replace('#<p[^>]*>\s*\{\{\s*tabel_karyawan\s*\}\}\s*</p>#i', '{{tabel_karyawan}}', $html) ?? $html;

        return preg_replace_callback(
            '/\{\{\s*([a-z_]+)\s*\}\}/',
            fn (array $m) => $values[$m[1]] ?? $m[0],
            $html,
        ) ?? $html;
    }

    /** Hanya tag dan atribut aman dari editor yang dipertahankan. */
    public function sanitize(string $html): string
    {
        $clean = strip_tags($html, self::ALLOWED_TAGS);
        if (trim($clean) === '') {
            return '';
        }

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><div id="root">'.$clean.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $root = $dom->getElementById('root');

        foreach ($dom->getElementsByTagName('*') as $el) {
            /** @var DOMElement $el */
            foreach (iterator_to_array($el->attributes) as $attr) {
                if (! in_array($attr->name, self::ALLOWED_ATTRS, true)) {
                    $el->removeAttribute($attr->name);
                } elseif ($attr->name === 'style') {
                    preg_match('/text-align:\s*(left|right|center|justify)/i', $attr->value, $m);
                    $m ? $el->setAttribute('style', 'text-align: '.strtolower($m[1])) : $el->removeAttribute('style');
                }
            }
        }

        $out = '';
        foreach ($root?->childNodes ?? [] as $child) {
            $out .= $dom->saveHTML($child);
        }

        return $out;
    }

    private function date(\DateTimeInterface $date): string
    {
        return Carbon::instance($date)->locale('id')->translatedFormat('d F Y');
    }

    private function defaultTemplate(): string
    {
        return <<<'HTML'
        <h2 style="text-align: center">{{nama_perusahaan}}</h2>
        <hr>
        <h3 style="text-align: center">SURAT PENEMPATAN KERJA</h3>
        <p style="text-align: center">Nomor: {{nomor_surat}}</p>
        <p>Dengan ini kami menempatkan karyawan berikut untuk bekerja pada proyek:</p>
        <table><tbody>
        <tr><td>Kode Proyek</td><td>: {{kode_proyek}}</td></tr>
        <tr><td>Nama Proyek</td><td>: {{nama_proyek}}</td></tr>
        <tr><td>Klien</td><td>: {{klien}}</td></tr>
        <tr><td>Lokasi</td><td>: {{lokasi}}</td></tr>
        <tr><td>Periode</td><td>: {{periode_mulai}} s/d {{periode_selesai}}</td></tr>
        </tbody></table>
        <p>Daftar karyawan yang ditempatkan:</p>
        <p>{{tabel_karyawan}}</p>
        <p>Catatan: {{catatan}}</p>
        <p>Demikian surat penempatan kerja ini dibuat untuk dilaksanakan dengan penuh tanggung jawab.</p>
        <p style="text-align: right">Ditetapkan pada {{tanggal_surat}}</p>
        <p style="text-align: right">{{jabatan_penandatangan}}</p>
        <p style="text-align: right"><br><br><br></p>
        <p style="text-align: right"><u>{{nama_penandatangan}}</u></p>
        HTML;
    }
}
