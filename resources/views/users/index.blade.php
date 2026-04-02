@extends('layouts.app')

@section('title', 'Usuarios')

@section('content')
<div class="page-header">
    <h1>Usuarios</h1>
    <a href="{{ route('users.create') }}" class="btn btn-primary">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M8 2a1 1 0 011 1v4h4a1 1 0 110 2H9v4a1 1 0 11-2 0V9H3a1 1 0 010-2h4V3a1 1 0 011-1z"/></svg>
        Nuevo Usuario
    </a>
</div>

<div class="card glass">
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Rol</th>
                    <th>Creado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                <tr>
                    <td>
                        <strong style="color: #fff;">{{ $user->name }}</strong>
                        @if($user->id === auth()->id())
                            <span style="color: #3b82f6; font-size: 0.75rem;">(tú)</span>
                        @endif
                    </td>
                    <td>{{ $user->email }}</td>
                    <td>
                        <span class="badge {{ $user->role === 'admin' ? 'badge-admin' : 'badge-consulta' }}">
                            {{ $user->role }}
                        </span>
                    </td>
                    <td style="color: #9ca3af;">{{ $user->created_at->format('d/m/Y') }}</td>
                    <td>
                        <div class="actions">
                            <a href="{{ route('users.edit', $user) }}" class="btn btn-warning btn-sm">Editar</a>
                            @if($user->id !== auth()->id())
                            <form action="{{ route('users.destroy', $user) }}" method="POST"
                                  onsubmit="return confirm('¿Estás seguro de eliminar este usuario?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" style="text-align: center; color: #9ca3af; padding: 2rem;">
                        No hay usuarios registrados.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
