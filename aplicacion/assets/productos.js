const formularioProducto = document.getElementById('formulario-producto');

if (formularioProducto) {
    // HTML valida el formato. JavaScript comprueba campos en blanco y el límite del stock.
    function validarCampoProducto(campo) {
        let mensaje = '';
        if (campo.hasAttribute('data-texto-obligatorio') && campo.value.trim() === '') {
            mensaje = 'Escribe un valor para este campo.';
        } else if (campo.hasAttribute('data-stock') && Number(campo.value) > 2147483647) {
            mensaje = 'Ingresa un entero entre 0 y 2.147.483.647.';
        }
        campo.setCustomValidity(mensaje);
    }

    formularioProducto.addEventListener('input', (evento) => {
        const campo = evento.target;
        if (campo.matches('input, select, textarea')) {
            campo.classList.remove('is-invalid');
            validarCampoProducto(campo);
        }
    });

    formularioProducto.addEventListener('submit', (evento) => {
        formularioProducto.querySelectorAll('[data-texto-obligatorio], [data-stock]')
            .forEach(validarCampoProducto);

        if (!formularioProducto.reportValidity()) {
            evento.preventDefault();
        }
    });
}

for (const formulario of document.querySelectorAll('[data-desactivar]')) {
    formulario.addEventListener('submit', (evento) => {
        if (!window.confirm('¿Desactivar este producto? Se conservarán su ficha y su historial.')) {
            evento.preventDefault();
        }
    });
}
