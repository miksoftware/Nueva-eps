@extends('layouts.app')

@section('title', 'Consultas')

@section('content')
<div class="page-header">
    <h1>Consultas por Cédula</h1>
</div>

{{-- Search by cédula --}}
<div class="card glass" id="searchPanel">
    <div class="card-header">
        <h3 class="card-title">Buscar Cédula</h3>
        <span class="badge badge-consulta">Consulta individual</span>
    </div>

    <div style="display: flex; gap: 0.5rem; align-items: flex-end;">
        <div class="form-group" style="flex: 1; margin-bottom: 0;">
            <label class="form-label" for="searchCedula">Número de Cédula</label>
            <input type="text" id="searchCedula" class="form-control" placeholder="Ej: 1098356925"
                   onkeydown="if(event.key==='Enter') searchCedulaFn()">
        </div>
        <button class="btn btn-primary" id="btnSearch" onclick="searchCedulaFn()" style="height: 46px;">
            Buscar
        </button>
    </div>

    {{-- Search result detail --}}
    <div id="searchResult" style="display: none; margin-top: 1.5rem;"></div>
</div>

{{-- Upload CSV (Admin only) --}}
@if(auth()->user()->isAdmin())
<div class="card glass" id="uploadPanel">
    <div class="card-header">
        <h3 class="card-title">Subir CSV con Cédulas</h3>
        <span class="badge badge-consulta">Lote masivo</span>
    </div>

    @unless(session('eps_token'))
    <div class="alert alert-error" style="margin-bottom: 1rem;">
        Debe iniciar sesión en las <a href="{{ route('eps.credentials') }}" style="color: #60a5fa; text-decoration: underline;">Credenciales EPS</a> antes de subir archivos.
    </div>
    @else

    <p style="color: #9ca3af; margin-bottom: 1rem; font-size: 0.9rem;">
        Suba un archivo CSV con una sola columna de números de cédula (CC). Una cédula por línea.
    </p>

    <div class="upload-zone" id="dropZone">
        <input type="file" id="csvFile" accept=".csv,.txt" style="display: none;">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="1.5">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
            <polyline points="17 8 12 3 7 8"/>
            <line x1="12" y1="3" x2="12" y2="15"/>
        </svg>
        <p style="color: #a0a0b8; margin-top: 0.5rem;">Arrastra un archivo CSV aquí o <span style="color: #3b82f6; cursor: pointer;" onclick="document.getElementById('csvFile').click()">haz clic para seleccionar</span></p>
        <p id="fileName" style="color: #34d399; margin-top: 0.5rem; display: none;"></p>
    </div>

    <button type="button" class="btn btn-primary btn-block" id="btnUpload" onclick="uploadCSV()" style="margin-top: 1rem;" disabled>
        SUBIR Y PROCESAR
    </button>
    @endunless
</div>
@endif

@if(auth()->user()->isAdmin())
{{-- Progress panel --}}
<div class="card glass" id="progressPanel" style="display: none;">
    <div class="card-header">
        <h3 class="card-title">Procesando Consultas</h3>
        <span class="badge badge-consulta" id="progressBadge">0 / 0</span>
    </div>

    <div class="progress-bar-container">
        <div class="progress-bar" id="progressBar" style="width: 0%"></div>
    </div>

    <div style="display: flex; gap: 1rem; margin-top: 1rem; flex-wrap: wrap;">
        <div class="stat-box stat-total">
            <span class="stat-label">Total</span>
            <span class="stat-value" id="statTotal">0</span>
        </div>
        <div class="stat-box stat-ok">
            <span class="stat-label">Completados</span>
            <span class="stat-value" id="statOk">0</span>
        </div>
        <div class="stat-box stat-err">
            <span class="stat-label">Errores</span>
            <span class="stat-value" id="statErr">0</span>
        </div>
        <div class="stat-box stat-pending">
            <span class="stat-label">Pendientes</span>
            <span class="stat-value" id="statPending">0</span>
        </div>
    </div>

    <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
        <button class="btn btn-danger btn-sm" id="btnStop" onclick="stopProcessing()">Detener</button>
        <button class="btn btn-warning btn-sm" id="btnNewBatch" onclick="newBatch()" style="display:none;">Nueva consulta</button>
    </div>
</div>

{{-- Results table --}}
<div class="card glass" id="resultsPanel" style="display: none;">
    <div class="card-header">
        <h3 class="card-title">Resultados</h3>
        <div style="display: flex; gap: 0.5rem;">
            <button class="btn btn-success btn-sm" id="btnExport" onclick="exportResults()">Descargar CSV</button>
        </div>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Documento</th>
                    <th>Nombre Completo</th>
                    <th>Sexo</th>
                    <th>Celular</th>
                    <th>Tel 1</th>
                    <th>Tel 2</th>
                    <th>Correo</th>
                    <th>Tipo Afiliado</th>
                    <th>Régimen</th>
                    <th>Categoría</th>
                    <th>IPS Primaria</th>
                    <th>Departamento</th>
                    <th>Municipio</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody id="resultsBody">
            </tbody>
        </table>
    </div>
