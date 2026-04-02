@extends('layouts.app')

@section('title', 'Credenciales EPS')

@section('content')
<div class="page-header">
    <h1>Credenciales EPS</h1>
</div>

<div class="eps-wrapper">
    {{-- Paso 1: Ingresar NIT --}}
    <div class="card glass" id="step1" style="max-width: 600px;">
        <div class="card-header">
            <h3 class="card-title">Paso 1 — Ingresar NIT del Prestador</h3>
            <span class="badge badge-consulta">Activo</span>
        </div>

        <div class="form-group">
            <label class="form-label" for="nit">NIT del Prestador</label>
            <input type="text" id="nit" class="form-control" placeholder="Ej: 890907215"
                   value="{{ session('eps_nit', '') }}">
        </div>

        <button type="button" class="btn btn-primary btn-block" id="btnConsultarSedes" onclick="consultarSedes()">
            CONSULTAR SEDES
        </button>
    </div>

    {{-- Paso 2: Seleccionar sede y contraseña --}}
    <div class="card glass" id="step2" style="max-width: 600px; display: none;">
        <div class="card-header">
            <h3 class="card-title">Paso 2 — Seleccionar Sede e Ingresar Contraseña</h3>
            <span class="badge badge-consulta">Activo</span>
        </div>

        <div class="form-group">
            <label class="form-label" for="sede">Sede</label>
            <select id="sede" class="form-control">
                <option value="">Cargando sedes...</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label" for="epsPassword">Contraseña</label>
            <input type="password" id="epsPassword" class="form-control" placeholder="Contraseña de la EPS">
        </div>

        <button type="button" class="btn btn-success btn-block" id="btnLogin" onclick="loginEps()">
            INGRESAR A LA EPS
        </button>

        <button type="button" class="btn btn-warning btn-block" style="margin-top: 0.5rem;" onclick="resetSteps()">
            Volver al Paso 1
        </button>
    </div>

    {{-- Estado de conexión --}}
    <div class="card glass" id="statusCard" style="max-width: 600px; display: none;">
        <div class="card-header">
            <h3 class="card-title">Estado de Conexión</h3>
            <span class="badge badge-success">Conectado</span>
        </div>

        <div id="statusContent">
            <p style="color: #9ca3af; margin-bottom: 1rem;">
                Sesión activa con la EPS. El token se ha guardado en la sesión.
            </p>
            <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 10px; padding: 1rem; margin-bottom: 1rem;">
                <p style="color: #34d399; font-size: 0.85rem;">
                    <strong>NIT:</strong> <span id="connectedNit"></span><br>
                    <strong>Token:</strong> <span id="connectedToken" style="word-break: break-all; font-size: 0.75rem;"></span>
                </p>
            </div>
        </div>

        <button type="button" class="btn btn-danger btn-block" onclick="logoutEps()">
            Cerrar Sesión EPS
        </button>
    </div>

    {{-- Mensajes --}}
    <div id="epsMessage" style="max-width: 600px; display: none;"></div>

    {{-- Log en tiempo real --}}
    <div class="card glass" style="max-width: 900px; margin-top: 1rem;">
        <div class="card-header">
            <h3 class="card-title">Log de Peticiones EPS</h3>
            <button class="btn btn-sm btn-danger" onclick="clearLog()">Limpiar</button>
        </div>
        <div id="epsLog" style="max-height: 400px; overflow-y: auto; font-family: 'Consolas', monospace; font-size: 0.8rem; padding: 0.5rem; background: rgba(0,0,0,0.3); border-radius: 8px;">
            <p style="color: #6b7280;">Esperando peticiones...</p>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const EPS_API = '{{ $epsApiUrl }}';

    // Si ya hay token en sesión, mostrar estado
    @if(session('eps_token'))
        document.getElementById('step1').style.display = 'none';
        document.getElementById('statusCard').style.display = 'block';
        document.getElementById('connectedNit').textContent = '{{ session("eps_nit") }}';
        document.getElementById('connectedToken').textContent = '{{ substr(session("eps_token", ""), 0, 80) }}...';
    @endif

    // === LOGGING ===
    function addLog(type, title, data) {
        const logEl = document.getElementById('epsLog');
        const time = new Date().toLocaleTimeString();
        const colors = { request: '#3b82f6', response: '#10b981', error: '#ef4444', info: '#f59e0b' };
        const color = colors[type] || '#fff';

        let content = `<div style="margin-bottom: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 8px;">`;
        content += `<div style="color: ${color}; font-weight: bold;">[${time}] ${type.toUpperCase()} — ${title}</div>`;

        if (typeof data === 'object') {
            content += `<pre style="color: #d1d5db; white-space: pre-wrap; word-break: break-all; margin-top: 4px;">${JSON.stringify(data, null, 2)}</pre>`;
        } else if (data) {
            content += `<pre style="color: #d1d5db; white-space: pre-wrap; word-break: break-all; margin-top: 4px;">${data}</pre>`;
        }

        content += `</div>`;

        if (logEl.querySelector('p')) logEl.innerHTML = '';
        logEl.innerHTML += content;
        logEl.scrollTop = logEl.scrollHeight;
    }

    function clearLog() {
        document.getElementById('epsLog').innerHTML = '<p style="color: #6b7280;">Esperando peticiones...</p>';
    }

    function showMessage(text, type = 'info') {
        const el = document.getElementById('epsMessage');
        el.innerHTML = `<div class="alert alert-${type}">${text}</div>`;
        el.style.display = 'block';
        setTimeout(() => el.style.display = 'none', 8000);
    }

    // === PASO 1: Consultar sedes directamente a la API EPS desde el navegador ===
    async function consultarSedes() {
        const nit = document.getElementById('nit').value.trim();
        if (!nit) {
            showMessage('Ingrese el NIT del prestador.', 'error');
            return;
        }

        const btn = document.getElementById('btnConsultarSedes');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span> Consultando...';

        const url = `${EPS_API}/ConsultarSedesPorNITPrestador?nit_prestador=${encodeURIComponent(nit)}`;

        addLog('request', 'GET ConsultarSedesPorNITPrestador', { url, nit });

        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                },
            });

            addLog('info', 'Response Status', { status: response.status, statusText: response.statusText });

            const text = await response.text();
            addLog('response', 'ConsultarSedesPorNITPrestador RAW', text.substring(0, 2000));

            let data;
            try {
                data = JSON.parse(text);
            } catch(e) {
                addLog('error', 'No es JSON válido, posiblemente Cloudflare challenge', { parseError: e.message });
                showMessage('La API devolvió una respuesta no válida. Es posible que Cloudflare esté bloqueando. Abre primero la API en otra pestaña: ' + EPS_API, 'error');
                return;
            }

            addLog('response', 'ConsultarSedesPorNITPrestador JSON', data);

            // Mapear las sedes al select
            const sedes = Array.isArray(data) ? data : (data.sedes || data.data || []);

            if (sedes.length > 0) {
                const select = document.getElementById('sede');
                select.innerHTML = '';

                addLog('info', 'Estructura de una sede (keys)', Object.keys(sedes[0]));

                sedes.forEach((sede, index) => {
                    const option = document.createElement('option');
                    // Probar diferentes nombres de campo
                    const id = sede.id_sede || sede.IdSede || sede.idSede || sede.id || sede.Id || Object.values(sede)[0];
                    const nombre = sede.nombre_sede || sede.NombreSede || sede.nombreSede || sede.nombre || sede.Nombre || Object.values(sede)[1] || `Sede ${index + 1}`;
                    option.value = id;
                    option.textContent = nombre;
                    if (index === 0) option.selected = true;
                    select.appendChild(option);

                    addLog('info', `Sede ${index}`, { id, nombre, raw: sede });
                });

                document.getElementById('step1').style.display = 'none';
                document.getElementById('step2').style.display = 'block';
                showMessage(`${sedes.length} sede(s) encontrada(s). Seleccione una sede e ingrese la contraseña.`, 'success');
            } else {
                addLog('error', 'No se encontraron sedes', data);
                showMessage('No se encontraron sedes para este NIT.', 'error');
            }
        } catch (error) {
            addLog('error', 'Error en fetch', { message: error.message, stack: error.stack });
            showMessage('Error de conexión: ' + error.message, 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = 'CONSULTAR SEDES';
        }
    }

    // === PASO 2: Login directamente a la API EPS desde el navegador ===
    async function loginEps() {
        const nit = document.getElementById('nit').value.trim();
        const sedeSelect = document.getElementById('sede');
        const sedeId = sedeSelect.value;
        const sedeName = sedeSelect.options[sedeSelect.selectedIndex].textContent;
        const password = document.getElementById('epsPassword').value;

        if (!password) {
            showMessage('Ingrese la contraseña.', 'error');
            return;
        }

        const btn = document.getElementById('btnLogin');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span> Autenticando...';

        const url = `${EPS_API}/ObtenerTokenAutenticacion`;
        const body = {
            sedea: {
                id: sedeId,
                name: sedeName,
            },
            Password: password,
        };

        addLog('request', 'POST ObtenerTokenAutenticacion', { url, body: { sedea: body.sedea, Password: '***' } });

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(body),
            });

            addLog('info', 'Response Status', { status: response.status, statusText: response.statusText });

            const text = await response.text();
            addLog('response', 'ObtenerTokenAutenticacion RAW', text.substring(0, 2000));

            let data;
            try {
                data = JSON.parse(text);
            } catch(e) {
                addLog('error', 'No es JSON válido', { parseError: e.message });
                showMessage('Respuesta no válida de la API.', 'error');
                return;
            }

            addLog('response', 'ObtenerTokenAutenticacion JSON', data);
            addLog('info', 'Keys de respuesta', Object.keys(data));

            // El token viene en data.return
            const token = data.return || data.token || data.Token || null;

            if (token) {
                addLog('info', 'Token obtenido!', { token: token.substring(0, 50) + '...' });

                // Parsear datos adicionales
                let ubicacion = {};
                let parametros = {};
                try {
                    if (data.ubicacion_prestador) ubicacion = JSON.parse(data.ubicacion_prestador);
                    if (data.parametros) parametros = JSON.parse(data.parametros);
                } catch(e) {
                    addLog('warn', 'Error parseando datos adicionales', e.message);
                }

                addLog('info', 'Ubicación prestador', ubicacion);
                addLog('info', 'Tipos documento disponibles', parametros.tiposDocumentoIdentidad?.length + ' tipos');

                // Guardar token en nuestro backend Laravel
                const saveResponse = await fetchApi('{{ route("eps.saveToken") }}', {
                    method: 'POST',
                    body: JSON.stringify({
                        token,
                        nit,
                        sede_id: sedeId,
                        sede_name: sedeName,
                        ubicacion_prestador: ubicacion,
                        parametros: parametros
                    }),
                });

                const saveData = await saveResponse.json();
                addLog('info', 'Token guardado en sesión Laravel', saveData);

                document.getElementById('step2').style.display = 'none';
                document.getElementById('statusCard').style.display = 'block';
                document.getElementById('connectedNit').textContent = nit;
                document.getElementById('connectedToken').textContent = token.substring(0, 80) + '...';
                showMessage('Autenticación exitosa con la EPS.', 'success');
            } else {
                addLog('error', 'No se encontró token en la respuesta', data);
                showMessage(data.message || data.Message || data.error || 'Error al autenticar. Revise las credenciales.', 'error');
            }
        } catch (error) {
            addLog('error', 'Error en fetch', { message: error.message, stack: error.stack });
            showMessage('Error de conexión: ' + error.message, 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = 'INGRESAR A LA EPS';
        }
    }

    async function logoutEps() {
        try {
            await fetchApi('{{ route("eps.logout") }}', { method: 'POST' });
            document.getElementById('statusCard').style.display = 'none';
            document.getElementById('step1').style.display = 'block';
            document.getElementById('nit').value = '';
            document.getElementById('epsPassword').value = '';
            addLog('info', 'Sesión EPS cerrada', {});
            showMessage('Sesión EPS cerrada.', 'info');
        } catch (error) {
            showMessage('Error: ' + error.message, 'error');
        }
    }

    function resetSteps() {
        document.getElementById('step2').style.display = 'none';
        document.getElementById('step1').style.display = 'block';
    }
</script>
@endsection

@section('styles')
<style>
    .eps-wrapper {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
</style>
@endsection
