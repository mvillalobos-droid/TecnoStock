const formulario = document.getElementById('formulario-acceso');
const clave = document.getElementById('clave');
const errorClave = document.getElementById('clave-error');
const codificador = new TextEncoder();

// Al editar, limpiamos el aviso anterior y dejamos a HTML validar el campo requerido.
clave.addEventListener('input', () => {
    clave.setCustomValidity('');
    errorClave.textContent = 'Escribe tu contraseña.';
});

formulario.addEventListener('submit', (evento) => {
    // bcrypt trabaja con hasta 72 bytes. Un carácter con tilde puede ocupar más de uno.
    const excedeLimite = codificador.encode(clave.value).length > 72;
    const aviso = excedeLimite ? 'Usa una contraseña de hasta 72 bytes.' : '';
    clave.setCustomValidity(aviso);
    errorClave.textContent = aviso || 'Escribe tu contraseña.';

    formulario.classList.add('was-validated');
    if (!formulario.checkValidity()) {
        evento.preventDefault();
        formulario.querySelector(':invalid').focus();
    }
});

// Si el script no está disponible, el formulario conserva la validación nativa de HTML.
formulario.noValidate = true;
