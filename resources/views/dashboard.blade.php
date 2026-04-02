@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="page-header">
    <h1>Dashboard</h1>
</div>

<div class="dashboard-grid">
    @if(auth()->user()->isAdmin())
    <div class="card glass">
        <div class="card-header">
            <h3 class="card-title">Sesión EPS</h3>
            @if(session('eps_token'))
                <span class="badge badge-success">Conectado</span>
            @else
                <span class="badge badge-danger">Desconectado</span>
            @endif
        </div>
        <p style="color: #9ca3af; margin-bottom: 1rem;">
            @if(session('eps_token'))
                Sesión activa con NIT: <strong style="color: #fff;">{{ session('eps_nit') }}</strong>
            @else
                Debes configurar las credenciales de la EPS para usar el scraper.
            @endif
        </p>
        <a href="{{ route('eps.credentials') }}" class="btn btn-primary">
            @if(session('eps_token'))
                Ver credenciales
            @else
                Configurar credenciales
            @endif
        </a>
    </div>

    <div class="card glass">
        <div class="card-header">
            <h3 class="card-title">Usuarios</h3>
        </div>
        <p style="color: #9ca3af; margin-bottom: 1rem;">
            Gestiona los usuarios y sus roles en el sistema.
        </p>
        <a href="{{ route('users.index') }}" class="btn btn-primary">Gestionar usuarios</a>
    </div>
    @else
    <div class="card glass">
        <div class="card-header">
            <h3 class="card-title">Bienvenido</h3>
        </div>
        <p style="color: #9ca3af;">
            Bienvenido al sistema de Referencia y Contrareferencia de Nueva EPS.<br>
            Desde aquí podrás consultar referencias y ver el estado de las mismas.
        </p>
    </div>
    @endif
</div>

@endsection

@section('styles')
<style>
    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 1.5rem;
    }
</style>
@endsection
