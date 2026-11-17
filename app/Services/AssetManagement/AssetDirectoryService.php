<?php

namespace App\Services\AssetManagement;

use App\Models\Master\Organization\Company;
use App\Models\Master\Organization\Department;
use App\Models\Master\Organization\Location;
use App\Models\Master\Product\Product;
use App\Models\Transaction\AssetManagement\Asset;
use App\Models\User;
use App\Services\BranchAccessService;
use App\Services\DepartmentAccessService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AssetDirectoryService
{
    public function __construct(
        private readonly DepartmentAccessService $departmentAccess,
        private readonly BranchAccessService $branchAccess,
    ) {}

    public const IMPORT_HEADERS = [
        'asset_number', 'product_code', 'company_code', 'department_code',
        'location_code', 'branch_code', 'custodian_email', 'serial_number',
        'acquisition_date', 'acquisition_cost', 'warranty_duration_months',
        'specifications', 'remarks',
    ];

    /** @return array<int, string> */
    public function relationships(): array
    {
        return [
            'product:id,code,name,category_id,type_id,brand_id,model_id',
            'product.category:id,name',
            'product.type:id,name',
            'product.brand:id,name',
            'product.model:id,name',
            'company:id,code,name',
            'department:id,code,name',
            'location:id,code,name,branch_id',
            'branch:id,code,name',
            'custodian:id,name,email',
            'registeredBy:id,name',
        ];
    }

    public function query(Request $request): Builder
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $query = $this->departmentAccess->scopeByDepartment(Asset::query(), $user, 'assets.department_id');
        $search = trim($request->string('search')->toString());
        $status = $request->string('status')->toString();

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('number', 'ilike', "%{$search}%")
                    ->orWhere('serial_number', 'ilike', "%{$search}%")
                    ->orWhereHas('product', fn (Builder $product) => $product->where('name', 'ilike', "%{$search}%")
                        ->orWhere('code', 'ilike', "%{$search}%"))
                    ->orWhereHas('company', fn (Builder $company) => $company->where('name', 'ilike', "%{$search}%"))
                    ->orWhereHas('department', fn (Builder $department) => $department->where('name', 'ilike', "%{$search}%"))
                    ->orWhereHas('location', fn (Builder $location) => $location->where('name', 'ilike', "%{$search}%"))
                    ->orWhereHas('custodian', fn (Builder $custodian) => $custodian->where('name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%"));
            });
        }

        if ($status !== '' && $status !== 'ALL') {
            $query->where('status', $status);
        }

        foreach (['company_id', 'department_id', 'location_id'] as $organizationFilter) {
            $organizationId = $request->string($organizationFilter)->toString();
            if ($organizationId !== '') {
                $query->where($organizationFilter, $organizationId);
            }
        }

        return $query;
    }

    public function escapeCsvCell(mixed $value): mixed
    {
        if (is_string($value) && preg_match('/^[\s]*[=+\-@]/u', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }

    public function import(UploadedFile $file, User $actor): int
    {
        $handle = fopen($file->getRealPath(), 'rb');
        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'File CSV tidak dapat dibaca.']);
        }

        $headers = fgetcsv($handle, 0, ',', '"', '\\');
        if (! is_array($headers)) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => 'File CSV kosong atau tidak memiliki header.']);
        }

        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
        $headers = array_map(fn ($header) => strtolower(trim((string) $header)), $headers);
        if (count($headers) !== count(array_unique($headers))) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => 'Header template tidak boleh berulang.']);
        }

        $missingHeaders = array_diff(self::IMPORT_HEADERS, $headers);
        if ($missingHeaders !== []) {
            fclose($handle);
            throw ValidationException::withMessages([
                'file' => 'Header template tidak lengkap: '.implode(', ', $missingHeaders).'.',
            ]);
        }

        $rows = [];
        $line = 1;
        while (($values = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $line++;
            if ($values === [null] || count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            if (count($rows) >= 5000) {
                fclose($handle);
                throw ValidationException::withMessages(['file' => 'Maksimal 5.000 baris aset dapat diimpor sekaligus.']);
            }

            if (count($values) !== count($headers)) {
                $rows[] = ['line' => $line, 'error' => 'Jumlah kolom tidak sesuai dengan header template.'];

                continue;
            }

            $rows[] = ['line' => $line, 'data' => array_combine($headers, $values)];
        }
        fclose($handle);

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'Tidak ada data aset untuk diimpor.']);
        }

        $codes = fn (string $field): array => collect($rows)
            ->filter(fn ($row) => isset($row['data']))
            ->pluck('data.'.$field)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $accessibleDepartmentIds = $this->departmentAccess->departmentIds($actor);
        $products = Product::query()->with(['category', 'type', 'brand', 'model', 'departments:id', 'branches:id'])
            ->whereHas('departments', fn (Builder $query) => $query->whereIn('mst_org_departments.id', $accessibleDepartmentIds))
            ->where('group', 'ASSET')->whereIn('code', $codes('product_code'))->get()->keyBy('code');
        $companies = Company::query()->whereIn('code', $codes('company_code'))->get()->keyBy('code');
        $departments = Department::query()->whereIn('code', $codes('department_code'))
            ->get()->keyBy(fn (Department $department) => $department->company_id.'|'.$department->code);
        $locations = Location::query()->with('branch')->whereIn('code', $codes('location_code'))
            ->get()->keyBy(fn (Location $location) => $location->company_id.'|'.$location->code);
        $users = User::query()->whereIn('email', $codes('custodian_email'))->get()->keyBy('email');

        $validatedRows = [];
        $errors = [];
        $numbers = [];
        $serialNumbers = [];

        foreach ($rows as $row) {
            $line = $row['line'];
            if (isset($row['error'])) {
                $errors[] = "Baris {$line}: {$row['error']}";

                continue;
            }

            $data = array_map(fn ($value) => trim((string) $value), $row['data']);
            $validator = Validator::make($data, [
                'asset_number' => ['required', 'string', 'max:100', 'regex:/^[A-Z0-9 .\\/$+%-]+$/'],
                'product_code' => ['required', 'string', 'max:100'],
                'company_code' => ['required', 'string', 'max:50'],
                'department_code' => ['required', 'string', 'max:50'],
                'location_code' => ['required', 'string', 'max:50'],
                'branch_code' => ['nullable', 'string', 'max:50'],
                'custodian_email' => ['nullable', 'email', 'max:255'],
                'serial_number' => ['nullable', 'string', 'max:150'],
                'acquisition_date' => ['required', 'date_format:Y-m-d'],
                'acquisition_cost' => ['nullable', 'numeric', 'min:0'],
                'warranty_duration_months' => ['nullable', 'integer', 'min:0', 'max:65535'],
                'specifications' => ['nullable', 'json'],
                'remarks' => ['nullable', 'string', 'max:5000'],
            ]);

            if ($validator->fails()) {
                $errors[] = "Baris {$line}: ".implode(' ', $validator->errors()->all());

                continue;
            }

            $product = $products->get($data['product_code']);
            $company = $companies->get($data['company_code']);
            if ($product === null || $product->category === null || $product->type === null || $product->brand === null) {
                $errors[] = "Baris {$line}: Produk ASSET tidak ditemukan atau data kategori, tipe, dan merek belum lengkap.";

                continue;
            }
            if ($company === null) {
                $errors[] = "Baris {$line}: Perusahaan tidak ditemukan.";

                continue;
            }

            $department = $departments->get($company->id.'|'.$data['department_code']);
            $location = $locations->get($company->id.'|'.$data['location_code']);
            if ($department === null || $location === null) {
                $errors[] = "Baris {$line}: Departemen atau lokasi tidak ditemukan pada perusahaan tersebut.";

                continue;
            }
            if (! $this->departmentAccess->canAccess($actor, $department->id)) {
                $errors[] = "Baris {$line}: Anda tidak memiliki akses ke department aset tersebut.";

                continue;
            }
            if (! $product->departments->contains('id', $department->id)) {
                $errors[] = "Baris {$line}: Produk tidak tersedia untuk department aset tersebut.";

                continue;
            }
            if (! $product->branches->contains('id', $location->branch_id)) {
                $errors[] = "Baris {$line}: Produk tidak tersedia untuk branch lokasi aset tersebut.";

                continue;
            }
            if ($data['branch_code'] !== '' && $location->branch?->code !== $data['branch_code']) {
                $errors[] = "Baris {$line}: Cabang tidak sesuai dengan cabang lokasi.";

                continue;
            }

            $custodian = $data['custodian_email'] !== '' ? $users->get($data['custodian_email']) : null;
            if ($data['custodian_email'] !== '' && (
                $custodian === null || ! $custodian->is_active || ($custodian->company_id !== null && $custodian->company_id !== $company->id)
            )) {
                $errors[] = "Baris {$line}: Email penanggung jawab tidak aktif atau bukan dari perusahaan aset.";

                continue;
            }

            if ($custodian !== null
                && ! $custodian->hasGlobalDepartmentAccess()
                && $custodian->department_id !== $department->id
                && ! $custodian->additionalDepartments()->whereKey($department->id)->exists()) {
                $errors[] = "Baris {$line}: Penanggung jawab tidak memiliki akses ke department aset.";

                continue;
            }

            if ($custodian !== null && ! $this->branchAccess->canAccess($custodian, $location->branch_id)) {
                $errors[] = "Baris {$line}: Penanggung jawab tidak memiliki akses ke branch aset.";

                continue;
            }

            if (isset($numbers[$data['asset_number']])) {
                $errors[] = "Baris {$line}: Nomor aset duplikat di dalam file.";

                continue;
            }
            $numbers[$data['asset_number']] = true;

            if ($data['serial_number'] !== '') {
                if (isset($serialNumbers[$data['serial_number']])) {
                    $errors[] = "Baris {$line}: Serial number duplikat di dalam file.";

                    continue;
                }
                $serialNumbers[$data['serial_number']] = true;
            }

            $specifications = $data['specifications'] === '' ? [] : json_decode($data['specifications'], true);
            if (! is_array($specifications)) {
                $errors[] = "Baris {$line}: Spesifikasi harus berupa JSON object, contoh {\"RAM\":\"16GB\"}.";

                continue;
            }

            $validatedRows[] = [
                'line' => $line,
                'data' => $data,
                'product' => $product,
                'company' => $company,
                'department' => $department,
                'location' => $location,
                'custodian' => $custodian,
                'specifications' => $specifications,
            ];
        }

        $existingNumbers = Asset::query()->whereIn('number', array_keys($numbers))->pluck('number')->all();
        foreach ($validatedRows as $row) {
            if (in_array($row['data']['asset_number'], $existingNumbers, true)) {
                $errors[] = "Baris {$row['line']}: Nomor aset sudah terdaftar.";
            }
        }

        $existingSerials = Asset::query()->whereIn('serial_number', array_keys($serialNumbers))->pluck('serial_number')->all();
        foreach ($validatedRows as $row) {
            if ($row['data']['serial_number'] !== '' && in_array($row['data']['serial_number'], $existingSerials, true)) {
                $errors[] = "Baris {$row['line']}: Serial number sudah terdaftar.";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['file' => implode("\n", $errors)]);
        }

        DB::transaction(function () use ($validatedRows, $actor): void {
            foreach ($validatedRows as $row) {
                $data = $row['data'];
                $warrantyMonths = $data['warranty_duration_months'] !== '' ? (int) $data['warranty_duration_months'] : null;
                $warrantyStart = $warrantyMonths !== null && $warrantyMonths > 0
                    ? CarbonImmutable::parse($data['acquisition_date'])
                    : null;

                Asset::query()->create([
                    'number' => $data['asset_number'],
                    'registration_method' => Asset::METHOD_EXISTING,
                    'status' => Asset::STATUS_ACTIVE,
                    'product_id' => $row['product']->id,
                    'category_id' => $row['product']->category_id,
                    'type_id' => $row['product']->type_id,
                    'brand_id' => $row['product']->brand_id,
                    'model_id' => $row['product']->model_id,
                    'company_id' => $row['company']->id,
                    'department_id' => $row['department']->id,
                    'location_id' => $row['location']->id,
                    'branch_id' => $row['location']->branch_id,
                    'custodian_id' => $row['custodian']?->id,
                    'serial_number' => $data['serial_number'] !== '' ? $data['serial_number'] : null,
                    'specifications' => $row['specifications'],
                    'warranty_duration_months' => $warrantyMonths,
                    'warranty_start_date' => $warrantyStart?->toDateString(),
                    'warranty_end_date' => $warrantyStart?->addMonths($warrantyMonths)->subDay()->toDateString(),
                    'acquisition_date' => $data['acquisition_date'],
                    'acquisition_cost' => $data['acquisition_cost'] !== '' ? $data['acquisition_cost'] : null,
                    'remarks' => $data['remarks'] !== '' ? $data['remarks'] : null,
                    'registered_by' => $actor->id,
                ]);
            }
        });

        return count($validatedRows);
    }
}
