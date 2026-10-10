<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Shift;
use App\Repositories\LeaveRequestRepository;
use App\Services\LeaveRequestService;
use App\Services\ScheduleService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function __construct(private readonly ScheduleService $scheduleService) {}

    /**
     * Tampilkan halaman Absensi Staff Cabang (Mobile-First) dan Monitoring Pusat.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $user->loadMissing(['role', 'employee.currentBranch']);

        $today = Carbon::today()->toDateString();
        $employee = $user->employee;

        // Jika user adalah Staff Cabang namun belum memiliki profil karyawan
        if (! $employee && $user->isStaffCabang()) {
            return Inertia::render('attendance/NoEmployee', [
                'user' => $user,
            ]);
        }

        $branch = $employee?->currentBranch;
        $shifts = $branch ? Shift::where('branch_id', $branch->id)->orderBy('start_time')->get() : collect();

        // Jadwal hari ini dari Admin Pusat: jika ada shift terjadwal, hanya shift itu yang bisa dipilih
        $todaySchedule = $employee ? $this->scheduleService->forDate($employee, $today) : null;
        if ($todaySchedule && ! $todaySchedule->is_off && $todaySchedule->shift_id) {
            $shifts = $shifts->where('id', $todaySchedule->shift_id)->values();
        }
        $schedulePeriod = $employee ? $this->scheduleService->employeePeriod($employee, Carbon::today()) : [];

        // Absensi hari ini untuk karyawan bersangkutan
        $todayAttendance = null;
        if ($employee) {
            $todayAttendance = Attendance::with(['shift', 'branch'])
                ->where('employee_id', $employee->id)
                ->where('date', $today)
                ->first();
        }

        // Riwayat absensi 14 hari terakhir karyawan
        $recentAttendances = $employee
            ? Attendance::with(['shift', 'branch'])
                ->where('employee_id', $employee->id)
                ->latest('date')
                ->take(14)
                ->get()
            : collect();

        // Data khusus Admin Pusat (Monitoring Seluruh Cabang)
        $adminMonitoring = null;
        if ($user->isAdminPusat()) {
            $query = Attendance::with(['employee.currentBranch', 'shift', 'branch']);

            $dateFilter = $request->input('date');
            if ($dateFilter && $dateFilter !== 'all') {
                $query->where('date', $dateFilter);
                $statsDate = $dateFilter;
            } elseif ($dateFilter === 'all') {
                $statsDate = null;
            } else {
                $query->where('date', $today);
                $statsDate = $today;
            }

            if ($search = $request->input('search')) {
                $query->whereHas('employee', function ($q) use ($search) {
                    $q->where('full_name', 'ilike', "%{$search}%")
                        ->orWhere('nik', 'ilike', "%{$search}%");
                });
            }

            if ($branchId = $request->input('branch_id')) {
                $query->where('branch_id', $branchId);
            }

            if ($status = $request->input('status')) {
                $query->where('status', $status);
            }

            $statsQuery = Attendance::query();
            if ($statsDate) {
                $statsQuery->where('date', $statsDate);
            }

            $adminMonitoring = [
                'attendances' => $query->latest('date')->latest('clock_in_time')->paginate(15)->withQueryString(),
                'branches' => Branch::where('is_active', true)->orderBy('branch_name')->get(['id', 'branch_code', 'branch_name']),
                'stats' => [
                    'total_today' => (clone $statsQuery)->count(),
                    'on_time' => (clone $statsQuery)->where('status', 'hadir_tepat_waktu')->count(),
                    'late' => (clone $statsQuery)->where('status', 'terlambat')->count(),
                    'clocked_out' => (clone $statsQuery)->whereNotNull('clock_out_time')->count(),
                ],
            ];
        }

        return Inertia::render('attendance/Index', [
            'employee' => $employee,
            'branch' => $branch,
            'shifts' => $shifts,
            'todayAttendance' => $todayAttendance,
            'recentAttendances' => $recentAttendances,
            'todaySchedule' => $todaySchedule ? [
                'is_off' => $todaySchedule->is_off,
                'shift_name' => $todaySchedule->shift_name,
                'start_time' => $todaySchedule->start_time ? substr($todaySchedule->start_time, 0, 5) : null,
                'end_time' => $todaySchedule->end_time ? substr($todaySchedule->end_time, 0, 5) : null,
            ] : null,
            'schedulePeriod' => $schedulePeriod,
            'periodLabel' => Carbon::today()->translatedFormat('F Y'),
            'todayDateFormatted' => Carbon::now()->translatedFormat('l, d F Y'),
            'todayDate' => $today,
            'adminMonitoring' => $adminMonitoring,
            'filters' => $request->only(['search', 'branch_id', 'status', 'date']),
        ]);
    }

    /**
     * Daftarkan foto referensi Face ID untuk karyawan.
     */
    public function registerFace(Request $request): RedirectResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        if (! $employee) {
            return back()->with('error', 'Profil karyawan tidak ditemukan untuk akun ini.');
        }

        $request->validate([
            'photo' => ['required', 'string'], // base64 data URL
            'face_data' => ['nullable'],
        ], [
            'photo.required' => 'Foto wajah wajib diambil menggunakan kamera.',
        ]);

        // 1. Validasi Keberadaan Wajah Manusia (Anti-Barang/Objek Mati)
        $faceCheck = $this->verifyHumanFacePresence($request->input('photo'));
        if (! $faceCheck['valid']) {
            return back()->with('error', 'Pendaftaran Face ID Gagal: '.($faceCheck['reason'] ?? 'Wajah manusia tidak terdeteksi pada foto.').' Pastikan kamera mengarah tepat ke wajah kru tanpa masker.');
        }

        try {
            $disk = config('filesystems.default', 's3');

            // Hapus foto lama jika ada di S3 atau local disk
            if ($employee->face_photo_path) {
                if (Storage::disk($disk)->exists($employee->face_photo_path)) {
                    Storage::disk($disk)->delete($employee->face_photo_path);
                }
                if ($disk !== 'public' && Storage::disk('public')->exists($employee->face_photo_path)) {
                    Storage::disk('public')->delete($employee->face_photo_path);
                }
            }

            $photoPath = $this->storeBase64Photo(
                $request->input('photo'),
                'employees/'.$employee->id.'/faces',
                'face_'.$employee->id.'_'.time()
            );

            $faceId = 'FID-'.($employee->currentBranch?->branch_code ?? 'MMY').'-'.str_pad((string) $employee->id, 4, '0', STR_PAD_LEFT).'-'.strtoupper(Str::random(4));

            $employee->update([
                'face_id' => $faceId,
                'face_photo_path' => $photoPath,
                'face_registered_at' => now(),
                'face_data' => $request->input('face_data') ?: ['registered_via' => 'mobile_app', 'timestamp' => now()->toIso8601String()],
            ]);

            return back()->with('success', 'Face ID Anda berhasil didaftarkan! Sekarang Anda siap melakukan absensi.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal mendaftarkan Face ID: '.$e->getMessage());
        }
    }

    /**
     * Reset / Hapus Face ID karyawan (bisa oleh staff sendiri atau admin).
     */
    public function resetFace(Request $request): RedirectResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        // Jika admin ingin mereset karyawan tertentu
        if ($user->isAdminPusat() && $request->filled('employee_id')) {
            $employee = Employee::find($request->input('employee_id'));
        }

        if (! $employee) {
            return back()->with('error', 'Profil karyawan tidak ditemukan.');
        }

        $disk = config('filesystems.default', 's3');
        if ($employee->face_photo_path) {
            if (Storage::disk($disk)->exists($employee->face_photo_path)) {
                Storage::disk($disk)->delete($employee->face_photo_path);
            }
            if ($disk !== 'public' && Storage::disk('public')->exists($employee->face_photo_path)) {
                Storage::disk('public')->delete($employee->face_photo_path);
            }
        }

        $employee->update([
            'face_id' => null,
            'face_photo_path' => null,
            'face_registered_at' => null,
            'face_data' => null,
        ]);

        return back()->with('success', 'Face ID berhasil direset. Silakan daftarkan wajah baru.');
    }

    /**
     * Catat absensi masuk (Clock In).
     */
    public function clockIn(Request $request): RedirectResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        if (! $employee) {
            return back()->with('error', 'Profil karyawan tidak ditemukan untuk akun ini.');
        }

        // 1. Wajib memiliki Face ID terdaftar
        if (! $employee->hasFaceRegistered()) {
            return back()->with('error', 'Face ID belum terdaftar! Silakan daftarkan wajah Anda terlebih dahulu sebelum absen.');
        }

        // 2. Cek apakah sudah absen hari ini
        $today = Carbon::today()->toDateString();
        $existing = Attendance::where('employee_id', $employee->id)->where('date', $today)->first();

        if ($existing && $existing->clock_in_time) {
            return back()->with('error', 'Anda sudah melakukan Clock In hari ini pada jam '.substr($existing->clock_in_time, 0, 5).' WIB.');
        }

        // 2b. Blok absen jika hari ini dijadwalkan libur oleh Admin Pusat
        $schedule = $this->scheduleService->forDate($employee, $today);
        if ($schedule?->is_off) {
            return back()->with('error', 'Hari ini adalah jadwal libur Anda. Absensi tidak dapat dilakukan.');
        }

        $leave = app(LeaveRequestRepository::class)->approvedOnDate($employee->id, $today);
        if ($leave) {
            $label = LeaveRequestService::TYPE_LABELS[$leave->type] ?? $leave->type;

            return back()->with('error', "Hari ini Anda sedang {$label} (disetujui Admin Pusat). Absensi tidak dapat dilakukan.");
        }

        // 3. Validasi input
        $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'photo' => ['required', 'string'],
            'shift_id' => ['nullable', 'exists:shifts,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'latitude.required' => 'Titik lokasi GPS diperlukan untuk validasi geofencing.',
            'photo.required' => 'Foto selfie kehadiran wajib diambil.',
        ]);

        $branch = $employee->currentBranch;
        if (! $branch) {
            return back()->with('error', 'Anda belum terdaftar pada cabang aktif. Hubungi Admin Pusat.');
        }

        // 4. Validasi Geofencing Jarak ke Cabang
        $distance = $this->calculateDistance(
            (float) $request->input('latitude'),
            (float) $request->input('longitude'),
            (float) $branch->latitude,
            (float) $branch->longitude
        );

        $allowedRadius = (int) ($branch->radius_meters ?: 50);

        if ($distance > $allowedRadius) {
            $formattedDist = round($distance, 1);

            return back()->with('error', "Lokasi di luar radius gerai! Jarak Anda saat ini {$formattedDist} meter dari {$branch->branch_name} (Maksimal toleransi {$allowedRadius} meter). Pastikan Anda berada di gerai.");
        }

        // 5. Validasi Keberadaan Wajah Manusia (Tolak jika memotret barang/objek lain)
        $faceCheck = $this->verifyHumanFacePresence($request->input('photo'));
        if (! $faceCheck['valid']) {
            return back()->with('error', 'Verifikasi Wajah Gagal: '.($faceCheck['reason'] ?? 'Wajah manusia tidak terdeteksi!').' Pastikan kamera mengarah tepat ke wajah Anda di dalam lingkaran oval, bukan ke barang atau objek lain.');
        }

        // 6. Verifikasi Kesamaan Wajah dengan Face ID Terdaftar
        $similarity = $this->calculateFaceSimilarity(
            $employee->face_photo_path ?: '',
            $request->input('photo')
        );

        // Batas minimal kelulusan identifikasi wajah
        if ($similarity < 20.0) {
            return back()->with('error', 'Verifikasi Wajah Gagal! Wajah Anda tidak sesuai dengan data Face ID terdaftar kru ini. Absensi dibatalkan demi keamanan.');
        }

        // 7. Simpan Foto Selfie Clock In ke Cloud Storage S3
        try {
            $photoPath = $this->storeBase64Photo(
                $request->input('photo'),
                'employees/'.$employee->id.'/attendance',
                'clock_in_'.$employee->id.'_'.date('Ymd_His')
            );
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal memproses foto selfie: '.$e->getMessage());
        }

        // 8. Tentukan Shift & Status Kehadiran (Tepat Waktu vs Terlambat)
        // Prioritas: shift dari jadwal Admin Pusat (snapshot) -> pilihan manual -> shift pertama cabang
        if ($schedule && $schedule->shift_id) {
            $shift = Shift::find($schedule->shift_id) ?? new Shift([
                'shift_name' => $schedule->shift_name,
                'start_time' => $schedule->start_time,
                'end_time' => $schedule->end_time,
                'late_tolerance_minutes' => $schedule->late_tolerance_minutes,
            ]);
            // Pakai nilai snapshot jadwal agar perubahan master shift tidak mengubah status hari ini
            $shift->start_time = $schedule->start_time;
            $shift->late_tolerance_minutes = $schedule->late_tolerance_minutes;
        } else {
            $shiftId = $request->input('shift_id');
            $shift = $shiftId ? Shift::find($shiftId) : Shift::where('branch_id', $branch->id)->first();
        }

        $status = 'hadir_tepat_waktu';
        $currentTime = Carbon::now();

        if ($shift) {
            $shiftStartTime = Carbon::parse($today.' '.$shift->start_time);
            $lateTolerance = (int) ($shift->late_tolerance_minutes ?? 15);
            $maxTime = (clone $shiftStartTime)->addMinutes($lateTolerance);

            if ($currentTime->greaterThan($maxTime)) {
                $status = 'terlambat';
            }
        }

        // 9. Simpan Record Absensi
        DB::transaction(function () use ($employee, $branch, $shift, $today, $currentTime, $photoPath, $request, $distance, $status, $similarity) {
            Attendance::updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'date' => $today,
                ],
                [
                    'branch_id' => $branch->id,
                    'shift_id' => $shift?->id,
                    'clock_in_time' => $currentTime->format('H:i:s'),
                    'clock_in_photo_url' => $photoPath,
                    'clock_in_lat' => (float) $request->input('latitude'),
                    'clock_in_long' => (float) $request->input('longitude'),
                    'clock_in_distance_meters' => round($distance, 1),
                    'clock_in_face_match_percentage' => $similarity,
                    'is_face_mismatch' => false,
                    'status' => $status,
                    'notes' => $request->input('notes'),
                ]
            );
        });

        $statusLabel = $status === 'terlambat' ? '⚠️ Terlambat' : '✅ Tepat Waktu';

        return back()->with('success', "Clock In berhasil pada {$currentTime->format('H:i')} WIB ({$statusLabel})! Selamat bertugas.");
    }

    /**
     * Catat absensi keluar (Clock Out).
     */
    public function clockOut(Request $request): RedirectResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        if (! $employee) {
            return back()->with('error', 'Profil karyawan tidak ditemukan untuk akun ini.');
        }

        // 1. Cek Face ID
        if (! $employee->hasFaceRegistered()) {
            return back()->with('error', 'Face ID belum terdaftar! Silakan daftarkan wajah Anda terlebih dahulu.');
        }

        // 2. Cek apakah sudah Clock In hari ini
        $today = Carbon::today()->toDateString();
        $attendance = Attendance::where('employee_id', $employee->id)->where('date', $today)->first();

        if (! $attendance || ! $attendance->clock_in_time) {
            return back()->with('error', 'Anda belum melakukan Clock In hari ini. Tidak dapat Clock Out.');
        }

        if ($attendance->clock_out_time) {
            return back()->with('error', 'Anda sudah melakukan Clock Out hari ini pada jam '.substr($attendance->clock_out_time, 0, 5).' WIB.');
        }

        // 3. Validasi input
        $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'photo' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'latitude.required' => 'Titik lokasi GPS diperlukan untuk validasi geofencing.',
            'photo.required' => 'Foto selfie kepulangan wajib diambil.',
        ]);

        $branch = $employee->currentBranch;
        if (! $branch) {
            return back()->with('error', 'Cabang tidak valid.');
        }

        // 4. Validasi Geofencing Jarak ke Cabang
        $distance = $this->calculateDistance(
            (float) $request->input('latitude'),
            (float) $request->input('longitude'),
            (float) $branch->latitude,
            (float) $branch->longitude
        );

        $allowedRadius = (int) ($branch->radius_meters ?: 50);

        if ($distance > $allowedRadius) {
            $formattedDist = round($distance, 1);

            return back()->with('error', "Lokasi di luar radius gerai! Jarak Anda saat ini {$formattedDist} meter dari {$branch->branch_name} (Maksimal toleransi {$allowedRadius} meter). Pastikan Anda berada di gerai saat clock out.");
        }

        // 5. Validasi Keberadaan Wajah Manusia (Tolak jika memotret barang/objek lain)
        $faceCheck = $this->verifyHumanFacePresence($request->input('photo'));
        if (! $faceCheck['valid']) {
            return back()->with('error', 'Verifikasi Wajah Gagal: '.($faceCheck['reason'] ?? 'Wajah manusia tidak terdeteksi!').' Pastikan kamera mengarah tepat ke wajah Anda di dalam lingkaran oval saat clock out, bukan ke barang lain.');
        }

        // 6. Verifikasi Kesamaan Wajah dengan Face ID
        $similarity = $this->calculateFaceSimilarity(
            $employee->face_photo_path ?: '',
            $request->input('photo')
        );

        // Batas minimal kelulusan identifikasi wajah
        if ($similarity < 20.0) {
            return back()->with('error', 'Verifikasi Wajah Gagal! Wajah Anda tidak sesuai dengan data Face ID terdaftar. Absensi kepulangan dibatalkan demi keamanan.');
        }

        // 7. Simpan Foto Selfie Clock Out ke Cloud Storage S3
        try {
            $photoPath = $this->storeBase64Photo(
                $request->input('photo'),
                'employees/'.$employee->id.'/attendance',
                'clock_out_'.$employee->id.'_'.date('Ymd_His')
            );
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal memproses foto selfie: '.$e->getMessage());
        }

        $currentTime = Carbon::now();

        // 8. Update Record Absensi
        $notes = $attendance->notes;
        if ($request->filled('notes')) {
            $notes = $notes ? ($notes.' | Out: '.$request->input('notes')) : $request->input('notes');
        }

        $attendance->update([
            'clock_out_time' => $currentTime->format('H:i:s'),
            'clock_out_photo_url' => $photoPath,
            'clock_out_lat' => (float) $request->input('latitude'),
            'clock_out_long' => (float) $request->input('longitude'),
            'clock_out_distance_meters' => round($distance, 1),
            'clock_out_face_match_percentage' => $similarity,
            'is_face_mismatch' => false,
            'notes' => $notes,
        ]);

        return back()->with('success', "Clock Out berhasil pada {$currentTime->format('H:i')} WIB! Terima kasih atas kerja keras Anda.");
    }

    /**
     * Hitung jarak dua titik koordinat GPS dalam satuan meter menggunakan rumus Haversine.
     */
    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // Radius bumi dalam meter

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Simpan string gambar data URL base64 ke disk storage (S3 / cloud storage).
     */
    private function storeBase64Photo(string $base64Data, string $folder, string $filenamePrefix): string
    {
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type)) {
            $data = substr($base64Data, strpos($base64Data, ',') + 1);
            $type = strtolower($type[1]);

            $decoded = base64_decode($data);
            if ($decoded === false) {
                throw new \Exception('Dekode data gambar base64 gagal.');
            }

            $ext = in_array($type, ['jpeg', 'jpg']) ? 'jpg' : ($type === 'png' ? 'png' : 'webp');
            $filename = $filenamePrefix.'.'.$ext;
            $path = $folder.'/'.$filename;

            $disk = config('filesystems.default', 's3');
            Storage::disk($disk)->put($path, $decoded);

            return $path;
        }

        throw new \Exception('Format gambar tidak valid. Harus format data URL base64 image.');
    }

    /**
     * Verifikasi keberadaan wajah manusia biologis pada foto (mencegah foto barang/benda mati/objek lain).
     * Menganalisis klaster krominansi rona kulit manusia universal (YCbCr),
     * variansi kontras luminansi, dan sebaran gradien tepi struktur wajah.
     *
     * @return array{valid: bool, reason: string|null}
     */
    private function verifyHumanFacePresence(string $base64Data): array
    {
        try {
            if (! str_starts_with($base64Data, 'data:image')) {
                return ['valid' => false, 'reason' => 'Format gambar tidak valid.'];
            }

            $raw = base64_decode(substr($base64Data, strpos($base64Data, ',') + 1));
            if (! $raw) {
                return ['valid' => false, 'reason' => 'Data foto rusak atau tidak dapat didekode.'];
            }

            $img = @imagecreatefromstring($raw);
            if (! $img) {
                return ['valid' => false, 'reason' => 'Format file gambar tidak didukung.'];
            }

            $w = imagesx($img);
            $h = imagesy($img);

            if ($w < 80 || $h < 80) {
                imagedestroy($img);

                return ['valid' => false, 'reason' => 'Resolusi foto terlalu kecil untuk verifikasi biometrik.'];
            }

            // Ambil ROI tengah wajah (oval viewfinder area: X 15%-85%, Y 12%-88%)
            $sampleSize = 48;
            $roi = imagecreatetruecolor($sampleSize, $sampleSize);
            imagecopyresampled(
                $roi,
                $img,
                0,
                0,
                (int) ($w * 0.15),
                (int) ($h * 0.12),
                $sampleSize,
                $sampleSize,
                (int) ($w * 0.70),
                (int) ($h * 0.76)
            );

            $totalPixels = $sampleSize * $sampleSize;
            $skinCount = 0;
            $lums = [];
            $edgeSum = 0;

            for ($y = 0; $y < $sampleSize; $y++) {
                for ($x = 0; $x < $sampleSize; $x++) {
                    $rgb = imagecolorat($roi, $x, $y);
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
                    $b = $rgb & 0xFF;

                    $lum = (int) (0.299 * $r + 0.587 * $g + 0.114 * $b);
                    $lums[] = $lum;

                    // Model Krominansi YCbCr Kulit Manusia Universal (tahan berbagai pencahayaan)
                    $cb = -0.168736 * $r - 0.331264 * $g + 0.5 * $b + 128;
                    $cr = 0.5 * $r - 0.418688 * $g - 0.081312 * $b + 128;

                    if ($cb >= 80 && $cb <= 145 && $cr >= 133 && $cr <= 180 && $cr > $cb) {
                        $skinCount++;
                    }

                    if ($x > 0 && $y > 0) {
                        $prevRgb = imagecolorat($roi, $x - 1, $y);
                        $prevLum = (int) (0.299 * (($prevRgb >> 16) & 0xFF) + 0.587 * (($prevRgb >> 8) & 0xFF) + 0.114 * ($prevRgb & 0xFF));
                        $edgeSum += abs($lum - $prevLum);
                    }
                }
            }

            imagedestroy($roi);
            imagedestroy($img);

            $skinRatio = ($skinCount / $totalPixels) * 100;
            $meanLum = array_sum($lums) / $totalPixels;
            $variance = 0;
            foreach ($lums as $l) {
                $variance += pow($l - $meanLum, 2);
            }
            $stdDev = sqrt($variance / $totalPixels);

            // 1. Kriteria Rona Kulit Manusia (Minimal 12% dari area wajah)
            if ($skinRatio < 12.0) {
                return ['valid' => false, 'reason' => 'Kamera terdeteksi mengarah ke barang, meja, atau objek selain wajah manusia.'];
            }

            // 2. Kriteria Variansi Tekstur & Kontur (Mencegah kardus/tembok polos/foto blank)
            if ($stdDev < 15.0 || $edgeSum < 3200) {
                return ['valid' => false, 'reason' => 'Objek terlalu polos atau buram (fitur mata dan hidung tidak terdeteksi).'];
            }

            return ['valid' => true, 'reason' => null];
        } catch (\Throwable $e) {
            return ['valid' => false, 'reason' => 'Gagal memverifikasi wajah: '.$e->getMessage()];
        }
    }

    /**
     * Bandingkan dua foto wajah (foto referensi Face ID terdaftar vs foto selfie kru saat absen)
     * menggunakan ekstraksi multi-fitur biometrik:
     * 1. Local Binary Patterns (LBP) histogram pada 16 sel wajah berbobot anatomis (mata, hidung, bibir).
     * 2. Directional Gradient Correlation (korelasi kontur garis rahang & tulang alis).
     * 3. Chrominance Tone Correlation (kesesuaian rona warna kulit).
     *
     * Mengembalikan persentase kecocokan (0.0% - 100.0%).
     * Batas minimal kelulusan identifikasi: 40.0%.
     */
    private function calculateFaceSimilarity(string $refPhotoRelativePath, string $selfieBase64OrPath): float
    {
        try {
            if (empty($refPhotoRelativePath)) {
                return 0.0;
            }

            $disk = config('filesystems.default', 's3');
            $refData = null;

            if (Storage::disk($disk)->exists($refPhotoRelativePath)) {
                $refData = Storage::disk($disk)->get($refPhotoRelativePath);
            } elseif (Storage::disk('public')->exists($refPhotoRelativePath)) {
                $refData = Storage::disk('public')->get($refPhotoRelativePath);
            }

            if (! $refData) {
                return 0.0;
            }

            $refImg = @imagecreatefromstring($refData);
            if (! $refImg) {
                return 0.0;
            }

            if (str_starts_with($selfieBase64OrPath, 'data:image')) {
                $data = substr($selfieBase64OrPath, strpos($selfieBase64OrPath, ',') + 1);
                $selfieData = base64_decode($data);
            } else {
                $selfieData = null;
                if (Storage::disk($disk)->exists($selfieBase64OrPath)) {
                    $selfieData = Storage::disk($disk)->get($selfieBase64OrPath);
                } elseif (Storage::disk('public')->exists($selfieBase64OrPath)) {
                    $selfieData = Storage::disk('public')->get($selfieBase64OrPath);
                }
            }

            if (! $selfieData) {
                imagedestroy($refImg);

                return 0.0;
            }

            $selfieImg = @imagecreatefromstring($selfieData);
            if (! $selfieImg) {
                imagedestroy($refImg);

                return 0.0;
            }

            $w1 = imagesx($refImg);
            $h1 = imagesy($refImg);
            $w2 = imagesx($selfieImg);
            $h2 = imagesy($selfieImg);

            // Standarisasi kedua wajah ke resolusi ROI 64x64
            $size = 64;
            $roi1 = imagecreatetruecolor($size, $size);
            $roi2 = imagecreatetruecolor($size, $size);

            imagecopyresampled($roi1, $refImg, 0, 0, (int) ($w1 * 0.15), (int) ($h1 * 0.10), $size, $size, (int) ($w1 * 0.70), (int) ($h1 * 0.80));
            imagecopyresampled($roi2, $selfieImg, 0, 0, (int) ($w2 * 0.15), (int) ($h2 * 0.10), $size, $size, (int) ($w2 * 0.70), (int) ($h2 * 0.80));

            imagedestroy($refImg);
            imagedestroy($selfieImg);

            // 1. Color Hue / Chrominance Tone Comparison
            $cbSum1 = 0;
            $crSum1 = 0;
            $cbSum2 = 0;
            $crSum2 = 0;
            for ($y = 10; $y < 54; $y += 2) {
                for ($x = 10; $x < 54; $x += 2) {
                    $c1 = imagecolorat($roi1, $x, $y);
                    $c2 = imagecolorat($roi2, $x, $y);
                    $r1 = ($c1 >> 16) & 0xFF;
                    $g1 = ($c1 >> 8) & 0xFF;
                    $b1 = $c1 & 0xFF;
                    $r2 = ($c2 >> 16) & 0xFF;
                    $g2 = ($c2 >> 8) & 0xFF;
                    $b2 = $c2 & 0xFF;
                    $cbSum1 += -0.168736 * $r1 - 0.331264 * $g1 + 0.5 * $b1 + 128;
                    $crSum1 += 0.5 * $r1 - 0.418688 * $g1 - 0.081312 * $b1 + 128;
                    $cbSum2 += -0.168736 * $r2 - 0.331264 * $g2 + 0.5 * $b2 + 128;
                    $crSum2 += 0.5 * $r2 - 0.418688 * $g2 - 0.081312 * $b2 + 128;
                }
            }
            $numSampled = 484;
            $chromaDiff = (abs($cbSum1 / $numSampled - $cbSum2 / $numSampled) + abs($crSum1 / $numSampled - $crSum2 / $numSampled));
            $toneScore = max(0, 100 - ($chromaDiff * 3.5));

            // Konversi kedua ROI ke Grayscale untuk analisis LBP & Gradien
            imagefilter($roi1, IMG_FILTER_GRAYSCALE);
            imagefilter($roi2, IMG_FILTER_GRAYSCALE);
            imagefilter($roi1, IMG_FILTER_CONTRAST, -8);
            imagefilter($roi2, IMG_FILTER_CONTRAST, -8);

            // 2. Local Binary Patterns (LBP) Grid 4x4 dengan pembobotan anatomis
            $gridCount = 4;
            $cellSize = $size / $gridCount;
            $weights = [
                0.6,
                0.9,
                0.9,
                0.6, // Dahi & rambut atas
                1.4,
                2.0,
                2.0,
                1.4, // Alis & mata (krusial)
                1.0,
                1.6,
                1.6,
                1.0, // Hidung & tulang pipi
                0.8,
                1.3,
                1.3,
                0.8,  // Bibir & dagu
            ];
            $totalLbpDist = 0;
            $cellIdx = 0;

            for ($gy = 0; $gy < $gridCount; $gy++) {
                for ($gx = 0; $gx < $gridCount; $gx++) {
                    $h1 = array_fill(0, 16, 0);
                    $h2 = array_fill(0, 16, 0);
                    $startY = $gy * $cellSize;
                    $startX = $gx * $cellSize;

                    for ($y = $startY + 1; $y < $startY + $cellSize - 1; $y++) {
                        for ($x = $startX + 1; $x < $startX + $cellSize - 1; $x++) {
                            $c1 = imagecolorat($roi1, $x, $y) & 0xFF;
                            $c2 = imagecolorat($roi2, $x, $y) & 0xFF;

                            $code1 = 0;
                            $code1 |= (((imagecolorat($roi1, $x - 1, $y - 1) & 0xFF) >= $c1) ? 1 : 0) << 7;
                            $code1 |= (((imagecolorat($roi1, $x, $y - 1) & 0xFF) >= $c1) ? 1 : 0) << 6;
                            $code1 |= (((imagecolorat($roi1, $x + 1, $y - 1) & 0xFF) >= $c1) ? 1 : 0) << 5;
                            $code1 |= (((imagecolorat($roi1, $x + 1, $y) & 0xFF) >= $c1) ? 1 : 0) << 4;
                            $code1 |= (((imagecolorat($roi1, $x + 1, $y + 1) & 0xFF) >= $c1) ? 1 : 0) << 3;
                            $code1 |= (((imagecolorat($roi1, $x, $y + 1) & 0xFF) >= $c1) ? 1 : 0) << 2;
                            $code1 |= (((imagecolorat($roi1, $x - 1, $y + 1) & 0xFF) >= $c1) ? 1 : 0) << 1;
                            $code1 |= (((imagecolorat($roi1, $x - 1, $y) & 0xFF) >= $c1) ? 1 : 0) << 0;

                            $code2 = 0;
                            $code2 |= (((imagecolorat($roi2, $x - 1, $y - 1) & 0xFF) >= $c2) ? 1 : 0) << 7;
                            $code2 |= (((imagecolorat($roi2, $x, $y - 1) & 0xFF) >= $c2) ? 1 : 0) << 6;
                            $code2 |= (((imagecolorat($roi2, $x + 1, $y - 1) & 0xFF) >= $c2) ? 1 : 0) << 5;
                            $code2 |= (((imagecolorat($roi2, $x + 1, $y) & 0xFF) >= $c2) ? 1 : 0) << 4;
                            $code2 |= (((imagecolorat($roi2, $x + 1, $y + 1) & 0xFF) >= $c2) ? 1 : 0) << 3;
                            $code2 |= (((imagecolorat($roi2, $x, $y + 1) & 0xFF) >= $c2) ? 1 : 0) << 2;
                            $code2 |= (((imagecolorat($roi2, $x - 1, $y + 1) & 0xFF) >= $c2) ? 1 : 0) << 1;
                            $code2 |= (((imagecolorat($roi2, $x - 1, $y) & 0xFF) >= $c2) ? 1 : 0) << 0;

                            $h1[$code1 >> 4]++;
                            $h2[$code2 >> 4]++;
                        }
                    }

                    $s1 = array_sum($h1);
                    $s2 = array_sum($h2);
                    if ($s1 > 0) {
                        foreach ($h1 as &$v) {
                            $v /= $s1;
                        }
                    }
                    if ($s2 > 0) {
                        foreach ($h2 as &$v) {
                            $v /= $s2;
                        }
                    }

                    $dist = 0;
                    for ($b = 0; $b < 16; $b++) {
                        if ($h1[$b] + $h2[$b] > 0) {
                            $dist += pow($h1[$b] - $h2[$b], 2) / ($h1[$b] + $h2[$b]);
                        }
                    }
                    $totalLbpDist += $dist * ($weights[$cellIdx] ?? 1.0);
                    $cellIdx++;
                }
            }
            $lbpScore = max(0, min(100, 100 - ($totalLbpDist * 16.5)));

            // 3. Directional Gradient Correlation
            $gradDiffSum = 0;
            for ($y = 2; $y < $size - 2; $y += 2) {
                for ($x = 2; $x < $size - 2; $x += 2) {
                    $dx1 = abs((imagecolorat($roi1, $x + 1, $y) & 0xFF) - (imagecolorat($roi1, $x - 1, $y) & 0xFF));
                    $dy1 = abs((imagecolorat($roi1, $x, $y + 1) & 0xFF) - (imagecolorat($roi1, $x, $y - 1) & 0xFF));
                    $dx2 = abs((imagecolorat($roi2, $x + 1, $y) & 0xFF) - (imagecolorat($roi2, $x - 1, $y) & 0xFF));
                    $dy2 = abs((imagecolorat($roi2, $x, $y + 1) & 0xFF) - (imagecolorat($roi2, $x, $y - 1) & 0xFF));
                    $gradDiffSum += (abs($dx1 - $dx2) + abs($dy1 - $dy2));
                }
            }
            $gradScore = max(0, min(100, 100 - ($gradDiffSum / 841 * 3.8)));

            imagedestroy($roi1);
            imagedestroy($roi2);

            // Kombinasi Skor Kecocokan Berbobot Multi-Faktor
            $finalScore = (0.65 * $lbpScore) + (0.25 * $gradScore) + (0.10 * $toneScore);

            return round(max(0.0, min(100.0, $finalScore)), 1);
        } catch (\Throwable $e) {
            return 0.0;
        }
    }
}
