const formularioMovimiento = document.getElementById('formulario-movimiento');

if (formularioMovimiento) {
    const producto = document.getElementById('movimiento-producto');
    const cantidad = document.getElementById('movimiento-cantidad');
    const disponible = document.getElementById('stock-disponible');

    function mostrarStock() {
        const stock = producto.selectedOptions[0]?.dataset.stock;
        disponible.textContent = stock === undefined ? '' : 'Cantidad disponible ' + stock;
    }

    // JavaScript comprueba el formato antes del envío. PHP consulta el saldo real al guardar.
    function validarCantidadMovimiento() {
        const numero = Number(cantidad.value);
        const maximo = Number(cantidad.dataset.maximo);
        const incorrecta = cantidad.value !== '' && (!Number.isInteger(numero) || numero < 1 || numero > maximo);
        cantidad.setCustomValidity(incorrecta ? 'Ingresa una cantidad entera y mayor que cero.' : '');
    }

    producto.addEventListener('change', mostrarStock);
    cantidad.addEventListener('input', validarCantidadMovimiento);
    formularioMovimiento.addEventListener('input', (evento) => {
        evento.target.classList.remove('is-invalid');
    });
    formularioMovimiento.addEventListener('submit', (evento) => {
        validarCantidadMovimiento();
        if (!formularioMovimiento.reportValidity()) {
            evento.preventDefault();
        }
    });
    mostrarStock();
}
