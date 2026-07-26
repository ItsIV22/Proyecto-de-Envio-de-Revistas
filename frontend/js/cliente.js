/**
 * cliente.js
 * Lógica para la vista de cliente (solicitudes de revistas y métricas personales)
 */

let solicitarModal;

document.addEventListener('DOMContentLoaded', async () => {
    // Verificar sesión cliente
    try {
        const session = await fetchAPI('auth.php?action=session');
        if (!session || !session.logged_in || session.user.role !== 'cliente') {
            window.location.href = 'index.html';
            return;
        }
        document.getElementById('clientName').textContent = `Hola, ${session.user.username}!`;
    } catch (e) {
        window.location.href = 'index.html';
        return;
    }

    solicitarModal = new bootstrap.Modal(document.getElementById('solicitarModal'));
    
    // Cargar envíos y estadísticas iniciales
    loadMisStats();
    loadMisEnvios();

    // Event listener formulario
    document.getElementById('solicitarForm').addEventListener('submit', handleSolicitudSubmit);
});

/**
 * Cargar estadísticas del cliente
 */
async function loadMisStats() {
    try {
        const response = await fetchAPI('stats.php');
        if (response && response.stats) {
            const s = response.stats;
            document.getElementById('stat-solicitudes-total').textContent = s.solicitudes_total;
            document.getElementById('stat-solicitudes-transito').textContent = s.solicitudes_transito;
            document.getElementById('stat-solicitudes-entregados').textContent = s.solicitudes_entregadas;
        }
    } catch (e) {
        console.error("Error al cargar estadísticas del cliente", e);
    }
}

/**
 * Cargar lista de envíos del cliente
 */
async function loadMisEnvios() {
    try {
        const envios = await fetchAPI('envios.php');
        const container = document.getElementById('misEnviosContainer');
        container.innerHTML = '';

        if (envios.length === 0) {
            container.innerHTML = `<div class="text-center text-muted w-100 py-5">Aún no tienes revistas solicitadas. ¡Pide una!</div>`;
            return;
        }

        envios.forEach(e => {
            let badgeClass = '';
            let statusIcon = '';
            if (e.estado === 'Pendiente') { badgeClass = 'badge-pendiente'; statusIcon = '⏳'; }
            else if (e.estado === 'En tránsito') { badgeClass = 'badge-transito'; statusIcon = '🚚'; }
            else if (e.estado === 'Entregado') { badgeClass = 'badge-entregado'; statusIcon = '✅'; }

            const col = document.createElement('div');
            col.className = 'col-12 col-md-6 col-lg-4';
            col.innerHTML = `
                <div class="card card-social h-100 border-0 bg-white">
                    <div class="card-body p-4 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <span class="badge ${badgeClass} rounded-pill px-3 py-2 fw-medium shadow-sm">${statusIcon} ${e.estado}</span>
                                <small class="text-muted fw-bold">#${e.id_envio}</small>
                            </div>
                            <h5 class="card-title fw-bold mb-1 text-dark">${e.revista_titulo}</h5>
                            <p class="text-muted small mb-3">Edición #${e.numero_edicion}</p>
                        </div>
                        
                        <div>
                            <div class="bg-light p-3 rounded-3 mb-2" style="font-size: 0.9rem;">
                                <small class="d-block text-muted mb-1 font-monospace" style="font-size: 0.75rem;">DIRECCIÓN DE ENVÍO:</small>
                                <span class="d-block fw-semibold text-secondary">${e.direccion_envio}</span>
                                <span class="d-block text-muted small">${e.ciudad}</span>
                            </div>
                            
                            ${e.numero_guia ? `
                            <div class="mt-3 p-2 bg-primary bg-opacity-10 text-primary rounded-3 text-center" style="font-size: 0.85rem;">
                                <small class="fw-bold d-block" style="font-size: 0.7rem;">NÚMERO DE GUÍA (${e.nombre_agencia || 'Agencia'}):</small>
                                <span class="font-monospace fw-bold">${e.numero_guia}</span>
                            </div>` : ''}
                        </div>
                    </div>
                </div>
            `;
            container.appendChild(col);
        });
    } catch (e) {
        showToast("Error al cargar tus envíos", "error");
    }
}

// Abrir modal de solicitar y listar ejemplares disponibles
async function openSolicitudModal() {
    try {
        const ejemplares = await fetchAPI('ejemplares.php');
        const selEjemplar = document.getElementById('solicitud_ejemplar');
        selEjemplar.innerHTML = '<option value="">Selecciona un ejemplar...</option>';
        ejemplares.forEach(e => {
            selEjemplar.innerHTML += `<option value="${e.id_ejemplar}">${e.revista_titulo} - Edición #${e.numero_edicion}</option>`;
        });
        solicitarModal.show();
    } catch (error) {
        showToast("Error al cargar revistas disponibles", "error");
    }
}

async function handleSolicitudSubmit(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    
    // Loader state
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm" role="status"></span> Enviando...`;

    const payload = {
        id_ejemplar: document.getElementById('solicitud_ejemplar').value
    };

    try {
        await fetchAPI('envios.php', { method: 'POST', body: payload });
        showToast("¡Solicitud enviada con éxito! pronto la procesaremos.", "success");
        solicitarModal.hide();
        loadMisStats(); // Refrescar métricas del cliente
        loadMisEnvios(); // Recargar envíos
    } catch (error) {
        showToast(error.message, "error");
    } finally {
        btn.disabled = false;
        btn.innerHTML = `Confirmar Solicitud`;
    }
}
