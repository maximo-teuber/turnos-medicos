(function() {
    'use strict';

    const form = document.getElementById('loginForm');
    const dniInput = document.getElementById('dni');
    const passwordInput = document.getElementById('password');

    // Solo números en DNI
    dniInput?.addEventListener('input', function(e) {
        this.value = this.value.replace(/[^0-9]/g, '');
    });

    // Validación del formulario
    form?.addEventListener('submit', function(e) {
        const dni = dniInput.value.trim();
        const password = passwordInput.value;

        // Validar DNI
        if (!dni) {
            e.preventDefault();
            alert('⚠️ Por favor, ingresá tu DNI');
            dniInput.focus();
            return false;
        }

        if (!/^[0-9]{7,10}$/.test(dni)) {
            e.preventDefault();
            alert('⚠️ El DNI debe tener entre 7 y 10 dígitos');
            dniInput.focus();
            return false;
        }

        // Validar contraseña
        if (!password) {
            e.preventDefault();
            alert('⚠️ Por favor, ingresá tu contraseña');
            passwordInput.focus();
            return false;
        }

        if (password.length < 6) {
            e.preventDefault();
            alert('⚠️ La contraseña debe tener al menos 6 caracteres');
            passwordInput.focus();
            return false;
        }

        // Todo OK
        return true;
    });

})();