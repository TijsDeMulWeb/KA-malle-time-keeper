<?php

namespace App\Http\Requests;

use App\Models\TimeEntry;
use App\Support\Report;
use App\Support\ReportPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', TimeEntry::class);
    }

    public function rules(): array
    {
        return [
            'periode' => ['nullable', Rule::in(ReportPeriod::$filters)],
            'van' => ['nullable', 'date_format:Y-m-d'],
            'tot' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function report(): Report
    {
        $period = ReportPeriod::resolve(
            $this->user(),
            $this->validated('periode') ?? 'course',
            $this->validated('van'),
            $this->validated('tot'),
        );

        return new Report($this->user(), $period);
    }
}
