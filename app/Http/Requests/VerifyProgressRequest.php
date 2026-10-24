<?php

namespace App\Http\Requests;

use App\Models\ProgressLog;
use Illuminate\Foundation\Http\FormRequest;

class VerifyProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        /** @var ProgressLog $log */
        $log = $this->route('progressLog');

        return $user !== null
            && ($user->isAdminPusat() || $user->isKepalaToko())
            && $log->workItem->resolveProject()->isVisibleTo($user);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }
}
