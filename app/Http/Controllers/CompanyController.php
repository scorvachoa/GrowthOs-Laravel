<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChannelRequest;
use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Requests\UpdateOrganizationRequest;
use App\Models\Channel;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CompanyController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user->hasRole('Super Admin')) {
            $companies = Organization::withCount('users')->orderBy('name')->get()->map(fn ($o) => [
                'id' => $o->id,
                'name' => $o->name,
                'logo_url' => $o->logo_path ? Storage::url($o->logo_path) : null,
                'primary_color' => $o->primary_color,
                'admin_invite_code' => $o->admin_invite_code,
                'invite_code' => $o->invite_code,
                'users_count' => $o->users_count,
                'created_at' => $o->created_at?->format('d/m/Y'),
            ]);

            return Inertia::render('Company/Index', [
                'companies' => $companies,
                'isSuperAdmin' => true,
                'organization' => null,
                'channels' => [],
            ]);
        }

        $org = Organization::find($user->organization_id);
        $channels = Channel::query()->orderBy('name')->get()->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'color' => $c->color,
            'youtube_channel_id' => $c->youtube_channel_id,
            'channel_url' => $c->channel_url,
        ]);

        return Inertia::render('Company/Index', [
            'companies' => null,
            'isSuperAdmin' => false,
            'organization' => $org ? [
                'id' => $org->id,
                'name' => $org->name,
                'logo_path' => $org->logo_path,
                'primary_color' => $org->primary_color,
                'logo_url' => $org->logo_path ? Storage::url($org->logo_path) : null,
                'invite_code' => $org->invite_code,
            ] : null,
            'channels' => $channels,
        ]);
    }

    public function create()
    {
        $this->authorize('create', Organization::class);

        return Inertia::render('Company/Create');
    }

    public function store(StoreOrganizationRequest $request)
    {
        $data = $request->validated();
        $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $data['name']), 0, 5)) . '-' . random_int(10000, 99999);

        $org = Organization::create([
            'name' => $data['name'],
            'primary_color' => $data['primary_color'] ?? '#4f46e5',
            'admin_invite_code' => $code,
        ]);

        if ($request->hasFile('logo')) {
            $org->update(['logo_path' => $request->file('logo')->store('logos', 'public')]);
        }

        return redirect()->route('company.index')
            ->with('success', "Empresa \"{$org->name}\" creada. Código admin: $code");
    }

    public function show(Organization $company)
    {
        $this->authorize('view', $company);

        $channels = Channel::where('organization_id', $company->id)->orderBy('name')->get()->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'color' => $c->color,
            'youtube_channel_id' => $c->youtube_channel_id,
            'channel_url' => $c->channel_url,
        ]);

        return Inertia::render('Company/Index', [
            'companies' => null,
            'isSuperAdmin' => Auth::user()->hasRole('Super Admin'),
            'organization' => [
                'id' => $company->id,
                'name' => $company->name,
                'logo_path' => $company->logo_path,
                'primary_color' => $company->primary_color,
                'logo_url' => $company->logo_path ? Storage::url($company->logo_path) : null,
                'invite_code' => $company->invite_code,
            ],
            'channels' => $channels,
        ]);
    }

    public function edit(Organization $company)
    {
        $this->authorize('update', $company);

        return Inertia::render('Company/Edit', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'logo_path' => $company->logo_path,
                'primary_color' => $company->primary_color,
                'logo_url' => $company->logo_path ? Storage::url($company->logo_path) : null,
            ],
        ]);
    }

    public function update(UpdateOrganizationRequest $request, Organization $company)
    {
        $data = $request->validated();
        $company->name = $data['name'];
        $company->primary_color = $data['primary_color'] ?? '#4f46e5';

        if ($request->hasFile('logo')) {
            if ($company->logo_path) {
                Storage::disk('public')->delete($company->logo_path);
            }
            $company->logo_path = $request->file('logo')->store('logos', 'public');
        }

        if ($request->boolean('remove_logo')) {
            if ($company->logo_path) {
                Storage::disk('public')->delete($company->logo_path);
            }
            $company->logo_path = null;
        }

        $company->save();

        return redirect()->route('company.index')->with('warning', 'Empresa actualizada');
    }

    public function destroy(Organization $company)
    {
        $this->authorize('delete', $company);

        $userCount = User::where('organization_id', $company->id)->count();
        if ($userCount > 0) {
            return redirect()->back()->with('error', 'No se puede eliminar una empresa con usuarios activos');
        }

        $company->delete();

        return redirect()->route('company.index')->with('error', 'Empresa eliminada');
    }

    public function storeChannel(StoreChannelRequest $request)
    {
        Channel::create($request->validated());
        return redirect()->back()->with('success', 'Canal creado');
    }

    public function updateChannel(StoreChannelRequest $request, Channel $channel)
    {
        $channel->update($request->validated());
        return redirect()->back()->with('warning', 'Canal actualizado');
    }

    public function destroyChannel(Channel $channel)
    {
        $channel->delete();
        return redirect()->back()->with('error', 'Canal eliminado');
    }

    public function switchCompany(Request $request)
    {
        $request->validate(['company_id' => 'required|exists:organizations,id']);

        if (!auth()->user()->hasRole('Super Admin')) {
            abort(403);
        }

        session(['active_company_id' => (int) $request->company_id]);

        return redirect()->back()->with('success', 'Empresa cambiada');
    }

    public function generateInviteCode(Request $request)
    {
        $orgId = $request->input('organization_id', Auth::user()->organization_id);
        $org = Organization::find($orgId);

        if (!$org) {
            throw ValidationException::withMessages(['invite_code' => 'No existe la empresa']);
        }

        if (!Auth::user()->hasRole('Super Admin') && $org->id !== Auth::user()->organization_id) {
            abort(403);
        }

        $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $org->name), 0, 5)) . '-' . random_int(1000, 9999);
        $org->update(['invite_code' => $code]);

        return redirect()->back()->with('success', "Código de invitación generado: $code");
    }
}
