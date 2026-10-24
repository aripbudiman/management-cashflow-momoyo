<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\WorkItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        /** @var WorkItem $item */
        $item = $this->route('workItem');

        return $user !== null && $item->resolveProject()->isVisibleTo($user);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'persen' => ['required', 'integer', 'min:0', 'max:100'],
            'catatan' => ['nullable', 'string', 'max:1000'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['image', 'max:8192'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            /** @var WorkItem $item */
            $item = $this->route('workItem');
            $progress = $this->input('persen');

            $status = $item->resolveProject()->status;
            if ($status === Project::STATUS_DRAFT) {
                $validator->errors()->add('persen', 'Progress baru bisa diisi setelah RAB dikirim ke klien.');

                return;
            }
            if ($status === Project::STATUS_RAB_DIKIRIM && ! $item->resolveProject()->hasIssuedPlacement()) {
                $validator->errors()->add('persen', 'Progress baru bisa diisi setelah surat penempatan kerja diterbitkan.');

                return;
            }
            if ($status === Project::STATUS_DIBATALKAN) {
                $validator->errors()->add('persen', 'Proyek sudah dibatalkan.');

                return;
            }

            if (is_numeric($progress) && (int) $progress < $item->progress && trim((string) $this->input('catatan')) === '') {
                $validator->errors()->add('catatan', 'Catatan wajib diisi kalau progress diturunkan.');
            }
            if (is_numeric($progress) && (int) $progress === $item->progress) {
                $validator->errors()->add('persen', 'Progress sama dengan sebelumnya, tidak ada yang diubah.');
            }
        }];
    }
}
