/**
 * app.js
 * Utilidades globales para llamadas a la API
 */

const API_BASE = '../backend/api';

/**
 * Función wrapper para fetch que incluye credenciales (cookies de sesión)
 * @param {string} endpoint - Ruta relativa al base path, ej: 'revistas.php'
 * @param {object} options - Opciones de fetch (method, body, etc)
 */
async function fetchAPI(endpoint, options = {}) {
    const url = `${API_BASE}/${endpoint}`;
    
    const defaultOptions = {
        headers: {
            'Content-Type': 'application/json'
        },
        // Enviar cookies para mantener la sesión
        credentials: 'same-origin' // Si están en el mismo host, si es diferente usar 'include'
    };

    if (options.body && typeof options.body === 'object') {
        options.body = JSON.stringify(options.body);
    }

    const finalOptions = { ...defaultOptions, ...options };
    
    // Merge headers si se enviaron personalizados
    if (options.headers) {
        finalOptions.headers = { ...defaultOptions.headers, ...options.headers };
    }

    try {
        const response = await fetch(url, finalOptions);
        
        // Si no autorizado (sesión expirada o no iniciada)
        if (response.status === 401 && !url.includes('auth.php')) {
            window.location.href = 'index.html';
            return null;
        }

        const text = await response.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            data = { message: "Error al parsear respuesta JSON" };
        }

        if (!response.ok) {
            throw new Error(data.message || `Error HTTP: ${response.status}`);
        }

        return data;
    } catch (error) {
        console.error("Fetch API Error:", error);
        throw error;
    }
}

/**
 * Función para cerrar sesión global
 */
async function logout() {
    try {
        await fetchAPI('auth.php?action=logout', { method: 'POST' });
        window.location.href = 'index.html';
    } catch (error) {
        alert("Error al cerrar sesión");
    }
}

/**
 * Función para mostrar notificaciones Toasts (Requiere Bootstrap JS)
 */
function showToast(message, type = 'success') {
    // Si no existe contenedor de toasts, crearlo
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(toastContainer);
    }

    const bgClass = type === 'success' ? 'bg-success' : (type === 'error' ? 'bg-danger' : 'bg-primary');
    
    const toastEl = document.createElement('div');
    toastEl.className = `toast align-items-center text-white border-0 ${bgClass}`;
    toastEl.setAttribute('role', 'alert');
    toastEl.setAttribute('aria-live', 'assertive');
    toastEl.setAttribute('aria-atomic', 'true');
    
    toastEl.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;
    
    toastContainer.appendChild(toastEl);
    
    // Si Bootstrap está cargado, inicializar
    if (typeof bootstrap !== 'undefined') {
        const toast = new bootstrap.Toast(toastEl);
        toast.show();
        
        // Limpiar del DOM luego de ocultarse
        toastEl.addEventListener('hidden.bs.toast', () => {
            toastEl.remove();
        });
    }
}
