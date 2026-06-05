<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CompanyController extends Controller
{
    public function index()
    {
        $org = Organization::query()->first();
        $channels = Channel::query()->orderBy('name')->get()->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'color' => $c->color,
            'youtube_channel_id' => $c->youtube_channel_id,
            'channel_url' => $c->channel_url,
        ]);

        return Inertia::render('Company/Index', [
            'organization' => $org ? [
                'id' => $org->id,
                'name' => $org->name,
                'logo_path' => $org->logo_path,
                'primary_color' => $org->primary_color,
                'logo_url' => $org->logo_path ? Storage::url($org->logo_path) : null,
            ] : null,
            'channels' => $channels,
        ]);
    }

    public function updateCompany(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'primary_color' => ['required', 'string', 'max:20'],
        ]);

        $org = Organization::query()->first();
        if (!$org) {
            return redirect()->back()->with('error', 'No existe la empresa');
        }
        $org->name = $validated['name'];
        $org->primary_color = $validated['primary_color'];

        if ($request->hasFile('logo')) {
            $request->validate(['logo' => ['image', 'mimes:jpeg,png,webp', 'max:2048']]);
            $path = $request->file('logo')->store('logos', 'public');
            $org->logo_path = $path;
        }

        if ($request->boolean('remove_logo')) {
            if ($org->logo_path) {
                Storage::disk('public')->delete($org->logo_path);
            }
            $org->logo_path = null;
        }

        $org->save();

        return redirect()->back()->with('success', 'Empresa actualizada');
    }

    public function storeChannel(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:channels,name'],
            'color' => ['required', 'string', 'max:20'],
            'youtube_channel_id' => ['nullable', 'string', 'max:120'],
            'channel_url' => ['nullable', 'url', 'max:500'],
        ]);

        Channel::create($validated);

        return redirect()->back()->with('success', 'Canal creado');
    }

    public function updateChannel(Request $request, Channel $channel)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('channels', 'name')->ignore($channel->id)],
            'color' => ['required', 'string', 'max:20'],
            'youtube_channel_id' => ['nullable', 'string', 'max:120'],
            'channel_url' => ['nullable', 'url', 'max:500'],
        ]);

        $channel->update($validated);

        return redirect()->back()->with('success', 'Canal actualizado');
    }

    public function destroyChannel(Channel $channel)
    {
        $channel->delete();

        return redirect()->back()->with('success', 'Canal eliminado');
    }
}
