<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PortalRole;
use App\Enums\UserStatus;
use App\Http\Requests\ApproveUserRequest;
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

        $users = User::query()
            ->where('status', $status->value)
            ->orderBy('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('users.index', ['users' => $users, 'status' => $status]);
    }

    public function show(Request $request, User $user): View
    {
        $pending = $user->status === UserStatus::Pending;

        return view('users.show', [
            'user' => $user->load('accesos'),
            'catalogo' => Subsistemas::todos(),
            // El formulario ofrece lo que cada subsistema tiene hoy (equipos, estaciones); null = no responde.
            'catalogos' => $pending ? collect(Subsistemas::claves())->mapWithKeys(
                fn (string $key): array => [$key => $this->gateway->catalog($key)],
            )->all() : [],
            'esAdministrador' => $request->user()->portal_role === PortalRole::Administrador,
        ]);
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
