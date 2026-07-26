/**
 * admin.js
 * Lógica para el panel de administración, buscador de catálogos y bloqueo de envíos
 */

let envioModal;
let revistaModal;
let ejemplarModal;
let personaModal;
let agenciaModal;
let credentialsModal;

// Almacenes en memoria para filtros en tiempo real
let allEnvios = [];
let allRevistas = [];
let allEjemplares = [];
let allPersonas = [];
let allAgencias = [];

document.addEventListener('DOMContentLoaded', async () => {
    // Verificar sesión admin
    try {
        const session = await fetchAPI('auth.php?action=session');
        if (!session || !session.logged_in || session.user.role !== 'admin') {
            window.location.href = 'index.html';
            return;
        }
        document.getElementById('adminName').textContent = `Admin: ${session.user.username}`;
    } catch (e) {
        window.location.href = 'index.html';
        return;
    }

    // Inicializar Modals de Bootstrap
    envioModal = new bootstrap.Modal(document.getElementById('envioModal'));
    revistaModal = new bootstrap.Modal(document.getElementById('revistaModal'));
    ejemplarModal = new bootstrap.Modal(document.getElementById('ejemplarModal'));
    personaModal = new bootstrap.Modal(document.getElementById('personaModal'));
    agenciaModal = new bootstrap.Modal(document.getElementById('agenciaModal'));
    credentialsModal = new bootstrap.Modal(document.getElementById('credentialsModal'));
    
    // Cargar estadísticas y envíos iniciales
    loadStats();
    loadEnvios();

    // Registrar Event Listeners para Formularios
    document.getElementById('envioForm').addEventListener('submit', handleEnvioSubmit);
    document.getElementById('revistaForm').addEventListener('submit', handleRevistaSubmit);
    document.getElementById('ejemplarForm').addEventListener('submit', handleEjemplarSubmit);
    document.getElementById('personaForm').addEventListener('submit', handlePersonaSubmit);
    document.getElementById('agenciaForm').addEventListener('submit', handleAgenciaSubmit);

    // Event Listeners para búsqueda en tiempo real
    document.getElementById('searchEnvios').addEventListener('input', filterEnvios);
    document.getElementById('filterEstado').addEventListener('change', filterEnvios);
    document.getElementById('searchRevistas').addEventListener('input', filterRevistas);
    document.getElementById('searchEjemplares').addEventListener('input', filterEjemplares);
    document.getElementById('searchPersonas').addEventListener('input', filterPersonas);
    document.getElementById('searchAgencias').addEventListener('input', filterAgencias);
});

/**
 * Cargar estadísticas globales del sistema
 */
async function loadStats() {
    try {
        const response = await fetchAPI('stats.php');
        if (response && response.stats) {
            const s = response.stats;
            document.getElementById('stat-envios-total').textContent = s.envios_total;
            document.getElementById('stat-envios-pendientes').textContent = s.envios_pendientes;
            document.getElementById('stat-personas-total').textContent = s.personas_total;
            document.getElementById('stat-ejemplares-total').textContent = s.ejemplares_total;
        }
    } catch (e) {
        console.error("Error al cargar estadísticas", e);
    }
}

/**
 * Control de navegación entre Pestañas (Tabs)
 */
function showTab(tabName) {
    const tabs = document.querySelectorAll('.tab-content');
    tabs.forEach(t => t.classList.remove('active-tab'));
    
    const target = document.getElementById(`tab-${tabName}`);
    if (target) {
        target.classList.add('active-tab');
    }

    const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
    navLinks.forEach(link => link.classList.remove('active'));
    
    const activeLink = document.getElementById(`nav-${tabName}`);
    if (activeLink) {
        activeLink.classList.add('active');
    }

    // Ocultar o mostrar contenedor de estadísticas globales (solo visible en Envíos)
    const statsContainer = document.getElementById('stats-container');
    if (tabName === 'envios') {
        statsContainer.classList.remove('d-none');
        loadStats();
        loadEnvios();
    } else {
        statsContainer.classList.add('d-none');
    }

    switch (tabName) {
        case 'revistas':
            loadRevistas();
            break;
        case 'ejemplares':
            loadEjemplares();
            break;
        case 'personas':
            loadPersonas();
            break;
        case 'agencias':
            loadAgencias();
            break;
    }
}

/* ==========================================================================
   MÓDULO: ENVÍOS
   ========================================================================== */

