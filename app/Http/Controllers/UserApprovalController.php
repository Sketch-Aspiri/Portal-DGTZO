<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PortalRole;
use App\Enums\UserStatus;
use App\Http\Requests\ApproveUserRequest;
use App\Http\Requests\GrantAccessRequest;
use App\Http\Requests\RejectUserRequest;
use App\Models\User;
use App\Services\Ecosystem\SubsystemGateway;
use App\Services\UserApprovalService;
use App\Support\Subsistemas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserApprovalController extends Controller
{
    public function __construct(
        private readonly UserApprovalService $approvals,
        private readonly SubsystemGateway $gateway,
    ) {}

    public function index(Request $request): View
    {
        $status = UserStatus::tryFrom((string) $request->query('estado')) ?? UserStatus::Pending;

        $term = trim((string) $request->query('q', ''));
        $term = mb_substr($term, 0, 100);

        $users = User::query()
            ->with(['solicitudAcceso:id,user_id,estado', 'accesos:id,user_id,subsistema'])
            ->where('status', $status->value)
            ->when($term !== '', function ($query) use ($term): void {
                $like = '%'.addcslashes($term, '\\%_').'%';
                $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->orderBy('created_at')
            ->paginate(20)
            ->withQueryString();

        // Totales por estado para las pestañas (una sola consulta).
        $counts = User::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('users.index', ['users' => $users, 'status' => $status, 'counts' => $counts, 'term' => $term]);
    }

    public function show(Request $request, User $user): View
    {
        $user->load('accesos', 'solicitudAcceso');
        $pending = $user->status === UserStatus::Pending;

        // Dar acceso a una cuenta activa: solo a otra persona y solo a los sistemas que aún no tiene.
        $canGrant = $user->status === UserStatus::Active && ! $request->user()->is($user);
        $missing = array_values(array_diff(Subsistemas::claves(), $user->accesos->pluck('subsistema')->all()));

        // El formulario ofrece lo que cada subsistema tiene hoy (equipos, estaciones); null = no responde.
        $offered = $pending ? Subsistemas::claves() : ($canGrant ? $missing : []);

        return view('users.show', [
            'user' => $user,
            'solicitud' => $user->solicitudAcceso,
            'catalogo' => Subsistemas::todos(),
            'catalogos' => collect($offered)->mapWithKeys(
                fn (string $key): array => [$key => $this->gateway->catalog($key)],
            )->all(),
            'esAdministrador' => $request->user()->portal_role === PortalRole::Administrador,
            'puedeDarAcceso' => $canGrant,
            'faltantes' => $missing,
        ]);
    }

    public function grant(GrantAccessRequest $request, User $user): RedirectResponse
    {
        $this->approvals->grant($user, $request->user(), $request->accesos());

        return redirect()->route('users.show', $user)->with('status', __('portal.access_granted'));
    }

    public function approve(ApproveUserRequest $request, User $user): RedirectResponse
    {
        $this->approvals->approve($user, $request->user(), $request->accesos());

        return redirect()->route('users.show', $user)->with('status', __('portal.approved'));
    }

    public function reject(RejectUserRequest $request, User $user): RedirectResponse
    {
        $this->approvals->reject($user, $request->user(), $request->validated('reason'));

        return redirect()->route('users.index')->with('status', __('portal.rejected'));
    }

    public function retry(Request $request, User $user, string $subsistema): RedirectResponse
    {
        $this->approvals->retry($user, $request->user(), $subsistema);

        return redirect()->route('users.show', $user)->with('status', __('portal.retrying'));
    }
}
