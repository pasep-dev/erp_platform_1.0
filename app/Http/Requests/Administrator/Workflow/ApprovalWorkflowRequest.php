<?php

namespace App\Http\Requests\Administrator\Workflow;

use App\Models\Administrator\Workflow\ApprovalWorkflow;
use App\Models\Master\Organization\Department;
use App\Models\User;
use App\Services\DepartmentAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApprovalWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            $this->isMethod('POST')
                ? 'approval.workflow.create'
                : 'approval.workflow.update'
        ) ?? false;
    }

    public function rules(): array
    {
        return [
            'module' => [
                'required',
                'string',
                'max:100',
                Rule::in([
                    'PURCHASE_REQUEST',
                    'INVENTORY_REQUEST',
                    'INVENTORY_OPENING_BALANCE',
                    'ASSET_TRANSFER',
                    'ASSET_DISPOSAL',
                    'ASSET_ADJUSTMENT',
                ]),
            ],

            'company_id' => [
                'required',
                'string',
                'size:26',
                'exists:mst_org_companies,id',
            ],

            'department_id' => [
                'required',
                'string',
                'size:26',
                'exists:mst_org_departments,id',
                Rule::in(app(DepartmentAccessService::class)->departmentIds($this->user())),
                function ($attribute, $value, $fail) {
                    $companyId = $this->input('company_id');

                    $exists = Department::query()
                        ->whereKey($value)
                        ->where('company_id', $companyId)
                        ->where('is_active', true)
                        ->exists();

                    if (! $exists) {
                        $fail(
                            'Department tidak sesuai dengan Company yang dipilih.'
                        );
                    }
                },
            ],

            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'is_active' => [
                'required',
                'boolean',
            ],

            'levels' => [
                'required',
                'array',
                'min:1',
            ],

            'levels.*.level' => [
                'required',
                'integer',
                'min:1',
            ],

            'levels.*.approver_user_id' => [
                'required',
                'integer',
                'exists:users,id',

                function ($attribute, $value, $fail) {
                    $userExists = User::query()
                        ->whereKey($value)
                        ->where('is_active', true)
                        ->exists();

                    if (! $userExists) {
                        $fail('Approver harus merupakan user aktif.');
                    }
                },
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $levels = collect($this->input('levels', []));

            /*
            |--------------------------------------------------------------------------
            | Level harus berurutan
            |--------------------------------------------------------------------------
            */

            $levelNumbers = $levels
                ->pluck('level')
                ->map(fn ($level) => (int) $level)
                ->values();

            if (
                $levelNumbers->count() !==
                $levelNumbers->unique()->count()
            ) {
                $validator->errors()->add(
                    'levels',
                    'Level approval tidak boleh duplikat.'
                );
            }

            $expected = range(1, $levelNumbers->count());

            if ($levelNumbers->sort()->values()->all() !== $expected) {
                $validator->errors()->add(
                    'levels',
                    'Level approval harus berurutan mulai dari Level 1.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Satu user tidak boleh menjadi dua level dalam workflow yang sama
            |--------------------------------------------------------------------------
            */

            $approvers = $levels
                ->pluck('approver_user_id')
                ->map(fn ($id) => (int) $id);

            if (
                $approvers->count() !==
                $approvers->unique()->count()
            ) {
                $validator->errors()->add(
                    'levels',
                    'User approver tidak boleh digunakan lebih dari satu level.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Hanya satu workflow aktif untuk:
            | Module + Company + Department
            |--------------------------------------------------------------------------
            */

            if ($this->boolean('is_active')) {
                $query = ApprovalWorkflow::query()
                    ->where('module', $this->input('module'))
                    ->where('company_id', $this->input('company_id'))
                    ->where('department_id', $this->input('department_id'))
                    ->where('is_active', true);

                if ($workflowId = $this->route('approvalWorkflow')?->id) {
                    $query->where('id', '!=', $workflowId);
                }

                if ($query->exists()) {
                    $validator->errors()->add(
                        'module',
                        'Sudah terdapat workflow aktif untuk Module, Company, dan Department tersebut.'
                    );
                }
            }
        });
    }
}