async function loadEnvios() {
    try {
        allEnvios = await fetchAPI('envios.php');
        filterEnvios();
    } catch (e) {
        showToast("Error al cargar envíos", "error");
    }
}

function filterEnvios() {
    const searchText = document.getElementById('searchEnvios').value.toLowerCase();
    const filterEstado = document.getElementById('filterEstado').value;

    const filtered = allEnvios.filter(e => {
        const matchesSearch = 
            e.nombre_completo.toLowerCase().includes(searchText) ||
            e.revista_titulo.toLowerCase().includes(searchText) ||
            (e.numero_guia && e.numero_guia.toLowerCase().includes(searchText)) ||
            e.id_envio.toString().includes(searchText);
            
        const matchesEstado = filterEstado === "" || e.estado === filterEstado;

        return matchesSearch && matchesEstado;
    });

    renderEnviosTable(filtered);
}

function renderEnviosTable(envios) {
    const tbody = document.getElementById('enviosTableBody');
    tbody.innerHTML = '';

    if (envios.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-muted">No se encontraron envíos</td></tr>`;
        return;
    }

    envios.forEach(e => {
        let borderClass = '';
        if (e.estado === 'Pendiente') borderClass = 'border-warning';
        else if (e.estado === 'En tránsito') borderClass = 'border-primary';
        else if (e.estado === 'Entregado') borderClass = 'border-success';

        // Si ya está Entregado, deshabilitamos select y editar
        const isEntregado = e.estado === 'Entregado';
        const disabledAttr = isEntregado ? 'disabled' : '';

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>#${e.id_envio}</td>
            <td><strong>${e.revista_titulo}</strong> <span class="text-muted">(Ed. ${e.numero_edicion})</span></td>
            <td>${e.nombre_completo}</td>
            <td>${e.nombre_agencia || '<span class="text-muted small">Sin asignar</span>'}</td>
            <td>${e.numero_guia || '-'}</td>
            <td>${e.fecha_despacho || '-'}</td>
            <td>
                <select class="form-select form-select-sm border fw-bold text-center ${borderClass}" 
                        style="width: 130px; font-size: 0.85rem;" 
                        ${disabledAttr}
                        onchange="updateEnvioEstado(${e.id_envio}, this.value)">
                    <option value="Pendiente" ${e.estado === 'Pendiente' ? 'selected' : ''}>Pendiente</option>
                    <option value="En tránsito" ${e.estado === 'En tránsito' ? 'selected' : ''}>En tránsito</option>
                    <option value="Entregado" ${e.estado === 'Entregado' ? 'selected' : ''}>Entregado</option>
                </select>
            </td>
            <td>
                <button class="btn btn-sm btn-outline-primary px-2" ${disabledAttr} onclick='editEnvio(${JSON.stringify(e)})'>Editar</button>
                <button class="btn btn-sm btn-outline-danger px-2" onclick="deleteEnvio(${e.id_envio})">Borrar</button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

async function updateEnvioEstado(id_envio, nuevo_estado) {
    try {
        const envioActual = allEnvios.find(e => e.id_envio === id_envio);
        
        const payload = {
            id_envio: id_envio,
            estado: nuevo_estado,
            id_agencia: envioActual ? envioActual.id_agencia : null,
            numero_guia: envioActual ? envioActual.numero_guia : null,
            fecha_despacho: envioActual ? envioActual.fecha_despacho : null
        };

        await fetchAPI('envios.php', { method: 'PUT', body: payload });
        showToast("Estado actualizado correctamente");
        loadStats();
        loadEnvios();
    } catch (error) {
        showToast(error.message, "error");
        loadEnvios();
    }
}

async function loadSelectOptions() {
    try {
        const [ejemplares, personas, agencias] = await Promise.all([
            fetchAPI('ejemplares.php'),
            fetchAPI('personas.php'),
            fetchAPI('agencias.php')
        ]);

        const selEjemplar = document.getElementById('envio_ejemplar');
        selEjemplar.innerHTML = '<option value="">Seleccione ejemplar...</option>';
        ejemplares.forEach(e => {
            selEjemplar.innerHTML += `<option value="${e.id_ejemplar}">${e.revista_titulo} - Ed. ${e.numero_edicion}</option>`;
        });

        const selPersona = document.getElementById('envio_persona');
        selPersona.innerHTML = '<option value="">Seleccione cliente...</option>';
        personas.forEach(p => {
            selPersona.innerHTML += `<option value="${p.id_persona}">${p.nombre_completo} (${p.ciudad})</option>`;
        });

        const selAgencia = document.getElementById('envio_agencia');
        selAgencia.innerHTML = '<option value="">Seleccione agencia (opcional)...</option>';
        agencias.forEach(a => {
            selAgencia.innerHTML += `<option value="${a.id_agencia}">${a.nombre_agencia}</option>`;
        });
    } catch (e) {
        console.error("Error al cargar selects", e);
    }
}

async function openEnvioModal() {
    document.getElementById('envioForm').reset();
    document.getElementById('envio_id').value = '';
    document.getElementById('envioModalTitle').textContent = 'Nuevo Envío';
    await loadSelectOptions();
    
    // Asegurar que el select del estado en modal esté habilitado
    document.getElementById('envio_estado').disabled = false;
    
    envioModal.show();
}

async function editEnvio(envio) {
    document.getElementById('envioModalTitle').textContent = 'Editar Envío';
    await loadSelectOptions();
    
    document.getElementById('envio_id').value = envio.id_envio;
    document.getElementById('envio_ejemplar').value = envio.id_ejemplar;
    document.getElementById('envio_persona').value = envio.id_persona;
    document.getElementById('envio_agencia').value = envio.id_agencia || '';
    document.getElementById('envio_guia').value = envio.numero_guia || '';
    document.getElementById('envio_fecha').value = envio.fecha_despacho || '';
    document.getElementById('envio_estado').value = envio.estado;
    
    // Si ya está entregado, deshabilitamos la edición completa (por seguridad extra)
    const isEntregado = envio.estado === 'Entregado';
    document.getElementById('envio_estado').disabled = isEntregado;
    
    envioModal.show();
}

async function handleEnvioSubmit(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    const id = document.getElementById('envio_id').value;
    const isEdit = !!id;
    
    setLoadingState(btn, true);

    const payload = {
        id_ejemplar: document.getElementById('envio_ejemplar').value,
        id_persona: document.getElementById('envio_persona').value,
        id_agencia: document.getElementById('envio_agencia').value || null,
        numero_guia: document.getElementById('envio_guia').value || null,
        fecha_despacho: document.getElementById('envio_fecha').value || null,
        estado: document.getElementById('envio_estado').value
    };

    if (isEdit) payload.id_envio = id;

    try {
        const method = isEdit ? 'PUT' : 'POST';
        await fetchAPI('envios.php', { method, body: payload });
        showToast(isEdit ? "Envío actualizado" : "Envío creado");
        envioModal.hide();
        loadStats();
        loadEnvios();
    } catch (error) {
        showToast(error.message, "error");
    } finally {
        setLoadingState(btn, false, "Guardar");
    }
}

async function deleteEnvio(id) {
    if (confirm("¿Seguro que desea eliminar este envío?")) {
        try {
            await fetchAPI(`envios.php?id=${id}`, { method: 'DELETE' });
            showToast("Envío eliminado");
            loadStats();
            loadEnvios();
        } catch (e) {
            showToast(e.message, "error");
        }
    }
}

/* ==========================================================================
   MÓDULO: REVISTAS
   ========================================================================== */

async function loadRevistas() {
    try {
        allRevistas = await fetchAPI('revistas.php');
        filterRevistas();
    } catch (e) {
        showToast("Error al cargar revistas", "error");
    }
}

function filterRevistas() {
    const searchText = document.getElementById('searchRevistas').value.toLowerCase();
    const filtered = allRevistas.filter(r => 
        r.titulo.toLowerCase().includes(searchText) ||
        r.categoria.toLowerCase().includes(searchText) ||
        r.periodicidad.toLowerCase().includes(searchText) ||
        r.id_revista.toString().includes(searchText)
    );
    renderRevistasTable(filtered);
}

function renderRevistasTable(revistas) {
    const tbody = document.getElementById('revistasTableBody');
    tbody.innerHTML = '';

    if (revistas.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-muted">No se encontraron revistas</td></tr>`;
        return;
    }

    revistas.forEach(r => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>#${r.id_revista}</td>
            <td><strong>${r.titulo}</strong></td>
            <td>${r.categoria}</td>
            <td>${r.periodicidad}</td>
            <td>
                <button class="btn btn-sm btn-outline-primary me-1" onclick='editRevista(${JSON.stringify(r)})'>Editar</button>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteRevista(${r.id_revista})">Eliminar</button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function openRevistaModal() {
    document.getElementById('revistaForm').reset();
    document.getElementById('revista_id').value = '';
    document.getElementById('revistaModalTitle').textContent = 'Nueva Revista';
    revistaModal.show();
}

function editRevista(r) {
    document.getElementById('revistaModalTitle').textContent = 'Editar Revista';
    document.getElementById('revista_id').value = r.id_revista;
    document.getElementById('revista_titulo').value = r.titulo;
    document.getElementById('revista_categoria').value = r.categoria;
    document.getElementById('revista_periodicidad').value = r.periodicidad;
    revistaModal.show();
}

async function handleRevistaSubmit(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    const id = document.getElementById('revista_id').value;
    const isEdit = !!id;

    setLoadingState(btn, true);

    const payload = {
        titulo: document.getElementById('revista_titulo').value,
        categoria: document.getElementById('revista_categoria').value,
        periodicidad: document.getElementById('revista_periodicidad').value
    };

    if (isEdit) payload.id_revista = id;

    try {
        const method = isEdit ? 'PUT' : 'POST';
        await fetchAPI('revistas.php', { method, body: payload });
        showToast(isEdit ? "Revista actualizada" : "Revista creada");
        revistaModal.hide();
        loadRevistas();
    } catch (error) {
        showToast(error.message, "error");
    } finally {
        setLoadingState(btn, false, "Guardar");
    }
}

async function deleteRevista(id) {
    if (confirm("¿Seguro que desea eliminar esta revista? (Solo se podrá si no tiene ejemplares/envíos vinculados)")) {
        try {
            await fetchAPI(`revistas.php?id=${id}`, { method: 'DELETE' });
            showToast("Revista eliminada");
            loadRevistas();
        } catch (e) {
            showToast(e.message, "error");
        }
    }
}

/* ==========================================================================
   MÓDULO: EJEMPLARES
   ========================================================================== */

async function loadEjemplares() {
    try {
        allEjemplares = await fetchAPI('ejemplares.php');
        filterEjemplares();
    } catch (e) {
        showToast("Error al cargar ejemplares", "error");
    }
}

function filterEjemplares() {
    const searchText = document.getElementById('searchEjemplares').value.toLowerCase();
    const filtered = allEjemplares.filter(ej => 
        ej.revista_titulo.toLowerCase().includes(searchText) ||
        ej.numero_edicion.toString().includes(searchText) ||
        ej.fecha_publicacion.includes(searchText) ||
        ej.id_ejemplar.toString().includes(searchText)
    );
    renderEjemplaresTable(filtered);
}

function renderEjemplaresTable(ejemplares) {
    const tbody = document.getElementById('ejemplaresTableBody');
    tbody.innerHTML = '';

    if (ejemplares.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-muted">No se encontraron ejemplares</td></tr>`;
        return;
    }

    ejemplares.forEach(ej => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>#${ej.id_ejemplar}</td>
            <td><strong>${ej.revista_titulo}</strong></td>
            <td>Edición #${ej.numero_edicion}</td>
            <td>${ej.fecha_publicacion}</td>
            <td>
                <button class="btn btn-sm btn-outline-primary me-1" onclick='editEjemplar(${JSON.stringify(ej)})'>Editar</button>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteEjemplar(${ej.id_ejemplar})">Eliminar</button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

async function loadRevistaSelectOptions() {
    try {
        const revistas = await fetchAPI('revistas.php');
        const select = document.getElementById('ejemplar_revista_select');
        select.innerHTML = '<option value="">Seleccione Revista...</option>';
        revistas.forEach(r => {
            select.innerHTML += `<option value="${r.id_revista}">${r.titulo}</option>`;
        });
    } catch (e) {
        console.error("Error al cargar select de revistas", e);
    }
}

async function openEjemplarModal() {
    document.getElementById('ejemplarForm').reset();
    document.getElementById('ejemplar_id').value = '';
    document.getElementById('ejemplarModalTitle').textContent = 'Nuevo Ejemplar';
    await loadRevistaSelectOptions();
    ejemplarModal.show();
}

async function editEjemplar(ej) {
    document.getElementById('ejemplarModalTitle').textContent = 'Editar Ejemplar';
    await loadRevistaSelectOptions();
    document.getElementById('ejemplar_id').value = ej.id_ejemplar;
    document.getElementById('ejemplar_revista_select').value = ej.id_revista;
    document.getElementById('ejemplar_edicion').value = ej.numero_edicion;
    document.getElementById('ejemplar_fecha').value = ej.fecha_publicacion;
    ejemplarModal.show();
}

async function handleEjemplarSubmit(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    const id = document.getElementById('ejemplar_id').value;
    const isEdit = !!id;

    setLoadingState(btn, true);

    const payload = {
        id_revista: document.getElementById('ejemplar_revista_select').value,
        numero_edicion: document.getElementById('ejemplar_edicion').value,
        fecha_publicacion: document.getElementById('ejemplar_fecha').value
    };

    if (isEdit) payload.id_ejemplar = id;

    try {
        const method = isEdit ? 'PUT' : 'POST';
        await fetchAPI('ejemplares.php', { method, body: payload });
        showToast(isEdit ? "Ejemplar actualizado" : "Ejemplar creado");
        ejemplarModal.hide();
        loadEjemplares();
    } catch (error) {
        showToast(error.message, "error");
    } finally {
        setLoadingState(btn, false, "Guardar");
    }
}

async function deleteEjemplar(id) {
    if (confirm("¿Seguro que desea eliminar este ejemplar?")) {
        try {
            await fetchAPI(`ejemplares.php?id=${id}`, { method: 'DELETE' });
            showToast("Ejemplar eliminado");
            loadEjemplares();
        } catch (e) {
            showToast(e.message, "error");
        }
    }
}

/* ==========================================================================
   MÓDULO: PERSONAS (CLIENTES)
   ========================================================================== */

async function loadPersonas() {
    try {
        allPersonas = await fetchAPI('personas.php');
        filterPersonas();
    } catch (e) {
        showToast("Error al cargar personas", "error");
    }
}

function filterPersonas() {
    const searchText = document.getElementById('searchPersonas').value.toLowerCase();
    const filtered = allPersonas.filter(p => 
        p.nombre_completo.toLowerCase().includes(searchText) ||
        p.ciudad.toLowerCase().includes(searchText) ||
        p.direccion_envio.toLowerCase().includes(searchText) ||
        p.telefono.includes(searchText) ||
        p.id_persona.toString().includes(searchText)
    );
    renderPersonasTable(filtered);
}

function renderPersonasTable(personas) {
    const tbody = document.getElementById('personasTableBody');
    tbody.innerHTML = '';

    if (personas.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-muted">No se encontraron personas</td></tr>`;
        return;
    }

    personas.forEach(p => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>#${p.id_persona}</td>
            <td><strong>${p.nombre_completo}</strong></td>
            <td>${p.direccion_envio}</td>
            <td>${p.ciudad}</td>
            <td>${p.telefono}</td>
            <td>
                <button class="btn btn-sm btn-outline-primary me-1" onclick='editPersona(${JSON.stringify(p)})'>Editar</button>
                <button class="btn btn-sm btn-outline-danger" onclick="deletePersona(${p.id_persona})">Eliminar</button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function openPersonaModal() {
    document.getElementById('personaForm').reset();
    document.getElementById('persona_id').value = '';
    document.getElementById('personaModalTitle').textContent = 'Nueva Persona';
    personaModal.show();
}

function editPersona(p) {
    document.getElementById('personaModalTitle').textContent = 'Editar Persona';
    document.getElementById('persona_id').value = p.id_persona;
    document.getElementById('persona_nombre').value = p.nombre_completo;
    document.getElementById('persona_direccion').value = p.direccion_envio;
    document.getElementById('persona_ciudad').value = p.ciudad;
    document.getElementById('persona_telefono').value = p.telefono;
    personaModal.show();
}

async function handlePersonaSubmit(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    const id = document.getElementById('persona_id').value;
    const isEdit = !!id;

    setLoadingState(btn, true);

    const payload = {
        nombre_completo: document.getElementById('persona_nombre').value,
        direccion_envio: document.getElementById('persona_direccion').value,
        ciudad: document.getElementById('persona_ciudad').value,
        telefono: document.getElementById('persona_telefono').value
    };

    if (isEdit) payload.id_persona = id;

    try {
        const method = isEdit ? 'PUT' : 'POST';
        const response = await fetchAPI('personas.php', { method, body: payload });
        
        personaModal.hide();
        loadPersonas();
        
        if (!isEdit && response && response.credentials) {
            document.getElementById('cred_username').textContent = response.credentials.username;
            document.getElementById('cred_password').textContent = response.credentials.password;
            credentialsModal.show();
        } else {
            showToast(isEdit ? "Persona actualizada" : "Persona registrada");
        }
    } catch (error) {
        showToast(error.message, "error");
    } finally {
        setLoadingState(btn, false, "Guardar");
    }
}

async function deletePersona(id) {
    if (confirm("¿Seguro que desea eliminar a esta persona? Esto también borrará su usuario asociado si existe.")) {
        try {
            await fetchAPI(`personas.php?id=${id}`, { method: 'DELETE' });
            showToast("Persona eliminada");
            loadPersonas();
        } catch (e) {
            showToast(e.message, "error");
        }
    }
}

/* ==========================================================================
   MÓDULO: AGENCIAS DE TRANSPORTE
   ========================================================================== */

async function loadAgencias() {
    try {
        allAgencias = await fetchAPI('agencias.php');
        filterAgencias();
    } catch (e) {
        showToast("Error al cargar agencias", "error");
    }
}

function filterAgencias() {
    const searchText = document.getElementById('searchAgencias').value.toLowerCase();
    const filtered = allAgencias.filter(a => 
        a.nombre_agencia.toLowerCase().includes(searchText) ||
        a.contacto.toLowerCase().includes(searchText) ||
        a.id_agencia.toString().includes(searchText)
    );
    renderAgenciasTable(filtered);
}

function renderAgenciasTable(agencias) {
    const tbody = document.getElementById('agenciasTableBody');
    tbody.innerHTML = '';

    if (agencias.length === 0) {
        tbody.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-muted">No se encontraron agencias</td></tr>`;
        return;
    }

    agencias.forEach(a => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>#${a.id_agencia}</td>
            <td><strong>${a.nombre_agencia}</strong></td>
            <td>${a.contacto}</td>
            <td>
                <button class="btn btn-sm btn-outline-primary me-1" onclick='editAgencia(${JSON.stringify(a)})'>Editar</button>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteAgencia(${a.id_agencia})">Eliminar</button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function openAgenciaModal() {
    document.getElementById('agenciaForm').reset();
    document.getElementById('agencia_id').value = '';
    document.getElementById('agenciaModalTitle').textContent = 'Nueva Agencia';
    agenciaModal.show();
}

function editAgencia(a) {
    document.getElementById('agenciaModalTitle').textContent = 'Editar Agencia';
    document.getElementById('agencia_id').value = a.id_agencia;
    document.getElementById('agencia_nombre').value = a.nombre_agencia;
    document.getElementById('agencia_contacto').value = a.contacto;
    agenciaModal.show();
}

async function handleAgenciaSubmit(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    const id = document.getElementById('agencia_id').value;
    const isEdit = !!id;

    setLoadingState(btn, true);

    const payload = {
        nombre_agencia: document.getElementById('agencia_nombre').value,
        contacto: document.getElementById('agencia_contacto').value
    };

    if (isEdit) payload.id_agencia = id;

    try {
        const method = isEdit ? 'PUT' : 'POST';
        await fetchAPI('agencias.php', { method, body: payload });
        showToast(isEdit ? "Agencia actualizada" : "Agencia registrada");
        agenciaModal.hide();
        loadAgencias();
    } catch (error) {
        showToast(error.message, "error");
    } finally {
        setLoadingState(btn, false, "Guardar");
    }
}

async function deleteAgencia(id) {
    if (confirm("¿Seguro que desea eliminar esta agencia?")) {
        try {
            await fetchAPI(`agencias.php?id=${id}`, { method: 'DELETE' });
            showToast("Agencia eliminada");
            loadAgencias();
        } catch (e) {
            showToast(e.message, "error");
        }
    }
}

/* ==========================================================================
   UTILIDADES
   ========================================================================== */

function setLoadingState(button, isLoading, originalText = "Guardar") {
    if (!button) return;
    
    if (isLoading) {
        button.disabled = true;
        button.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Procesando...`;
    } else {
        button.disabled = false;
        button.innerHTML = originalText;
    }
}
