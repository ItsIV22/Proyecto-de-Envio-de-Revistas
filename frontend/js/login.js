/**
 * login.js
 * Lógica para la pantalla de inicio de sesión
 */

document.addEventListener('DOMContentLoaded', async () => {
    // Verificar si ya hay sesión activa
    try {
        const session = await fetchAPI('auth.php?action=session');
        if (session && session.logged_in) {
            redirectByRole(session.user.role);
        }
    } catch (e) {
        // Ignorar si falla, simplemente no hay sesión
    }

    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const usernameInput = document.getElementById('username').value.trim();
            const passwordInput = document.getElementById('password').value.trim();
            const alertEl = document.getElementById('loginAlert');
            const btn = document.querySelector('.btn-login');

            alertEl.classList.add('d-none');
            btn.disabled = true;
            btn.textContent = 'Verificando...';

            try {
                const response = await fetchAPI('auth.php?action=login', {
                    method: 'POST',
                    body: {
                        username: usernameInput,
                        password: passwordInput
                    }
                });

                if (response && response.user) {
                    redirectByRole(response.user.role);
                }
            } catch (error) {
                alertEl.textContent = error.message || 'Credenciales inválidas o error de conexión.';
                alertEl.classList.remove('d-none');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Ingresar';
            }
        });
    }
});

function redirectByRole(role) {
    if (role === 'admin') {
        window.location.href = 'admin.html';
    } else if (role === 'cliente') {
        window.location.href = 'cliente.html';
    }
}
