<?php

namespace App\Http\Controllers;

use App\Models\Contenido;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ManagementController extends Controller
{
    private function model(string $resource): string
    {
        return ['usuarios' => User::class, 'productos' => Producto::class, 'contenidos' => Contenido::class][$resource];
    }

    private function authorizeResource(Request $r, string $resource, bool $write = false): void
    {
        abort_if(($write || $resource === 'usuarios') && $r->user()->role !== 'superadmin', 403);
    }

    private function rules(string $resource, ?int $id = null): array
    {
        return match ($resource) {
            'usuarios' => ['name' => 'required|string|max:120', 'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($id)], 'password' => ($id ? 'sometimes' : 'required').'|string|min:10|max:128', 'role' => 'required|in:user,superadmin'],
            'productos' => ['nombre' => 'required|string|max:150', 'descripcion' => 'nullable|string|max:5000', 'precio' => 'required|numeric|min:0|max:9999999999.99', 'stock' => 'required|integer|min:0|max:100000000'],
            'contenidos' => ['titulo' => 'required|string|max:180', 'cuerpo' => 'required|string|max:20000', 'publicado' => 'required|boolean']
        };
    }

    public function index(Request $r, string $resource)
    {
        $this->authorizeResource($r, $resource);
        $q = $this->model($resource)::query();
        if ($resource === 'contenidos' && $r->user()->role !== 'superadmin') {
            $q->where('publicado', true);
        }

        return $q->latest('id')->get();
    }

    public function show(Request $r, string $resource, int $id)
    {
        $this->authorizeResource($r, $resource);
        $m = $this->model($resource)::findOrFail($id);
        abort_if($resource === 'contenidos' && ! $m->publicado && $r->user()->role !== 'superadmin', 404);

        return $m;
    }

    public function store(Request $r, string $resource)
    {
        $this->authorizeResource($r, $resource, true);

        return response()->json($this->model($resource)::create($r->validate($this->rules($resource))), 201);
    }

    public function update(Request $r, string $resource, int $id)
    {
        $this->authorizeResource($r, $resource, true);
        $m = $this->model($resource)::findOrFail($id);
        $d = $r->validate($this->rules($resource, $id));
        abort_if($resource === 'usuarios' && $id === $r->user()->id && $d['role'] !== 'superadmin', 422, 'No puedes quitar tu propio rol.');
        $revoke = $resource === 'usuarios' && (isset($d['password']) || $d['role'] !== $m->role);
        $m->update($d);
        if ($revoke) {
            $m->tokens()->delete();
        }

        return $m;
    }

    public function destroy(Request $r, string $resource, int $id)
    {
        $this->authorizeResource($r, $resource, true);
        abort_if($resource === 'usuarios' && $id === $r->user()->id, 422, 'No puedes eliminar tu propia cuenta.');
        $m = $this->model($resource)::findOrFail($id);
        if ($resource === 'usuarios') {
            $m->tokens()->delete();
        }
        $m->delete();

        return response()->noContent();
    }
}
