@extends('layouts.app')

@section('title', 'Crear Usuario')

@section('content')
<div class="page-header">
    <h1>Crear Usuario</h1>
    <a href="{{ route('users.index') }}" class="btn btn-warning">Volver</a>
</div>

<div class="card glass" style="max-width: 600px;">
    <form method="POST" action="{{ route('users.store') }}">
        @csrf

        <div class="form-group">
            <label class="form-label" for="name">Nombre</label>
            <input type="text" id="name" name="name" class="form-control"
                   placeholder="Nombre completo" value="{{ old('name') }}" required>
            @error('name')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="email">Correo electrónico</label>
            <input type="email" id="email" name="email" class="form-control"
                   placeholder="correo@ejemplo.com" value="{{ old('email') }}" required>
            @error('email')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="role">Rol</label>
            <select id="role" name="role" class="form-control" required>
                <option value="">Seleccionar rol</option>
                <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>Admin</option>
                <option value="consulta" {{ old('role') === 'consulta' ? 'selected' : '' }}>Consulta</option>
            </select>
            @error('role')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="password">Contraseña</label>
            <input type="password" id="password" name="password" class="form-control"
                   placeholder="Mínimo 6 caracteres" required>
            @error('password')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="password_confirmation">Confirmar contraseña</label>
            <input type="password" id="password_confirmation" name="password_confirmation" class="form-control"
                   placeholder="Repetir contraseña" required>
        </div>

        <button type="submit" class="btn btn-success btn-block">Crear Usuario</button>
    </form>
</div>
@endsection
