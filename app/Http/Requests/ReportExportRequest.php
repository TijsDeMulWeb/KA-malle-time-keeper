<?php

namespace App\Http\Requests;

use App\Models\SchoolYear;
use App\Models\User;
use App\Support\Report;
use App\Support\ReportPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->subject());
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'periode' => ['nullable', Rule::in(ReportPeriod::$filters)],
            'van' => ['nullable', 'date_format:Y-m-d'],
            'tot' => ['nullable', 'date_format:Y-m-d'],
            'jaar' => ['nullable', 'integer', 'exists:school_years,id'],
        ];
    }

    public function subject(): User
    {
        $user = $this->route('user');

        return $user instanceof User
            ? $user
            : $this->user();
    }

    public function report(): Report
    {
        $period = ReportPeriod::resolve(
            $this->subject(),
            $this->validated('periode') ?? 'year',
            $this->validated('van'),
            $this->validated('tot'),
            SchoolYear::whereKey($this->validated('jaar'))->first(),
        );

        return new Report($this->subject(), $period);
    }
}
