<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class CopyScheduleWeekRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->managesBranch((int) $this->input('branch_id'));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'week' => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function weekStart(): Carbon
    {
        return Carbon::parse($this->input('week'))->startOfWeek(Carbon::MONDAY);
    }
}
