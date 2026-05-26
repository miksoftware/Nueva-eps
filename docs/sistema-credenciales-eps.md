# Sistema de Credenciales EPS — Guía de Implementación

> Basado en el proyecto **Nueva EPS**. Esta guía documenta la arquitectura del sistema de autenticación dinámica contra la API de la EPS para poder replicarla en otros proyectos.

---

## Índice

1. [Visión general](#1-visión-general)
2. [Arquitectura y flujo completo](#2-arquitectura-y-flujo-completo)
3. [Archivos involucrados](#3-archivos-involucrados)
4. [Paso a paso — cómo funciona](#4-paso-a-paso--cómo-funciona)
5. [Cómo implementarlo en otra EPS](#5-cómo-implementarlo-en-otra-eps)
6. [Variables de entorno y configuración](#6-variables-de-entorno-y-configuración)
7. [Endpoints de la API EPS](#7-endpoints-de-la-api-eps)
8. [Consideraciones de seguridad](#8-consideraciones-de-seguridad)

---

## 1. Visión general

El sistema tiene **dos capas de autenticación independientes**:

| Capa | Qué es | Tecnología |
|---|---|---|
| **Capa 1 — App** | Login de usuario en la aplicación Laravel | Laravel Auth (email + password, tabla `users`) |
| **Capa 2 — EPS** | Login contra la API externa de la EPS | NIT + Sede + Contraseña → JWT Token |

El usuario primero inicia sesión en la aplicación. Luego, el rol `admin` debe autenticarse contra la API de la EPS para obtener un **token JWT** que se almacena en la sesión PHP. Ese token es el que se usa en todas las consultas posteriores.

---

## 2. Arquitectura y flujo completo

```
┌─────────────────────────────────────────────────────────────────┐
│                        NAVEGADOR (Browser)                       │
│                                                                   │
│  1. GET /eps/credentials                                          │
│     └─→ muestra formulario: NIT del prestador                    │
│                                                                   │
│  2. JS: fetch → API EPS (directo, sin pasar por Laravel)         │
│     GET {EPS_API_URL}/ConsultarSedesPorNITPrestador?nit=...      │
│     └─→ devuelve array de sedes                                  │
│                                                                   │
│  3. Usuario selecciona sede + ingresa contraseña                 │
│                                                                   │
│  4. JS: fetch → API EPS (directo)                                │
│     POST {EPS_API_URL}/ObtenerTokenAutenticacion                 │
│     Body: { sedea: { id, name }, Password: "..." }               │
│     └─→ devuelve { return: "JWT_TOKEN_AQUI", ... }               │
│                                                                   │
│  5. JS: fetch → /eps/save-token (Laravel)                        │
│     POST con el token obtenido                                    │
│     └─→ Laravel guarda en session()                              │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                    CONSULTAS POSTERIORES                          │
│                                                                   │
│  JS lee session('eps_token') inyectado en Blade:                 │
│    const EPS_TOKEN = '{{ session("eps_token") }}'               │
│                                                                   │
│  fetch → API EPS con Authorization: Bearer {EPS_TOKEN}           │
│    GET {EPS_API_URL}/ConsultarAfiliado?documento=...             │
└─────────────────────────────────────────────────────────────────┘
```

> **Decisión clave de arquitectura**: las llamadas a la API de la EPS se hacen **desde el navegador (client-side)**, NO desde el servidor Laravel. Esto evita problemas de bloqueo por Cloudflare y mantiene el servidor desacoplado de la API externa durante las consultas.

---

## 3. Archivos involucrados

```
app/
├── Http/Controllers/
│   ├── AuthController.php            ← Login/logout de la app Laravel
│   └── EpsCredentialController.php   ← Guardar/borrar token EPS en sesión
├── Services/
│   └── EpsScraperService.php         ← Servicio PHP para llamadas server-side a la API EPS
config/
└── services.php                      ← Define services.eps.api_url
resources/views/
├── auth/
│   └── login.blade.php               ← Login de la app (email + password)
└── eps/
    └── credentials.blade.php         ← UI de 2 pasos para login en la EPS
routes/
└── web.php                           ← Rutas protegidas por middleware auth y admin
.env                                  ← EPS_API_URL=https://api.referencias.nuevaeps.com.co
```

---

## 4. Paso a paso — cómo funciona

### 4.1 Login de la aplicación (`AuthController`)

Autenticación estándar de Laravel. El usuario ingresa email + contraseña.

```php
// app/Http/Controllers/AuthController.php
public function login(Request $request)
{
    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required'],
    ]);

    if (Auth::attempt($credentials, $request->boolean('remember'))) {
        $request->session()->regenerate();

        // Redirección por rol
        if (Auth::user()->role === 'consulta') {
            return redirect()->intended(route('consultas.index'));
        }
        return redirect()->intended(route('dashboard'));
    }

    return back()->withErrors(['email' => 'Credenciales incorrectas.'])->onlyInput('email');
}
```

Los **roles** relevantes son:
- `admin` → puede gestionar credenciales EPS, subir CSV, ver dashboard completo
- `consulta` → solo puede buscar cédulas individuales

---

### 4.2 Pantalla de credenciales EPS (`credentials.blade.php`)

Accesible en `GET /eps/credentials`, solo para admins. Flujo en 2 pasos:

#### Paso 1 — NIT del prestador

```javascript
async function consultarSedes() {
    const nit = document.getElementById('nit').value.trim();
    const url = `${EPS_API}/ConsultarSedesPorNITPrestador?nit_prestador=${encodeURIComponent(nit)}`;

    const response = await fetch(url, {
        method: 'GET',
        headers: { 'Accept': 'application/json' },
    });

    const data = await response.json();
    // data es un array de sedes: [{ id_sede, nombre_sede, ... }, ...]

    // Poblar el <select> con las sedes
    sedes.forEach(sede => {
        const id    = sede.id_sede || sede.IdSede || sede.id || Object.values(sede)[0];
        const nombre = sede.nombre_sede || sede.NombreSede || sede.nombre || Object.values(sede)[1];
        // agregar <option value="{id}">{nombre}</option>
    });
}
```

> El código usa múltiples alias de clave (`id_sede || IdSede || ...`) porque la estructura exacta de la respuesta puede variar por EPS. Adaptar según la API real.

#### Paso 2 — Sede + Contraseña → Token

```javascript
async function loginEps() {
    const nit      = document.getElementById('nit').value.trim();
    const sedeId   = document.getElementById('sede').value;
    const sedeName = document.getElementById('sede').options[selectedIndex].textContent;
    const password = document.getElementById('epsPassword').value;

    const response = await fetch(`${EPS_API}/ObtenerTokenAutenticacion`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        body: JSON.stringify({
            sedea: { id: sedeId, name: sedeName },
            Password: password,
        }),
    });

    const data = await response.json();
    const token = data.return || data.token || data.Token || null;

    if (token) {
        // Guardar el token en la sesión Laravel
        await fetch('/eps/save-token', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({
                token:    token,
                nit:      nit,
                sede_id:  sedeId,
                sede_name: sedeName,
                ubicacion_prestador: JSON.stringify(data.ubicacion_prestador || {}),
                parametros: JSON.stringify(data.parametros || {}),
            }),
        });
    }
}
```

---

### 4.3 Guardado del token en sesión (`EpsCredentialController`)

```php
// app/Http/Controllers/EpsCredentialController.php
public function saveToken(Request $request)
{
    $request->validate([
        'token'   => ['required', 'string'],
        'nit'     => ['required', 'string'],
        'sede_id' => ['required', 'string'],
    ]);

    session([
        'eps_token'         => $request->token,
        'eps_nit'           => $request->nit,
        'eps_sede'          => $request->sede_id,
        'eps_sede_name'     => $request->sede_name,
        'eps_ubicacion'     => $request->ubicacion_prestador,
        'eps_parametros'    => $request->parametros,
    ]);

    return response()->json(['success' => true]);
}

public function logout(Request $request)
{
    session()->forget(['eps_token', 'eps_nit', 'eps_sede', 'eps_sede_name', 'eps_ubicacion', 'eps_parametros']);
    return response()->json(['success' => true]);
}
```

---

### 4.4 Uso del token en las consultas

En las vistas Blade, el token se inyecta como variable JavaScript:

```blade
{{-- resources/views/consultas/index.blade.php --}}
<script>
    const EPS_API   = '{{ config("services.eps.api_url") }}';
    const EPS_TOKEN = '{{ session("eps_token", "") }}';
</script>
```

Luego el JS lo usa en los fetch a la API:

```javascript
const response = await fetch(`${EPS_API}/ConsultarAfiliado?documento=${cedula}`, {
    headers: {
        'Authorization': `Bearer ${EPS_TOKEN}`,
        'Accept': 'application/json',
    },
});
```

Si no hay token activo, la vista muestra un aviso:

```blade
@unless(session('eps_token'))
    <div class="alert alert-error">
        Debe iniciar sesión en las
        <a href="{{ route('eps.credentials') }}">Credenciales EPS</a>
        antes de realizar consultas.
    </div>
@endunless
```

---

### 4.5 Servicio PHP server-side (`EpsScraperService`)

Para procesos en background (jobs, comandos artisan, procesamiento de CSV), se usa el servicio PHP en lugar de JS:

```php
// app/Services/EpsScraperService.php
class EpsScraperService
{
    protected string $baseUrl;
    protected ?string $token = null;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.eps.api_url'), '/');
    }

    protected function http()
    {
        return Http::withOptions([
            'verify' => false,         // desactivar SSL verify si hay problemas de certificado
            'curl' => [
                // resolver IP manualmente si Cloudflare bloquea resolución DNS
                CURLOPT_RESOLVE => ['api.eps.com.co:443:104.18.27.237'],
            ],
        ])
        ->withHeaders([
            'User-Agent' => 'Mozilla/5.0 ...',
            'Accept'     => 'application/json',
            'Origin'     => 'https://pwa.eps.com.co',
            'Referer'    => 'https://pwa.eps.com.co/',
        ])
        ->timeout(30);
    }
}
```

> El `CURLOPT_RESOLVE` con IP fija es una solución al bloqueo de Cloudflare en entornos de servidor donde la resolución DNS puede ser distinta al navegador.

---

## 5. Cómo implementarlo en otra EPS

### 5.1 Archivos a crear / copiar

Copiar y adaptar los siguientes archivos:

| Archivo | Qué adaptar |
|---|---|
| `app/Http/Controllers/EpsCredentialController.php` | Claves de sesión si cambian, validaciones |
| `app/Services/EpsScraperService.php` | `baseUrl`, headers `Origin`/`Referer`, IP en `CURLOPT_RESOLVE` |
| `resources/views/eps/credentials.blade.php` | Endpoints, estructura de campos (ver §5.3) |
| `config/services.php` | Agregar bloque `'eps' => ['api_url' => env('EPS_API_URL')]` |
| `.env` | `EPS_API_URL=https://api.nueva-eps.com.co` |
| `routes/web.php` | Agregar las 3 rutas de credenciales |

### 5.2 Rutas a agregar

```php
// routes/web.php — dentro del grupo middleware('auth')->middleware('admin')
Route::get('/eps/credentials', [EpsCredentialController::class, 'index'])->name('eps.credentials');
Route::post('/eps/save-token',  [EpsCredentialController::class, 'saveToken'])->name('eps.saveToken');
Route::post('/eps/logout',      [EpsCredentialController::class, 'logout'])->name('eps.logout');
```

### 5.3 Adaptar los endpoints de la API

Cada EPS tendrá endpoints distintos. Localizar y reemplazar en `credentials.blade.php` y `EpsScraperService.php`:

| Concepto | Nueva EPS | Adaptar a la nueva EPS |
|---|---|---|
| Consultar sedes | `GET /ConsultarSedesPorNITPrestador?nit_prestador=` | Endpoint equivalente |
| Obtener token | `POST /ObtenerTokenAutenticacion` | Endpoint equivalente |
| Body del login | `{ sedea: {id, name}, Password }` | Según documentación |
| Campo del token en respuesta | `data.return` | `data.token` o `data.access_token` |
| Campo ID de sede | `sede.id_sede` | Según respuesta real |
| Campo nombre de sede | `sede.nombre_sede` | Según respuesta real |

### 5.4 Adaptar `config/services.php`

```php
// config/services.php
'eps' => [
    'api_url' => env('EPS_API_URL', 'https://api.nueva-eps.com.co'),
],
```

### 5.5 Verificar el guard de sesión en las vistas

En cada vista donde se usen consultas, asegurarse de:

```blade
{{-- Verificar que hay token activo --}}
@unless(session('eps_token'))
    <div class="alert alert-error">
        Configure las <a href="{{ route('eps.credentials') }}">Credenciales EPS</a>.
    </div>
@endunless

@section('scripts')
<script>
    const EPS_API   = '{{ config("services.eps.api_url") }}';
    const EPS_TOKEN = '{{ session("eps_token", "") }}';
</script>
@endsection
```

### 5.6 Dashboard — indicador de estado de conexión

```blade
{{-- resources/views/dashboard.blade.php --}}
@if(session('eps_token'))
    <span class="badge badge-success">Conectado</span>
    Sesión activa con NIT: <strong>{{ session('eps_nit') }}</strong>
@else
    <span class="badge badge-danger">Desconectado</span>
    <a href="{{ route('eps.credentials') }}">Configurar credenciales</a>
@endif
```

---

## 6. Variables de entorno y configuración

### `.env`

```env
# URL base de la API de la EPS (sin trailing slash)
EPS_API_URL=https://api.referencias.nuevaeps.com.co
```

### `config/services.php`

```php
'eps' => [
    'api_url' => env('EPS_API_URL', 'https://api.referencias.nuevaeps.com.co'),
],
```

### Claves de sesión usadas

| Clave | Contenido |
|---|---|
| `eps_token` | JWT token para autenticar llamadas a la API |
| `eps_nit` | NIT del prestador autenticado |
| `eps_sede` | ID de la sede seleccionada |
| `eps_sede_name` | Nombre legible de la sede |
| `eps_ubicacion` | JSON con datos de ubicación del prestador |
| `eps_parametros` | JSON con parámetros adicionales devueltos por la API |

---

## 7. Endpoints de la API EPS

### `GET /ConsultarSedesPorNITPrestador`

| Parámetro | Tipo | Descripción |
|---|---|---|
| `nit_prestador` | query string | NIT de la IPS/prestador |

**Respuesta exitosa:**
```json
[
  {
    "id_sede": "123",
    "nombre_sede": "Sede Principal",
    "...": "otros campos"
  }
]
```

---

### `POST /ObtenerTokenAutenticacion`

**Body (JSON):**
```json
{
  "sedea": {
    "id": "123",
    "name": "Sede Principal"
  },
  "Password": "contraseña_aqui"
}
```

**Respuesta exitosa:**
```json
{
  "return": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "ubicacion_prestador": "{...json string...}",
  "parametros": "{...json string...}"
}
```

> El token viene en el campo `return`. Para otras EPS puede estar en `token`, `access_token` o `Token`. Verificar con logs en la consola del navegador.

---

### `GET /ConsultarAfiliado` (ejemplo de consulta con token)

```javascript
fetch(`${EPS_API}/ConsultarAfiliado?documento=${cedula}`, {
    headers: {
        'Authorization': `Bearer ${EPS_TOKEN}`,
        'Accept': 'application/json',
    },
});
```

---

## 8. Consideraciones de seguridad

1. **El token NO se guarda en base de datos** — vive solo en la sesión PHP (`session()`). Al cerrar sesión o reiniciar el servidor, se pierde y hay que volver a autenticarse.

2. **La contraseña de la EPS NO se guarda en ningún lado** — solo se usa para obtener el token y se descarta.

3. **Solo admins** pueden acceder a `/eps/credentials` gracias al middleware `admin`.

4. **CSRF protection** — todos los POST internos de Laravel incluyen el token CSRF en el header `X-CSRF-TOKEN`.

5. **`verify => false` en cURL** — se desactiva la verificación SSL en el servicio PHP para evitar errores con certificados de la API externa. Esto es aceptable si la URL es de confianza y está en `.env`.

6. **Contraseña en el body del log** — en el servicio `EpsScraperService`, el log de autenticación enmascara la contraseña (`'contrasena' => '***'`). Mantener esta práctica.

7. **Token expuesto en JS** — el token JWT se inyecta en la página como variable JavaScript. Asegurarse de que las vistas no sean accesibles sin autenticación (middleware `auth`).

---

*Documentación generada a partir del proyecto Nueva EPS — `app/Services/EpsScraperService.php`, `app/Http/Controllers/EpsCredentialController.php`, `resources/views/eps/credentials.blade.php`*
