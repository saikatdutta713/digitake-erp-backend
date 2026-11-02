<?php

namespace App\Modules\Employee\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeBankDetailResource;
use App\Http\Resources\EmployeeDetailResource;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use App\Models\User;
use App\Modules\Employee\Requests\CreateEmployeeRequest;
use App\Modules\Employee\Requests\UpdateBankDetailRequest;
use App\Modules\Employee\Requests\UpdateEmployeeRequest;
use App\Modules\Employee\Requests\UpdateEmployeeStatusRequest;
use App\Modules\Employee\Services\EmployeeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EmployeeController extends Controller
{
    public function __construct(
        protected EmployeeService $employeeService
    ) {}

    /**
     * List employees with query scoping for department managers.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Employee::class);

        /** @var User $actor */
        $actor = $request->user();

        $query = Employee::with(['department', 'designation', 'reportingManager']);

        // Scope by department if the actor is a Department Manager without higher HR/Admin privileges
        if ($actor->hasRole('Department Manager') && ! $actor->hasAnyRole(['Super Admin', 'Admin', 'HR Manager'])) {
            $deptId = $actor->employee?->managedDepartment?->id ?? $actor->employee?->department_id;
            $query->where('department_id', $deptId);
        } elseif ($request->filled('department_id')) {
            $query->where('department_id', $request->input('department_id'));
        }

        if ($request->filled('status')) {
            $query->where('employment_status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $term = '%'.$request->input('search').'%';
            $query->where(function ($q) use ($term) {
                $q->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('employee_code', 'like', $term)
                    ->orWhere('official_email', 'like', $term);
            });
        }

        $employees = $query->orderBy('first_name')->paginate($request->integer('per_page', 20));

        return ApiResponse::success(
            EmployeeResource::collection($employees),
            'Employees retrieved.',
            200,
            [
                'current_page' => $employees->currentPage(),
                'last_page' => $employees->lastPage(),
                'total' => $employees->total(),
            ]
        );
    }

    /**
     * Store new employee.
     */
    public function store(CreateEmployeeRequest $request): JsonResponse
    {
        Gate::authorize('create', Employee::class);

        /** @var User $actor */
        $actor = $request->user();
        $employee = $this->employeeService->create($request->validated(), $actor);
        $employee->load(['department', 'designation', 'reportingManager']);

        return ApiResponse::success(new EmployeeDetailResource($employee), 'Employee onboarded successfully.', 201);
    }

    /**
     * View employee detailed profile.
     */
    public function show(Employee $employee): JsonResponse
    {
        Gate::authorize('view', $employee);
        $employee->load(['department', 'designation', 'reportingManager', 'statusHistory']);

        return ApiResponse::success(new EmployeeDetailResource($employee), 'Employee details retrieved.');
    }

    /**
     * Update employee.
     */
    public function update(UpdateEmployeeRequest $request, Employee $employee): JsonResponse
    {
        Gate::authorize('update', $employee);

        /** @var User $actor */
        $actor = $request->user();
        $updated = $this->employeeService->update($employee, $request->validated(), $actor);
        $updated->load(['department', 'designation', 'reportingManager']);

        return ApiResponse::success(new EmployeeDetailResource($updated), 'Employee updated successfully.');
    }

    /**
     * Update employment status (archive / resign / terminate).
     */
    public function updateStatus(UpdateEmployeeStatusRequest $request, Employee $employee): JsonResponse
    {
        Gate::authorize('archive', $employee);

        /** @var User $actor */
        $actor = $request->user();
        $this->employeeService->updateStatus(
            $employee,
            $request->input('status'),
            $request->input('effective_date'),
            $request->input('reason'),
            $actor
        );

        $employee->load('statusHistory');

        return ApiResponse::success(new EmployeeDetailResource($employee), 'Employee status updated successfully.');
    }

    /**
     * View confidential bank details.
     */
    public function getBankDetails(Employee $employee): JsonResponse
    {
        Gate::authorize('viewBank', $employee);

        $bank = $employee->bankDetails;

        if (! $bank) {
            return ApiResponse::error('NO_BANK_DETAILS', 'No bank details recorded for this employee.', 404);
        }

        return ApiResponse::success(new EmployeeBankDetailResource($bank), 'Bank details retrieved.');
    }

    /**
     * Update confidential bank details.
     */
    public function updateBankDetails(UpdateBankDetailRequest $request, Employee $employee): JsonResponse
    {
        Gate::authorize('updateBank', $employee);

        /** @var User $actor */
        $actor = $request->user();
        $bank = $this->employeeService->updateBankDetails($employee, $request->validated(), $actor);

        return ApiResponse::success(new EmployeeBankDetailResource($bank), 'Bank details updated successfully.');
    }
}
