<?php

namespace App\Modules\Project\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Modules\Project\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function __construct(
        protected ProjectService $projectService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->projectService->listProjects($request);

        return response()->json([
            'status' => 'success',
            'data' => $result,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'client_name' => ['required', 'string', 'max:255'],
            'client_email' => ['nullable', 'email'],
            'client_phone' => ['nullable', 'string', 'max:30'],
            'client_company' => ['nullable', 'string', 'max:255'],
            'client_gstin' => ['nullable', 'string', 'max:25'],
            'client_address' => ['nullable', 'string'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'project_manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'string', 'max:35'],
            'priority' => ['nullable', 'string', 'in:low,medium,high,urgent'],
            'billing_type' => ['nullable', 'string', 'in:fixed_cost,time_and_materials,retainer,internal'],
            'estimated_budget' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date'],
            'milestones' => ['nullable', 'array'],
            'milestones.*.title' => ['required', 'string', 'max:255'],
            'milestones.*.description' => ['nullable', 'string'],
            'milestones.*.due_date' => ['nullable', 'date'],
            'milestones.*.amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $project = $this->projectService->createProject($validated, $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Project created successfully.',
            'data' => $project,
        ], 201);
    }

    public function show(Project $project): JsonResponse
    {
        $project->load(['department', 'projectManager', 'createdBy', 'milestones.tasks', 'tasks', 'quotations', 'invoices']);

        return response()->json([
            'status' => 'success',
            'data' => $project,
        ]);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'client_email' => ['nullable', 'email'],
            'client_phone' => ['nullable', 'string', 'max:30'],
            'client_company' => ['nullable', 'string', 'max:255'],
            'client_gstin' => ['nullable', 'string', 'max:25'],
            'client_address' => ['nullable', 'string'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'project_manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'string', 'max:35'],
            'priority' => ['nullable', 'string', 'in:low,medium,high,urgent'],
            'billing_type' => ['nullable', 'string', 'in:fixed_cost,time_and_materials,retainer,internal'],
            'estimated_budget' => ['nullable', 'numeric', 'min:0'],
            'actual_cost' => ['nullable', 'numeric', 'min:0'],
            'progress_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $updated = $this->projectService->updateProject($project, $validated, $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Project updated successfully.',
            'data' => $updated,
        ]);
    }

    public function destroy(Request $request, Project $project): JsonResponse
    {
        $this->projectService->deleteProject($project, $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Project archived successfully.',
        ]);
    }

    public function addMilestone(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $milestone = $this->projectService->addMilestone($project, $validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Milestone added.',
            'data' => $milestone,
        ], 201);
    }

    public function addTask(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'milestone_id' => ['nullable', 'integer', 'exists:project_milestones,id'],
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'priority' => ['nullable', 'string', 'in:low,medium,high,urgent'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
        ]);

        $task = $this->projectService->addTask($project, $validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Task added.',
            'data' => $task,
        ], 201);
    }

    public function updateTaskStatus(Request $request, ProjectTask $task): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:todo,in_progress,review,done'],
        ]);

        $updated = $this->projectService->updateTaskStatus($task, $validated['status']);

        return response()->json([
            'status' => 'success',
            'message' => 'Task status updated.',
            'data' => $updated,
        ]);
    }
}