</div>
@endif {{-- end admin batch section --}}

@if(auth()->user()->isAdmin() && $lotes->isNotEmpty())
<div class="card glass">
    <div class="card-header">
        <h3 class="card-title">Lotes Anteriores</h3>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Lote</th>
                    <th>Fecha</th>
                    <th>Total</th>
                    <th>Completados</th>
                    <th>Errores</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lotes as $lote)
                <tr>
                    <td style="font-family: monospace; font-size: 0.8rem;">{{ $lote->lote }}</td>
                    <td>{{ \Carbon\Carbon::parse($lote->fecha)->format('d/m/Y H:i') }}</td>
                    <td>{{ $lote->total }}</td>
                    <td style="color: #34d399;">{{ $lote->completados }}</td>
                    <td style="color: #f87171;">{{ $lote->errores }}</td>
                    <td class="actions">
                        <a href="{{ route('consultas.export', $lote->lote) }}" class="btn btn-success btn-sm">CSV</a>
                        <button class="btn btn-primary btn-sm" onclick="loadLote('{{ $lote->lote }}')">Ver</button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Detail modal --}}
<div id="detailModal" class="detail-modal" style="display:none;" onclick="if(event.target===this)this.style.display='none'">
    <div class="detail-modal-content glass-strong">
        <div class="card-header" style="margin-bottom: 1.2rem;">
            <h3 class="card-title" id="detailTitle">Información del Afiliado</h3>
            <button class="btn btn-danger btn-sm" onclick="document.getElementById('detailModal').style.display='none'">Cerrar</button>
        </div>
        <div id="detailBody"></div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    const EPS_API = '{{ config("services.eps.api_url") }}';
    const EPS_TOKEN = '{{ session("eps_token", "") }}';
    const SAVE_RESULT_URL = '{{ route("consultas.saveResult") }}';
    const IS_ADMIN = {{ auth()->user()->isAdmin() ? 'true' : 'false' }};
    @if(auth()->user()->isAdmin())
    const UPLOAD_URL = '{{ route("consultas.upload") }}';
    @endif

    let currentLote = null;
    let processing = false;
    let stopRequested = false;

    // === DANE Codes: Departamentos de Colombia ===
    const DEPTOS = {
        '05':'ANTIOQUIA','08':'ATLÁNTICO','11':'BOGOTÁ D.C.','13':'BOLÍVAR','15':'BOYACÁ',
        '17':'CALDAS','18':'CAQUETÁ','19':'CAUCA','20':'CESAR','23':'CÓRDOBA',
        '25':'CUNDINAMARCA','27':'CHOCÓ','41':'HUILA','44':'LA GUAJIRA','47':'MAGDALENA',
        '50':'META','52':'NARIÑO','54':'NORTE DE SANTANDER','63':'QUINDÍO','66':'RISARALDA',
        '68':'SANTANDER','70':'SUCRE','73':'TOLIMA','76':'VALLE DEL CAUCA','81':'ARAUCA',
        '85':'CASANARE','86':'PUTUMAYO','88':'SAN ANDRÉS','91':'AMAZONAS','94':'GUAINÍA',
        '95':'GUAVIARE','97':'VAUPÉS','99':'VICHADA'
    };

    // Municipios principales (DANE code => nombre)
    const MUNICIPIOS = {
        '05001':'MEDELLÍN','05088':'BELLO','05266':'ENVIGADO','05360':'ITAGÜÍ','05631':'SABANETA',
        '05615':'RIONEGRO','05045':'APARTADÓ','05154':'CAUCASIA','05837':'TURBO','05172':'CHIGORODÓ',
        '05079':'BARBOSA','05212':'COPACABANA','05308':'GIRARDOTA','05380':'LA ESTRELLA',
        '05376':'LA CEJA','05440':'MARINILLA','05318':'GUAMAL','05321':'GUATAPÉ',
        '08001':'BARRANQUILLA','08758':'SOLEDAD','08433':'MALAMBO','08296':'GALAPA','08638':'SABANALARGA',
        '11001':'BOGOTÁ D.C.',
        '13001':'CARTAGENA','13430':'MAGANGUÉ','13657':'SAN JUAN NEPOMUCENO','13836':'TURBACO',
        '15001':'TUNJA','15238':'DUITAMA','15759':'SOGAMOSO','15176':'CHIQUINQUIRÁ','15572':'PUERTO BOYACÁ',
        '17001':'MANIZALES','17174':'CHINCHINÁ','17380':'LA DORADA','17042':'ANSERMA',
        '18001':'FLORENCIA','18753':'SAN VICENTE DEL CAGUÁN',
        '19001':'POPAYÁN','19698':'SANTANDER DE QUILICHAO','19573':'PUERTO TEJADA','19142':'CALOTO',
        '20001':'VALLEDUPAR','20011':'AGUACHICA','20013':'AGUSTÍN CODAZZI','20060':'BOSCONIA',
        '23001':'MONTERÍA','23417':'LORICA','23466':'MONTELÍBANO','23555':'PLANETA RICA','23660':'SAHAGÚN',
        '25001':'AGUA DE DIOS','25175':'CHÍA','25269':'FACATATIVÁ','25290':'FUSAGASUGÁ','25307':'GIRARDOT',
        '25430':'MADRID','25473':'MOSQUERA','25754':'SOACHA','25758':'SOPÓ','25899':'ZIPAQUIRÁ',
        '25817':'TOCANCIPÁ','25126':'CAJICÁ','25740':'SIBATÉ',
        '27001':'QUIBDÓ','27361':'ISTMINA',
        '41001':'NEIVA','41551':'PITALITO','41298':'GARZÓN','41524':'PALERMO','41132':'CAMPOALEGRE',
        '41396':'LA PLATA','41306':'GIGANTE','41016':'AIPE',
        '44001':'RIOHACHA','44430':'MAICAO','44078':'BARRANCAS','44847':'URIBIA',
        '47001':'SANTA MARTA','47189':'CIÉNAGA','47288':'FUNDACIÓN','47245':'EL BANCO','47555':'PLATO',
        '50001':'VILLAVICENCIO','50006':'ACACÍAS','50313':'GRANADA','50573':'PUERTO LÓPEZ',
        '50226':'CUMARAL','50689':'SAN MARTÍN','50318':'GUAMAL',
        '52001':'PASTO','52356':'IPIALES','52835':'TUMACO','52838':'TÚQUERRES',
        '54001':'CÚCUTA','54498':'OCAÑA','54518':'PAMPLONA','54874':'VILLA DEL ROSARIO','54405':'LOS PATIOS',
        '54261':'EL ZULIA','54245':'EL CARMEN',
        '63001':'ARMENIA','63130':'CALARCÁ','63470':'MONTENEGRO','63401':'LA TEBAIDA',
        '66001':'PEREIRA','66170':'DOSQUEBRADAS','66682':'SANTA ROSA DE CABAL','66400':'LA VIRGINIA',
        '68001':'BUCARAMANGA','68276':'FLORIDABLANCA','68547':'PIEDECUESTA','68307':'GIRÓN',
        '68081':'BARRANCABERMEJA','68679':'SAN GIL','68432':'MÁLAGA','68689':'SAN VICENTE DE CHUCURÍ',
        '68406':'LEBRIJA','68190':'CIMITARRA','68575':'PUERTO WILCHES','68655':'SABANA DE TORRES',
        '68755':'SOCORRO','68077':'BARBOSA','68079':'BARICHARA','68229':'CURITÍ',
        '70001':'SINCELEJO','70215':'COROZAL','70708':'SAN ONOFRE','70702':'SAN MARCOS',
        '73001':'IBAGUÉ','73268':'ESPINAL','73449':'MELGAR','73275':'FLANDES','73349':'HONDA',
        '73411':'LÍBANO','73168':'CHAPARRAL','73443':'MARIQUITA','73283':'FRESNO',
        '76001':'CALI','76109':'BUENAVENTURA','76111':'BUGA','76520':'PALMIRA','76834':'TULUÁ',
        '76147':'CARTAGO','76364':'JAMUNDÍ','76130':'CANDELARIA','76122':'CAICEDONIA',
        '76736':'SEVILLA','76248':'EL CERRITO','76306':'GINEBRA','76275':'FLORIDA','76892':'YUMBO',
        '81001':'ARAUCA','81736':'SARAVENA','81794':'TAME','81065':'ARAUQUITA',
        '85001':'YOPAL','85010':'AGUAZUL','85250':'PAZ DE ARIPORO','85440':'VILLANUEVA',
        '85410':'TAURAMENA','85162':'MONTERREY',
        '86001':'MOCOA','86568':'PUERTO ASÍS','86573':'LEGUÍZAMO',
        '88001':'SAN ANDRÉS','88564':'PROVIDENCIA',
        '91001':'LETICIA','91540':'PUERTO NARIÑO',
        '94001':'INÍRIDA',
        '95001':'SAN JOSÉ DEL GUAVIARE',
        '97001':'MITÚ',
        '99001':'PUERTO CARREÑO'
    };

    function deptName(code) {
        if (!code) return null;
        const c = String(code).padStart(2, '0');
        return DEPTOS[c] || code;
    }

    function muniName(deptCode, muniCode) {
        if (!muniCode) return null;
        if (!deptCode) return muniCode;
        const d = String(deptCode).padStart(2, '0');
        const m = String(muniCode).padStart(3, '0');
        const full = d + m;
        return MUNICIPIOS[full] || muniCode;
    }

    // === File Upload  ===
    const dropZone = document.getElementById('dropZone');
    const csvFileInput = document.getElementById('csvFile');

    if (dropZone) {
        dropZone.addEventListener('click', () => csvFileInput.click());
        dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('drag-over'); });
        dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('drag-over');
            if (e.dataTransfer.files.length) {
                csvFileInput.files = e.dataTransfer.files;
                fileSelected();
            }
        });

        csvFileInput.addEventListener('change', fileSelected);
    }

    function fileSelected() {
        const file = csvFileInput.files[0];
        if (file) {
            document.getElementById('fileName').textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
            document.getElementById('fileName').style.display = 'block';
            document.getElementById('btnUpload').disabled = false;
        }
    }

    // === SEARCH by cédula ===
    async function searchCedulaFn() {
        const cedula = document.getElementById('searchCedula').value.trim();
        if (!cedula) return;

        const btn = document.getElementById('btnSearch');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span>';

        const resultDiv = document.getElementById('searchResult');
        resultDiv.style.display = 'none';

        try {
            // Call DB and EPS API in parallel
            const [dbResp, liveResult] = await Promise.all([
                fetchApi(`/consultas/search/${cedula}`).then(r => r.json()).catch(() => ({success: false})),
                consultarCedula(cedula),
            ]);

            let html = '';

            // Live data card
            if (liveResult.success) {
                const d = liveResult.data;
                const nombre = [d.primer_nombre, d.segundo_nombre, d.primer_apellido, d.segundo_apellido].filter(Boolean).join(' ');
                html += `
                <div style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 12px; padding: 1.2rem; margin-bottom: 1rem;">
                    <h4 style="color: #34d399; margin-bottom: 1rem;">Datos actuales de la EPS</h4>
                    <div class="detail-grid">
                        <div class="detail-item"><span class="detail-label">Documento</span><span class="detail-value">CC ${cedula}</span></div>
                        <div class="detail-item"><span class="detail-label">Nombre</span><span class="detail-value">${nombre}</span></div>
                        <div class="detail-item"><span class="detail-label">Sexo</span><span class="detail-value">${d.sexo || '-'}</span></div>
                        <div class="detail-item"><span class="detail-label">Celular</span><span class="detail-value">${d.celular || '-'}</span></div>
                        <div class="detail-item"><span class="detail-label">Teléfono 1</span><span class="detail-value">${d.telefono1 || '-'}</span></div>
                        <div class="detail-item"><span class="detail-label">Teléfono 2</span><span class="detail-value">${d.telefono2 || '-'}</span></div>
                        <div class="detail-item full"><span class="detail-label">Correo</span><span class="detail-value">${d.correo_electronico || '-'}</span></div>
                        <div class="detail-item"><span class="detail-label">Tipo Afiliado</span><span class="detail-value">${d.tipo_afiliado || '-'}</span></div>
                        <div class="detail-item"><span class="detail-label">Régimen</span><span class="detail-value">${d.regimen || '-'}</span></div>
                        <div class="detail-item"><span class="detail-label">Categoría</span><span class="detail-value">${d.categoria || '-'}</span></div>
                        <div class="detail-item full"><span class="detail-label">IPS Primaria</span><span class="detail-value">${d.ips_primaria || '-'}</span></div>
                        <div class="detail-item"><span class="detail-label">Departamento</span><span class="detail-value">${d.departamento || '-'}</span></div>
                        <div class="detail-item"><span class="detail-label">Municipio</span><span class="detail-value">${d.municipio || '-'}</span></div>
                    </div>
                    <button class="btn btn-sm btn-primary" style="margin-top: 1rem;" onclick='showDetailModal(${JSON.stringify(d).replace(/'/g, "&#39;")})'>Ver detalle</button>
                </div>`;
                logMsg('success', `CC ${cedula} — ${nombre}`);
            } else {
                html += `<div class="alert alert-error" style="margin-bottom:1rem;">API EPS: ${liveResult.error}</div>`;
                logMsg('error', `CC ${cedula} — ${liveResult.error}`);
            }

            // Historical data from DB
            if (dbResp.success && dbResp.consultas && dbResp.consultas.length > 0) {
                html += `<h4 style="color: #f59e0b; margin: 1rem 0 0.5rem;">Historial en base de datos (${dbResp.consultas.length} registro(s))</h4>`;
                dbResp.consultas.forEach(c => {
                    const nombre = [c.primer_nombre, c.segundo_nombre, c.primer_apellido, c.segundo_apellido].filter(Boolean).join(' ') || '-';
                    const fecha = new Date(c.created_at).toLocaleString();
                    html += `
                    <div style="background: rgba(245, 158, 11, 0.06); border: 1px solid rgba(245, 158, 11, 0.15); border-radius: 10px; padding: 0.8rem; margin-bottom: 0.5rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; flex-wrap: wrap; gap: 0.5rem;">
                            <span style="font-size: 0.8rem; color: #fbbf24;">Lote: ${c.lote}</span>
                            <span style="font-size: 0.75rem; color: #9ca3af;">${fecha}</span>
                            <span class="badge badge-${c.estado === 'completado' ? 'success' : 'danger'}">${c.estado}</span>
                        </div>
                        <div style="font-size: 0.85rem; color: #d1d5db;">
                            ${nombre} | ${c.tipo_afiliado || '-'} | ${c.regimen || '-'} | ${c.departamento || '-'} / ${c.municipio || '-'}
                        </div>
                    </div>`;
                });
            }

            resultDiv.innerHTML = html || '<div class="alert alert-info">Sin resultados.</div>';
            resultDiv.style.display = 'block';
        } catch(e) {
            resultDiv.innerHTML = `<div class="alert alert-error">Error: ${e.message}</div>`;
            resultDiv.style.display = 'block';
        } finally {
            btn.disabled = false;
            btn.innerHTML = 'Buscar';
        }
    }

    function showDetailModal(d) {
        const nombre = [d.primer_nombre, d.segundo_nombre, d.primer_apellido, d.segundo_apellido].filter(Boolean).join(' ') || '-';
        const body = document.getElementById('detailBody');

        body.innerHTML = `
            {{-- Person header --}}
            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid rgba(255,255,255,0.08);">
                <div style="width: 56px; height: 56px; border-radius: 50%; background: linear-gradient(135deg, #3b82f6, #8b5cf6); display: flex; align-items: center; justify-content: center; font-size: 1.4rem; font-weight: 700; color: #fff; flex-shrink: 0;">
                    ${(d.primer_nombre || '?')[0]}${(d.primer_apellido || '?')[0]}
                </div>
                <div>
                    <div style="font-size: 1.2rem; font-weight: 700; color: #fff;">${nombre}</div>
                    <div style="font-size: 0.85rem; color: #9ca3af;">CC ${d.numero_documento || '-'}</div>
                </div>
            </div>

            {{-- Section: Datos Personales --}}
            <div class="modal-section">
                <div class="modal-section-title">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    Datos Personales
                </div>
                <div class="detail-grid modal-grid">
                    <div class="detail-item"><span class="detail-label">Sexo</span><span class="detail-value">${d.sexo === 'M' ? 'Masculino' : d.sexo === 'F' ? 'Femenino' : (d.sexo || '-')}</span></div>
                    <div class="detail-item"><span class="detail-label">Tipo Documento</span><span class="detail-value">${d.tipo_documento || 'CC'}</span></div>
                </div>
            </div>

            {{-- Section: Contacto --}}
            <div class="modal-section">
                <div class="modal-section-title">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
                    Contacto
                </div>
                <div class="detail-grid modal-grid">
                    <div class="detail-item"><span class="detail-label">Celular</span><span class="detail-value">${d.celular || '-'}</span></div>
                    <div class="detail-item"><span class="detail-label">Teléfono 1</span><span class="detail-value">${d.telefono1 || '-'}</span></div>
                    <div class="detail-item"><span class="detail-label">Teléfono 2</span><span class="detail-value">${d.telefono2 || '-'}</span></div>
                    <div class="detail-item full"><span class="detail-label">Correo Electrónico</span><span class="detail-value">${d.correo_electronico || '-'}</span></div>
                </div>
            </div>

            {{-- Section: Afiliación --}}
            <div class="modal-section">
                <div class="modal-section-title">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    Afiliación
                </div>
                <div class="detail-grid modal-grid">
                    <div class="detail-item"><span class="detail-label">Tipo Afiliado</span><span class="detail-value modal-highlight">${d.tipo_afiliado || '-'}</span></div>
                    <div class="detail-item"><span class="detail-label">Régimen</span><span class="detail-value modal-highlight">${d.regimen || '-'}</span></div>
                    <div class="detail-item"><span class="detail-label">Categoría</span><span class="detail-value">${d.categoria || '-'}</span></div>
                    <div class="detail-item full"><span class="detail-label">IPS Primaria</span><span class="detail-value" style="color: #60a5fa;">${d.ips_primaria || '-'}</span></div>
                </div>
            </div>

            {{-- Section: Ubicación --}}
            <div class="modal-section" style="border-bottom: none; padding-bottom: 0;">
                <div class="modal-section-title">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    Ubicación
                </div>
                <div class="detail-grid modal-grid">
                    <div class="detail-item"><span class="detail-label">Departamento</span><span class="detail-value">${d.departamento || '-'}</span></div>
                    <div class="detail-item"><span class="detail-label">Municipio</span><span class="detail-value">${d.municipio || '-'}</span></div>
                </div>
            </div>
        `;

        document.getElementById('detailTitle').textContent = nombre;
        document.getElementById('detailModal').style.display = 'flex';
    }

    // === Upload CSV ===
    async function uploadCSV() {
        const file = csvFileInput.files[0];
        if (!file) return;

        const btn = document.getElementById('btnUpload');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span> Subiendo...';

        const formData = new FormData();
        formData.append('csv_file', file);

        try {
            const response = await fetch(UPLOAD_URL, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: formData,
            });

            const data = await response.json();

            if (!data.success) {
                alert(data.message || 'Error al subir archivo.');
                btn.disabled = false;
                btn.innerHTML = 'SUBIR Y PROCESAR';
                return;
            }

            logMsg('info', `Lote creado: ${data.lote} — ${data.total} cédulas`);
            currentLote = data.lote;

            document.getElementById('uploadPanel').style.display = 'none';
            document.getElementById('progressPanel').style.display = 'block';
            document.getElementById('resultsPanel').style.display = 'block';
            document.getElementById('resultsBody').innerHTML = '';

            startProcessing(data.cedulas);

        } catch (err) {
            alert('Error: ' + err.message);
            btn.disabled = false;
            btn.innerHTML = 'SUBIR Y PROCESAR';
        }
    }

    // === Process each cédula ===
    async function startProcessing(cedulas) {
        processing = true;
        stopRequested = false;
        const total = cedulas.length;

        updateStats(total, 0, 0, total);
        document.getElementById('btnStop').style.display = '';
        document.getElementById('btnNewBatch').style.display = 'none';

        let ok = 0, errors = 0;

        for (let i = 0; i < cedulas.length; i++) {
            if (stopRequested) {
                logMsg('warn', 'Procesamiento detenido por el usuario.');
                break;
            }

            const cedula = cedulas[i];

            logMsg('request', `[${i+1}/${total}] Consultando CC ${cedula}...`);

            try {
                const result = await consultarCedula(cedula);

                if (result.success) {
                    ok++;
                    addResultRow(i + 1, result.data);
                    await saveResultToBackend(cedula, 'completado', result.data);
                    logMsg('success', `[${i+1}/${total}] CC ${cedula} — ${result.data.primer_nombre} ${result.data.primer_apellido}`);
                } else {
                    errors++;
                    addResultRowError(i + 1, cedula, result.error);
                    await saveResultToBackend(cedula, 'error', null, result.error);
                    logMsg('error', `[${i+1}/${total}] CC ${cedula} — ${result.error}`);
                }
            } catch (err) {
                errors++;
                addResultRowError(i + 1, cedula, err.message);
                await saveResultToBackend(cedula, 'error', null, err.message);
                logMsg('error', `[${i+1}/${total}] CC ${cedula} — ${err.message}`);
            }

            updateStats(total, ok, errors, total - ok - errors);

            // Small delay to not overwhelm the API
            if (i < cedulas.length - 1) {
                await delay(800);
            }
        }

        processing = false;
        document.getElementById('btnStop').style.display = 'none';
        document.getElementById('btnNewBatch').style.display = '';
        logMsg('info', `Procesamiento finalizado. OK: ${ok}, Errores: ${errors}`);
    }

    // === Consultar cédula en la API EPS ===
    async function consultarCedula(cedula) {
        const url1 = `${EPS_API}/ObtenerInformacionAfiliado?tipoDocumento=CC&numeroDocumento=${cedula}`;
        const url2 = `${EPS_API}/ConsultarPacienteTipoNumeroDocumento?tipoDocumento=CC&numeroDocumento=${cedula}`;

        const headers = {
            'Accept': 'application/json',
            'Authorization': `Bearer ${EPS_TOKEN}`,
        };

        let afiliadoData = null;
        let pacienteData = null;

        // Both requests in parallel
        const [resp1, resp2] = await Promise.allSettled([
            fetch(url1, { headers }).then(r => r.ok ? r.text() : null),
            fetch(url2, { headers }).then(r => r.ok ? r.text() : null),
        ]);

        if (resp1.status === 'fulfilled' && resp1.value) {
            try { afiliadoData = JSON.parse(resp1.value); } catch(e) {}
        }
        if (resp2.status === 'fulfilled' && resp2.value) {
            try { pacienteData = JSON.parse(resp2.value); } catch(e) {}
        }

        if (!afiliadoData && !pacienteData) {
            return { success: false, error: 'No se obtuvo respuesta de la API' };
        }

        // Flatten nested objects recursively into a single-level object
        function flattenObj(obj, result = {}) {
            if (!obj || typeof obj !== 'object') return result;
            for (const [key, val] of Object.entries(obj)) {
                if (val && typeof val === 'object' && !Array.isArray(val)) {
                    flattenObj(val, result);
                } else {
                    if (result[key] === undefined) result[key] = val;
                }
            }
            return result;
        }

        // Flatten both sources so all nested keys are at top level
        const flat1 = flattenObj(afiliadoData);
        const flat2 = flattenObj(pacienteData);

        // Log flattened keys for debugging
        logMsg('info', `Afiliado flat keys: ${Object.keys(flat1).join(', ')}`);
        logMsg('info', `Paciente flat keys: ${Object.keys(flat2).join(', ')}`);

        function extract(keys) {
            for (const key of keys) {
                if (flat1[key] !== undefined && flat1[key] !== null && flat1[key] !== '') return String(flat1[key]);
            }
            for (const key of keys) {
                if (flat2[key] !== undefined && flat2[key] !== null && flat2[key] !== '') return String(flat2[key]);
            }
            return null;
        }

        // Extract raw dept/muni codes
        const rawDepto = extract(['depto_residencia', 'departamento', 'codigoDepartamento',
            'codDepartamento', 'cod_departamento', 'dpto']);
        const rawMuni = extract(['municipio_residencia', 'municipio', 'codigoMunicipio',
            'codMunicipio', 'cod_municipio', 'mpio']);

        // Resolve DANE codes to names
        let deptoFinal = rawDepto;
        let muniFinal = rawMuni;

        if (rawDepto && /^\d{1,2}$/.test(rawDepto)) {
            deptoFinal = deptName(rawDepto);
            if (rawMuni && /^\d{1,3}$/.test(rawMuni)) {
                muniFinal = muniName(rawDepto, rawMuni);
            }
        }

        // desc_tipo_afiliacion = "COTIZANTE" is more useful than tipo_afiliado = "1"
        const tipoAfiliadoRaw = extract(['desc_tipo_afiliacion', 'tipoAfiliado', 'tipo_afiliado']);

        const extracted = {
            tipo_documento: 'CC',
            numero_documento: cedula,
            primer_nombre: extract(['primer_nombre', 'nombres', 'primerNombre', 'PrimerNombre']),
            segundo_nombre: extract(['segundo_nombre', 'segundoNombre', 'SegundoNombre']),
            primer_apellido: extract(['primer_apellido', 'primerApellido', 'PrimerApellido']),
            segundo_apellido: extract(['segundo_apellido', 'segundoApellido', 'SegundoApellido']),
            sexo: extract(['sexo', 'Sexo', 'genero']),
            celular: extract(['telefono_movil', 'celular', 'telefonoCelular', 'telefono_celular']),
            telefono1: extract(['telefono_residencia', 'telefono1', 'telefonoFijo', 'telefono']),
            telefono2: extract(['telefono2', 'telefonoFijo2']),
            correo_electronico: extract(['correo_electronico', 'email', 'correoElectronico', 'correo']),
            tipo_afiliado: tipoAfiliadoRaw,
            regimen: extract(['tipo_regimen', 'regimen', 'tipoRegimen', 'nombreRegimen']),
            categoria: extract(['categoria', 'Categoria', 'categoriaAfiliacion']),
            ips_primaria: extract(['nombre_prestador', 'nombreIps', 'ipsPrimaria', 'ips_primaria',
                'nombreIpsPrimaria', 'ips']),
            departamento: deptoFinal,
            municipio: muniFinal,
            respuesta_afiliado: JSON.stringify(afiliadoData),
            respuesta_paciente: JSON.stringify(pacienteData),
        };

        // Check if we got at least a name
        if (!extracted.primer_nombre && !extracted.primer_apellido) {
            const errMsg = src1.message || src1.Message || src1.error || src2.message || src2.Message || 'Afiliado no encontrado';
            return { success: false, error: typeof errMsg === 'string' ? errMsg : JSON.stringify(errMsg) };
        }

        return { success: true, data: extracted };
    }

    async function saveResultToBackend(cedula, estado, data, errorMsg) {
        try {
            await fetchApi(SAVE_RESULT_URL, {
                method: 'POST',
                body: JSON.stringify({
                    lote: currentLote,
                    numero_documento: cedula,
                    estado: estado,
                    data: data,
                    error_message: errorMsg,
                }),
            });
        } catch(e) {
            // silent fail for backend save
        }
    }

    // === UI Helpers ===
    function updateStats(total, ok, errors, pending) {
        const el = document.getElementById('statTotal');
        if (!el) return;
        el.textContent = total;
        document.getElementById('statOk').textContent = ok;
        document.getElementById('statErr').textContent = errors;
        document.getElementById('statPending').textContent = pending;
        document.getElementById('progressBadge').textContent = `${ok + errors} / ${total}`;

        const pct = total > 0 ? ((ok + errors) / total * 100) : 0;
        document.getElementById('progressBar').style.width = pct + '%';
    }

    function addResultRow(idx, d) {
        const tbody = document.getElementById('resultsBody');
        if (!tbody) return;
        const nombre = [d.primer_nombre, d.segundo_nombre, d.primer_apellido, d.segundo_apellido].filter(Boolean).join(' ');

        const tr = document.createElement('tr');
        tr.style.cursor = 'pointer';
        tr.title = 'Clic para ver detalle';
        // Store data in closure for modal
        const rowData = {...d};
        tr.onclick = () => showDetailModal(rowData);
        tr.innerHTML = `
            <td>${idx}</td>
            <td>${d.numero_documento}</td>
            <td>${nombre}</td>
            <td>${d.sexo || '-'}</td>
            <td>${d.celular || '-'}</td>
            <td>${d.telefono1 || '-'}</td>
            <td>${d.telefono2 || '-'}</td>
            <td style="font-size:0.8rem;">${d.correo_electronico || '-'}</td>
            <td>${d.tipo_afiliado || '-'}</td>
            <td>${d.regimen || '-'}</td>
            <td>${d.categoria || '-'}</td>
            <td style="font-size:0.78rem;">${d.ips_primaria || '-'}</td>
            <td>${d.departamento || '-'}</td>
            <td>${d.municipio || '-'}</td>
            <td><span class="badge badge-success">OK</span></td>
        `;
        tbody.appendChild(tr);
    }

    function addResultRowError(idx, cedula, error) {
        const tbody = document.getElementById('resultsBody');
        if (!tbody) return;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${idx}</td>
            <td>${cedula}</td>
            <td colspan="12" style="color: #f87171; font-size: 0.85rem;">${error}</td>
            <td><span class="badge badge-danger">Error</span></td>
        `;
        tbody.appendChild(tr);
    }

    function stopProcessing() {
        stopRequested = true;
    }

    function newBatch() {
        const uploadPanel = document.getElementById('uploadPanel');
        if (!uploadPanel) return;
        uploadPanel.style.display = '';
        document.getElementById('progressPanel').style.display = 'none';
        document.getElementById('resultsPanel').style.display = 'none';
        document.getElementById('btnUpload').disabled = true;
        document.getElementById('btnUpload').innerHTML = 'SUBIR Y PROCESAR';
        document.getElementById('fileName').style.display = 'none';
        csvFileInput.value = '';
        currentLote = null;
    }

    function exportResults() {
        if (currentLote) {
            window.location.href = `/consultas/${currentLote}/export`;
        }
    }

    async function loadLote(lote) {
        try {
            const resp = await fetchApi(`/consultas/${lote}/status`);
            const data = await resp.json();

            if (!data.success) return;

            currentLote = lote;
            document.getElementById('uploadPanel').style.display = 'none';
            document.getElementById('progressPanel').style.display = 'block';
            document.getElementById('resultsPanel').style.display = 'block';
            document.getElementById('btnStop').style.display = 'none';
            document.getElementById('btnNewBatch').style.display = '';

            updateStats(data.total, data.completados, data.errores, data.pendientes);

            const tbody = document.getElementById('resultsBody');
            tbody.innerHTML = '';

            data.consultas.forEach((c, i) => {
                if (c.estado === 'completado') {
                    addResultRow(i + 1, c);
                } else if (c.estado === 'error') {
                    addResultRowError(i + 1, c.numero_documento, c.error || 'Error');
                } else {
                    addResultRowError(i + 1, c.numero_documento, 'Pendiente');
                }
            });
        } catch(e) {
            alert('Error al cargar lote: ' + e.message);
        }
    }

    function logMsg(type, msg) {
        // Silent - no UI log panel
    }

    function delay(ms) {
        return new Promise(r => setTimeout(r, ms));
    }
</script>
@endsection

@section('styles')
<style>
    .upload-zone {
        border: 2px dashed rgba(59, 130, 246, 0.3);
        border-radius: 12px;
        padding: 2rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .upload-zone:hover,
    .upload-zone.drag-over {
        border-color: #3b82f6;
        background: rgba(59, 130, 246, 0.05);
    }

    .progress-bar-container {
        width: 100%;
        height: 8px;
        background: rgba(255,255,255,0.08);
        border-radius: 4px;
        overflow: hidden;
    }

    .progress-bar {
        height: 100%;
        background: linear-gradient(90deg, #3b82f6, #10b981);
        border-radius: 4px;
        transition: width 0.3s ease;
    }

    .stat-box {
        flex: 1;
        min-width: 100px;
        padding: 0.8rem;
        border-radius: 10px;
        text-align: center;
    }

    .stat-label {
        display: block;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 0.3rem;
    }

    .stat-value {
        display: block;
        font-size: 1.5rem;
        font-weight: 700;
    }

    .stat-total {
        background: rgba(59, 130, 246, 0.1);
        border: 1px solid rgba(59, 130, 246, 0.2);
        color: #60a5fa;
    }

    .stat-ok {
        background: rgba(16, 185, 129, 0.1);
        border: 1px solid rgba(16, 185, 129, 0.2);
        color: #34d399;
    }

    .stat-err {
        background: rgba(239, 68, 68, 0.1);
        border: 1px solid rgba(239, 68, 68, 0.2);
        color: #f87171;
    }

    .stat-pending {
        background: rgba(245, 158, 11, 0.1);
        border: 1px solid rgba(245, 158, 11, 0.2);
        color: #fbbf24;
    }

    .detail-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.8rem;
    }

    .detail-grid .full {
        grid-column: 1 / -1;
    }

    .detail-item {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .detail-label {
        font-size: 0.72rem;
        color: #9ca3af;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .detail-value {
        font-size: 0.9rem;
        color: #e0e0e0;
        font-weight: 500;
    }

    .detail-modal {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.6);
        backdrop-filter: blur(4px);
        z-index: 1000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem;
    }

    .detail-modal-content {
        max-width: 600px;
        width: 100%;
        max-height: 85vh;
        overflow-y: auto;
        padding: 2rem;
        border-radius: 16px;
    }

    .modal-section {
        padding-bottom: 1.2rem;
        margin-bottom: 1.2rem;
        border-bottom: 1px solid rgba(255,255,255,0.06);
    }

    .modal-section-title {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.85rem;
        font-weight: 600;
        color: #9ca3af;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 0.8rem;
    }

    .modal-grid {
        grid-template-columns: 1fr 1fr;
    }

    .modal-highlight {
        color: #fbbf24 !important;
        font-weight: 600;
    }

    @media (max-width: 768px) {
        .detail-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endsection
