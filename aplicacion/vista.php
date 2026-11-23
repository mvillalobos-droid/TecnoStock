<?php
declare(strict_types=1);

// Los datos que llegan de un formulario o de la BD se muestran como texto.
function escaparHtml(string $texto): string
{
    return htmlspecialchars($texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mostrarNavegacion(string $pagina): void
{
    ?>
    <header class="barra-panel">
        <nav class="container-xl d-flex flex-wrap align-items-center justify-content-between gap-3 py-3"
             aria-label="Navegación principal">
            <div class="d-flex flex-wrap align-items-center gap-3 gap-md-4">
                <a class="nombre-marca-panel" href="panel.php"
                    <?= $pagina === 'inicio' ? 'aria-current="page"' : '' ?>>TecnoStock</a>
                <a class="enlace-catalogo" href="productos.php"
                    <?= $pagina === 'productos' ? 'aria-current="page"' : '' ?>>Productos</a>
                <a class="enlace-catalogo" href="movimientos.php"
                    <?= $pagina === 'movimientos' ? 'aria-current="page"' : '' ?>>Movimientos</a>
            </div>
            <form method="post" action="salir.php">
                <input type="hidden" name="token" value="<?= tokenFormulario() ?>">
                <button type="submit" class="btn btn-outline-primary">Cerrar sesión</button>
            </form>
        </nav>
    </header>
    <?php
}
