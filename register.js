(function() {
    'use strict';

    const form = document.getElementById('registerForm');
    const password = document.getElementById('password');
    const password2 = document.getElementById('password2');
    const strengthMsg = document.getElementById('strengthMsg');

    // Validar fortaleza de contraseña
    password?.addEventListener('input', function() {
        const val = this.value;
        const len = val.length;

        if (len === 0) {
            strengthMsg.textContent = '';
            strengthMsg.style.color = '#94a3b8';
            return;
        }

        if (len < 6) {
            strengthMsg.textContent = '⚠️ Muy corta (mínimo 6 caracteres)';
            strengthMsg.style.color = '#ef4444';
        } else if (len < 8) {
            strengthMsg.textContent = '🟡 Débil';
            strengthMsg.style.color = '#fb923c';
        } else if (len < 12) {
            strengthMsg.textContent = '🟢 Buena';
            strengthMsg.style.color = '#10b981';
        } else {
            strengthMsg.textContent = '🔒 Excelente';
            strengthMsg.style.color = '#22d3ee';
        }
    });

    // Validar coincidencia de contraseñas
    password2?.addEventListener('input', function() {
        if (this.value && password.value !== this.value) {
            this.setCustomValidity('Las contraseñas no coinciden');
        } else {
            this.setCustomValidity('');
        }
    });

    // Validación del formulario antes de enviar
    form?.addEventListener('submit', function(e) {
        // DNI solo números
        const dni = document.getElementById('dni').value.trim();
        if (!/^[0-9]{7,10}$/.test(dni)) {
            e.preventDefault();
            alert('⚠️ El DNI debe tener entre 7 y 10 dígitos numéricos');
            return false;
        }

        // Validar que las contraseñas coincidan
        if (password.value !== password2.value) {
            e.preventDefault();
            alert('⚠️ Las contraseñas no coinciden');
            return false;
        }

        // Todo OK
        return true;
    });

    // Solo números en DNI
    document.getElementById('dni')?.addEventListener('input', function(e) {
        this.value = this.value.replace(/[^0-9]/g, '');
    });

})();