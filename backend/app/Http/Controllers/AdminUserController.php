<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogService;
use App\Support\AdminPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class AdminUserController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {}

    public function index()
    {
        $query = User::query();
        $search = request('q');
        $role = request('role');
        $status = request('status');
        $mfa = request('mfa');

        if ($search) {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        if ($role) {
            $query->where('role', AdminPermissions::normalizeRole($role));
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        } elseif ($status === 'locked') {
            $query->whereNotNull('locked_until')->where('locked_until', '>', now());
        }

        if ($mfa === 'enabled') {
            $query->where('mfa_enabled', true);
        } elseif ($mfa === 'disabled') {
            $query->where('mfa_enabled', false);
        }

        return response()->json([
            'success' => true,
            'usuarios' => $query
                ->orderByRaw("CASE WHEN role = 'super_admin' THEN 0 WHEN role = 'sub_admin' THEN 1 WHEN role = 'custom' THEN 2 ELSE 3 END")
                ->orderBy('name')
                ->get()
                ->map(fn (User $user) => $this->serializeUser($user)),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => ['required', Rule::in(AdminPermissions::roles())],
            'is_active' => 'nullable|boolean',
            'mfa_enabled' => 'nullable|boolean',
            'custom_permissions' => 'nullable|array',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => AdminPermissions::normalizeRole($validated['role']),
            'is_active' => $validated['is_active'] ?? true,
            'mfa_enabled' => $validated['mfa_enabled'] ?? false,
            'custom_permissions' => array_values(array_unique($validated['custom_permissions'] ?? [])),
        ]);

        $this->auditLogService->record($request->user(), 'admin.users.created', $user, [
            'role' => $user->role,
        ], $request);

        return response()->json([
            'success' => true,
            'mensaje' => 'Usuario creado correctamente.',
            'usuario' => $this->serializeUser($user),
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => 'nullable|string|min:8',
            'role' => ['required', Rule::in(AdminPermissions::roles())],
            'is_active' => 'required|boolean',
            'mfa_enabled' => 'required|boolean',
            'custom_permissions' => 'nullable|array',
        ]);

        $currentUser = $request->user();
        $targetRole = AdminPermissions::normalizeRole($validated['role']);

        if ($currentUser->id === $user->id) {
            if ($targetRole !== User::ROLE_SUPER_ADMIN) {
                return response()->json([
                    'success' => false,
                    'mensaje' => 'No puedes quitarte el rol principal de administrador a ti mismo.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if (!$validated['is_active']) {
                return response()->json([
                    'success' => false,
                    'mensaje' => 'No puedes desactivar tu propia cuenta.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $targetRole,
            'is_active' => $validated['is_active'],
            'mfa_enabled' => $validated['mfa_enabled'],
            'custom_permissions' => array_values(array_unique($validated['custom_permissions'] ?? [])),
        ]);

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
            $user->tokens()->delete();
        }

        $user->save();

        if (!$user->is_active) {
            $user->tokens()->delete();
        }

        $this->auditLogService->record($request->user(), 'admin.users.updated', $user, [
            'role' => $user->role,
            'is_active' => $user->is_active,
            'mfa_enabled' => $user->mfa_enabled,
        ], $request);

        return response()->json([
            'success' => true,
            'mensaje' => 'Usuario actualizado correctamente.',
            'usuario' => $this->serializeUser($user->fresh()),
        ]);
    }

    public function toggleStatus(Request $request, User $user)
    {
        if ($request->user()->id === $user->id) {
            return response()->json([
                'success' => false,
                'mensaje' => 'No puedes desactivar tu propia cuenta.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->is_active = !$user->is_active;
        $user->save();

        if (!$user->is_active) {
            $user->tokens()->delete();
        }

        $this->auditLogService->record($request->user(), 'admin.users.toggled_status', $user, [
            'is_active' => $user->is_active,
        ], $request);

        return response()->json([
            'success' => true,
            'mensaje' => $user->is_active
                ? 'Usuario activado correctamente.'
                : 'Usuario desactivado correctamente.',
            'usuario' => $this->serializeUser($user->fresh()),
        ]);
    }

    public function destroy(Request $request, User $user)
    {
        if ($request->user()->id === $user->id) {
            return response()->json([
                'success' => false,
                'mensaje' => 'No puedes eliminar tu propia cuenta.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->tokens()->delete();
        $user->delete();
        $this->auditLogService->record($request->user(), 'admin.users.deleted', $user->email, [], $request);

        return response()->json([
            'success' => true,
            'mensaje' => 'Usuario eliminado correctamente.',
        ]);
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->normalizedRole(),
            'is_active' => (bool) $user->is_active,
            'mfa_enabled' => (bool) $user->mfa_enabled,
            'custom_permissions' => $user->custom_permissions ?? [],
            'permissions' => $user->permissions(),
            'failed_login_attempts' => (int) $user->failed_login_attempts,
            'locked_until' => $user->locked_until?->toIso8601String(),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'last_login_ip' => $user->last_login_ip,
        ];
    }
}
