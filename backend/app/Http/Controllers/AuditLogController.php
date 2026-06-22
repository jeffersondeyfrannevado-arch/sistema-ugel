<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::query()->with('actor:id,name,email');

        if ($request->filled('q')) {
            $query->where(function ($builder) use ($request) {
                $builder
                    ->where('action', 'like', '%' . $request->query('q') . '%')
                    ->orWhere('target_label', 'like', '%' . $request->query('q') . '%');
            });
        }

        if ($request->filled('action')) {
            $query->where('action', $request->query('action'));
        }

        if ($request->filled('actor_id')) {
            $query->where('actor_id', $request->integer('actor_id'));
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->query('to'));
        }

        return response()->json([
            'success' => true,
            'logs' => $query->latest()->limit(200)->get(),
        ]);
    }
}
