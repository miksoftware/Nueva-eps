@extends('layouts.app')

@section('title', 'Editar Usuario')

@section('content')
<div class="page-header">
    <h1>Editar Usuario</h1>
    <a href="{{ route('users.index') }}" class="btn btn-warning">Volver</a>
</div>

<div class="card glass" style="max-width: 600px;">
    <form method="POST" action="{{ route('users.update', $user) }}">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label class="form-label" for="name">Nombre</label>
            <input type="text" id="name" name="name" class="form-control"
                   placeholder="Nombre completo" value="{{ old('name', $user->name) }}" required>
            @error('name')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="email">Correo electrónico</label>
            <input type="email" id="email" name="email" class="form-control"
                   placeholder="correo@ejemplo.com" value="{{ old('email', $user->email) }}" required>
            @error('email')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="role">Rol</label>
            <select id="role" name="role" class="form-control" required>
                <option value="admin" {{ old('role', $user->role) === 'admin' ? 'selected' : '' }}>Admin</option>
                <option value="consulta" {{ old('role', $user->role) === 'consulta' ? 'selected' : '' }}>Consulta</option>
            </select>
            @error('role')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="password">Nueva contraseña <span style="color: #6b7280;">(dejar vacío para no cambiar)</span></label>
            <input type="password" id="password" name="password" class="form-control"
                   placeholder="Mínimo 6 caracteres">
            @error('password')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="password_confirmation">Confirmar nueva contraseña</label>
            <input type="password" id="password_confirmation" name="password_confirmation" class="form-control"
                   placeholder="Repetir contraseña">
        </div>

        <button type="submit" class="btn btn-success btn-block">Guardar Cambios</button>
    </form>
</div>
@endsection
