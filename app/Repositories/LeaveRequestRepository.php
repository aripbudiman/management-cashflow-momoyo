<?php

namespace App\Repositories;

use App\Models\LeaveRequest;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class LeaveRequestRepository
{
    /**
     * @param  array{search?: mixed, branch_id?: mixed, status?: mixed, type?: mixed}  $filters
     */
    public function paginateForAdmin(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return LeaveRequest::query()
            ->with(['employee:id,full_name,nik,user_id', 'employee.user:id,role_id', 'employee.user.role:id,name', 'branch:id,branch_name', 'approver:id,name'])
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->whereHas('employee', fn ($e) => $e
                ->where('full_name', 'ilike', "%{$v}%")
                ->orWhere('nik', 'ilike', "%{$v}%")))
            ->when($filters['branch_id'] ?? null, fn ($q, $v) => $q->where('branch_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->orderByRaw("case when status = 'pending' then 0 else 1 end")
            ->latest('start_date')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return Collection<int, LeaveRequest>
     */
    public function forEmployee(int $employeeId): Collection
    {
        return LeaveRequest::query()
            ->with(['branch:id,branch_name', 'approver:id,name'])
            ->where('employee_id', $employeeId)
            ->latest('start_date')
            ->limit(50)
            ->get();
    }

    public function hasOverlap(int $employeeId, string $start, string $end): bool
    {
        return LeaveRequest::query()
            ->where('employee_id', $employeeId)
            ->whereIn('status', ['pending', 'approved'])
            ->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start)
            ->exists();
    }

    /**
     * Total hari cuti tahunan pada tahun tertentu.
     *
     * @param  array<int, string>  $statuses
     */
    public function annualLeaveDays(int $employeeId, int $year, array $statuses, ?int $exceptId = null): int
    {
        return (int) LeaveRequest::query()
            ->where('employee_id', $employeeId)
            ->where('type', 'cuti_tahunan')
            ->whereIn('status', $statuses)
            ->whereYear('start_date', $year)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->sum('total_days');
    }

    /**
     * @return Collection<int, LeaveRequest>
     */
    public function approvedForEmployeeBetween(int $employeeId, CarbonInterface $start, CarbonInterface $end): Collection
    {
        return LeaveRequest::query()
            ->where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->get();
    }

    /**
     * @return Collection<int, LeaveRequest>
     */
    public function approvedForBranchBetween(int $branchId, CarbonInterface $start, CarbonInterface $end): Collection
    {
        return LeaveRequest::query()
            ->where('branch_id', $branchId)
            ->where('status', 'approved')
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->get();
    }

    public function approvedOnDate(int $employeeId, string $date): ?LeaveRequest
    {
        return LeaveRequest::query()
            ->where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();
    }
}
