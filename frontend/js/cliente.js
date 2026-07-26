/**
 * cliente.js
 * Lógica para la vista del cliente
 */

let solicitarModal;

document.addEventListener('DOMContentLoaded', async () => {
    // Verificar sesión cliente
    try {
        const session = await fetchAPI('auth.php?action=session');
        if (!session.logged_in || session.user.role !== 'cliente') {
            window.location.href = 'index.html';
            return;
        }
        document.getElementById('clientName').textContent = `Hola, ${session.user.username}!`;
    } catch (e) {
        window.location.href = 'index.html';
    }

    solicitarModal = new bootstrap.Modal(document.getElementById('solicitarModal'));
    
    // Cargar envíos al iniciar
    loadMisEnvios();

    // Event listener formulario
    document.getElementById('solicitarForm').addEventListener('submit', handleSolicitudSubmit);
});

// Cargar envíos del cliente
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
                <div class="card card-social h-100">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <span class="badge ${badgeClass} rounded-pill px-3 py-2 fw-medium shadow-sm">${statusIcon} ${e.estado}</span>
                            <small class="text-muted fw-bold">#${e.id_envio}</small>
                        </div>
                        <h5 class="card-title fw-bold mb-1">${e.revista_titulo}</h5>
                        <p class="text-muted mb-3">Edición ${e.numero_edicion}</p>
                        
                        <div class="bg-light p-3 rounded-3 mb-0">
                            <small class="d-block text-muted mb-1">Destino:</small>
                            <span class="d-block fw-medium text-dark">${e.direccion_envio}, ${e.ciudad}</span>
                        </div>
                        
                        ${e.numero_guia ? `<div class="mt-3"><small class="text-muted">Guía:</small> <span class="fw-bold">${e.numero_guia}</span></div>` : ''}
                    </div>
                </div>
            `;
            container.appendChild(col);
        });
    } catch (e) {
        showToast("Error al cargar tus envíos", "error");
    }
}

// Abrir modal de solicitar
async function openSolicitudModal() {
    try {
        const ejemplares = await fetchAPI('ejemplares.php');
        const selEjemplar = document.getElementById('solicitud_ejemplar');
        selEjemplar.innerHTML = '<option value="">Selecciona un ejemplar...</option>';
        ejemplares.forEach(e => {
            selEjemplar.innerHTML += `<option value="${e.id_ejemplar}">${e.revista_titulo} - Edición ${e.numero_edicion}</option>`;
        });
        solicitarModal.show();
    } catch (error) {
        showToast("Error al cargar revistas disponibles", "error");
    }
}

async function handleSolicitudSubmit(e) {
    e.preventDefault();
    
    const payload = {
        id_ejemplar: document.getElementById('solicitud_ejemplar').value
    };

    try {
        await fetchAPI('envios.php', { method: 'POST', body: payload });
        showToast("¡Solicitud enviada con éxito! pronto la procesaremos.", "success");
        solicitarModal.hide();
        loadMisEnvios();
    } catch (error) {
        showToast(error.message, "error");
    }
}
