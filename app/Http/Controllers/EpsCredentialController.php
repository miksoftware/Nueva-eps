<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class EpsCredentialController extends Controller
{
    public function index()
    {
        $epsToken = session('eps_token');
        $epsNit = session('eps_nit');
        $epsApiUrl = config('services.eps.api_url');

        return view('eps.credentials', compact('epsToken', 'epsNit', 'epsApiUrl'));
    }

    public function saveToken(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string'],
            'nit' => ['required', 'string'],
            'sede_id' => ['required', 'string'],
        ]);

        session([
            'eps_token' => $request->token,
            'eps_nit' => $request->nit,
            'eps_sede' => $request->sede_id,
            'eps_sede_name' => $request->sede_name,
            'eps_ubicacion' => $request->ubicacion_prestador,
            'eps_parametros' => $request->parametros,
        ]);

        return response()->json(['success' => true]);
    }

    public function logout(Request $request)
    {
        session()->forget(['eps_token', 'eps_nit', 'eps_sede', 'eps_sede_name', 'eps_ubicacion', 'eps_parametros']);

        return response()->json(['success' => true]);
    }
}
