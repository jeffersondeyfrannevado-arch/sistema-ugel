<?php

namespace App\Http\Controllers;

use App\Models\SystemContent;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminContentController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {}

    public function index(Request $request)
    {
        $query = SystemContent::query()->with(['creator:id,name,email', 'reviewer:id,name,email']);

        if ($request->filled('q')) {
            $query->where(function ($builder) use ($request) {
                $builder
                    ->where('title', 'like', '%' . $request->query('q') . '%')
                    ->orWhere('body', 'like', '%' . $request->query('q') . '%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        return response()->json([
            'success' => true,
            'contenidos' => $query->latest()->limit(200)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:5000',
            'type' => 'required|string|max:50',
            'status' => 'required|in:draft,published,flagged,removed',
        ]);

        $content = SystemContent::create([
            'title' => strip_tags($validated['title']),
            'body' => strip_tags($validated['body']),
            'type' => $validated['type'],
            'status' => $validated['status'],
            'is_flagged' => $validated['status'] === 'flagged',
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        $this->auditLogService->record($request->user(), 'admin.content.created', $content, [], $request);

        return response()->json([
            'success' => true,
            'contenido' => $content->load(['creator:id,name,email', 'reviewer:id,name,email']),
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, SystemContent $content)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:5000',
            'type' => 'required|string|max:50',
            'status' => 'required|in:draft,published,flagged,removed',
        ]);

        $content->fill([
            'title' => strip_tags($validated['title']),
            'body' => strip_tags($validated['body']),
            'type' => $validated['type'],
            'status' => $validated['status'],
            'is_flagged' => $validated['status'] === 'flagged',
            'updated_by' => $request->user()?->id,
        ])->save();

        $this->auditLogService->record($request->user(), 'admin.content.updated', $content, [], $request);

        return response()->json([
            'success' => true,
            'contenido' => $content->fresh()->load(['creator:id,name,email', 'reviewer:id,name,email']),
        ]);
    }

    public function moderate(Request $request, SystemContent $content)
    {
        $validated = $request->validate([
            'status' => 'required|in:published,flagged,removed',
            'flagged_reason' => 'nullable|string|max:255',
            'review_notes' => 'nullable|string|max:1000',
        ]);

        $content->fill([
            'status' => $validated['status'],
            'is_flagged' => $validated['status'] === 'flagged',
            'flagged_reason' => $validated['flagged_reason'] ?? null,
            'review_notes' => $validated['review_notes'] ?? null,
            'reviewed_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ])->save();

        $this->auditLogService->record($request->user(), 'admin.content.moderated', $content, [
            'status' => $content->status,
        ], $request);

        return response()->json([
            'success' => true,
            'contenido' => $content->fresh()->load(['creator:id,name,email', 'reviewer:id,name,email']),
        ]);
    }

    public function destroy(Request $request, SystemContent $content)
    {
        $label = $content->title;
        $content->delete();

        $this->auditLogService->record($request->user(), 'admin.content.deleted', $label, [], $request);

        return response()->json([
            'success' => true,
            'mensaje' => 'Contenido eliminado correctamente.',
        ]);
    }
}
