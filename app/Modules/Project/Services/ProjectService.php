<?php

namespace App\Modules\Project\Services;

use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\ProjectTask;
use App\Models\User;
use App\Modules\Audit\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ProjectService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * List projects with dynamic stage filters and summary statistics.
     *
     * @return array<string, mixed>
     */
    public function listProjects(Request $request): array
    {
        $query = Project::with([
            'department',
            'projectManager',
            'createdBy',
            'milestones',
            'tasks',
            'invoices',
            'quotations',
        ])->latest('id');

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority') && $request->priority !== 'all') {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('department_id') && $request->department_id !== 'all') {
            $query->where('department_id', $request->department_id);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('project_code', 'like', "%{$search}%")
                    ->orWhere('client_name', 'like', "%{$search}%")
                    ->orWhere('client_company', 'like', "%{$search}%");
            });
        }

        $projects = $query->get();

        // Calculate statistics across all projects
        $allProjects = Project::all();

        $stats = [
            'total_projects' => $allProjects->count(),
            'active_in_progress' => $allProjects->where('status', 'in_progress')->count(),
            'in_review' => $allProjects->where('status', 'in_review')->count(),
            'planning' => $allProjects->where('status', 'planning')->count(),
            'completed' => $allProjects->where('status', 'completed')->count(),
            'on_hold' => $allProjects->where('status', 'on_hold')->count(),
            'total_estimated_budget' => round($allProjects->sum('estimated_budget'), 2),
            'total_actual_cost' => round($allProjects->sum('actual_cost'), 2),
        ];

        return [
            'stats' => $stats,
            'count' => $projects->count(),
            'records' => $projects,
        ];
    }

    /**
     * Create a new project.
     *
     * @param  array<string, mixed>  $data
     */
    public function createProject(array $data, User $actor): Project
    {
        $data['project_code'] = $data['project_code'] ?? Project::generateCode();
        $data['created_by_user_id'] = $actor->id;
        $data['status'] = $data['status'] ?? 'planning';

        $milestones = $data['milestones'] ?? [];
        unset($data['milestones']);

        $project = Project::create($data);

        if (! empty($milestones)) {
            foreach ($milestones as $m) {
                $project->milestones()->create([
                    'title' => $m['title'],
                    'description' => $m['description'] ?? null,
                    'due_date' => $m['due_date'] ?? null,
                    'amount' => $m['amount'] ?? 0,
                    'status' => 'pending',
                ]);
            }
        }

        $this->auditService->log(
            'project.created',
            $project,
            null,
            $project->toArray(),
            $actor
        );

        return $project->load(['department', 'projectManager', 'createdBy', 'milestones', 'tasks']);
    }

    /**
     * Update an existing project.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateProject(Project $project, array $data, User $actor): Project
    {
        $old = $project->toArray();

        if (isset($data['status']) && $data['status'] === 'completed' && $project->status !== 'completed') {
            $data['completed_at'] = Carbon::now();
            $data['progress_percentage'] = 100;
        }

        $project->update($data);

        $this->auditService->log(
            'project.updated',
            $project,
            $old,
            $project->toArray(),
            $actor
        );

        return $project->load(['department', 'projectManager', 'createdBy', 'milestones.tasks', 'tasks', 'quotations', 'invoices']);
    }

    /**
     * Delete a project.
     */
    public function deleteProject(Project $project, User $actor): bool
    {
        $old = $project->toArray();
        $project->delete();

        $this->auditService->log(
            'project.deleted',
            $project,
            $old,
            null,
            $actor
        );

        return true;
    }

    /**
     * Add milestone to project.
     *
     * @param  array<string, mixed>  $data
     */
    public function addMilestone(Project $project, array $data): ProjectMilestone
    {
        return $project->milestones()->create($data);
    }

    /**
     * Add task to project.
     *
     * @param  array<string, mixed>  $data
     */
    public function addTask(Project $project, array $data): ProjectTask
    {
        return $project->tasks()->create($data);
    }

    /**
     * Update task status.
     */
    public function updateTaskStatus(ProjectTask $task, string $status): ProjectTask
    {
        $task->update(['status' => $status]);

        return $task;
    }
}
