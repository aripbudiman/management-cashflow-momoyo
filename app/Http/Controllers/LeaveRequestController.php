<?php

namespace App\Http\Controllers;

use App\Http\Requests\DecideLeaveRequest;
use App\Http\Requests\StoreLeaveRequest;
use App\Models\Branch;
use App\Models\LeaveRequest;
use App\Repositories\LeaveRequestRepository;
use App\Services\LeaveRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaveRequestController extends Controller
{
    public function __construct(
        private readonly LeaveRequestService $service,
        private readonly LeaveRequestRepository $leaves,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $employee = $user->employee;
        $isAdmin = $user->isAdminPusat();
        $canReview = $isAdmin || $user->isKepalaToko();

        $filters = $request->only(['search', 'branch_id', 'status', 'type']);
        if ($user->isKepalaToko()) {
            // Kepala Toko hanya melihat pengajuan cabangnya
            $filters['branch_id'] = $employee?->current_branch_id;
        }

        $reviewList = null;
        if ($canReview) {
            $reviewList = $this->leaves->paginateForAdmin($filters);
            $reviewList->getCollection()->transform(function (LeaveRequest $leave) use ($user) {
                $leave->setAttribute('can_decide', $leave->status === 'pending' && $leave->isDecidableBy($user));

                return $leave;
            });
        }

        return Inertia::render('leaves/Index', [
            'isAdmin' => $canReview,
            'isMonitorOnly' => $isAdmin,
            'typeLabels' => LeaveRequestService::TYPE_LABELS,
            'myLeaves' => $employee ? $this->leaves->forEmployee($employee->id) : [],
            'quota' => $employee ? $this->service->quota($employee) : null,
            'hasEmployee' => (bool) $employee,
            'adminLeaves' => $reviewList,
            'branches' => $isAdmin ? Branch::where('is_active', true)->orderBy('branch_name')->get(['id', 'branch_name']) : [],
            'filters' => $request->only(['search', 'branch_id', 'status', 'type']),
        ]);
    }

    public function store(StoreLeaveRequest $request): RedirectResponse
    {
        $this->service->submit(
            $request->user()->employee,
            $request->safe()->only(['type', 'start_date', 'end_date', 'reason']),
            $request->file('attachment'),
        );

        $approver = $request->user()->isKepalaToko() ? 'Admin Pusat' : 'Kepala Toko';

        return back()->with('success', "Pengajuan berhasil dikirim dan menunggu persetujuan {$approver}.");
    }

    public function approve(DecideLeaveRequest $request, LeaveRequest $leave): RedirectResponse
    {
        $this->service->approve($leave, $request->user(), $request->validated('approval_notes'));

        return back()->with('success', 'Pengajuan disetujui. Jadwal karyawan pada tanggal tersebut telah dihapus.');
    }

    public function reject(DecideLeaveRequest $request, LeaveRequest $leave): RedirectResponse
    {
        $this->service->reject($leave, $request->user(), $request->validated('approval_notes'));

        return back()->with('success', 'Pengajuan ditolak.');
    }

    public function destroy(Request $request, LeaveRequest $leave): RedirectResponse
    {
        abort_unless($leave->employee_id === $request->user()->employee?->id, 403);

        $this->service->cancel($leave);

        return back()->with('success', 'Pengajuan dibatalkan.');
    }
}
