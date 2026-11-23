<?php
declare(strict_types=1);

const STOCK_MAXIMO = 2_147_483_647;

function listarProductos(PDO $conexion, string $busqueda): array
{
    // Los signos % y _ escritos por el usuario cuentan como texto literal en LIKE.
    $termino = '%' . strtr($busqueda, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    $consulta = $conexion->prepare(
        <<<'SQL'
        SELECT p.id_producto, p.codigo, p.nombre, p.precio, p.stock_actual,
               p.stock_minimo, p.estado, c.nombre AS categoria
        FROM producto AS p
        JOIN categoria AS c ON c.id_categoria = p.id_categoria
        WHERE p.nombre LIKE :nombre ESCAPE '!'
           OR p.codigo LIKE :codigo ESCAPE '!'
           OR c.nombre LIKE :categoria ESCAPE '!'
        ORDER BY p.estado DESC, p.nombre, p.id_producto
        SQL
    );
    $consulta->execute([
        'nombre' => $termino,
        'codigo' => $termino,
        'categoria' => $termino,
    ]);

    return $consulta->fetchAll();
}

function listarCategorias(PDO $conexion): array
{
    $consulta = $conexion->prepare('SELECT id_categoria, nombre FROM categoria ORDER BY nombre, id_categoria');
    $consulta->execute();
    return $consulta->fetchAll();
}

function buscarProducto(PDO $conexion, int $id, bool $bloquearFila = false): array|false
{
    $sql = 'SELECT id_producto, codigo, nombre, id_categoria, descripcion, precio,
                   stock_actual, stock_minimo, estado
            FROM producto WHERE id_producto = :id';

    if ($bloquearFila) {
        $sql .= ' FOR UPDATE';
    }

    $consulta = $conexion->prepare($sql);
    $consulta->execute(['id' => $id]);
    return $consulta->fetch();
}

function leerIdCatalogo(mixed $valor): ?int
{
    $id = filter_var($valor, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 4_294_967_295],
    ]);
    return $id === false ? null : $id;
}

// Los campos de stock se guardan como enteros con signo en la base.
function leerUnidadesStock(string $valor): ?int
{
    if (!preg_match('/\A[0-9]{1,10}\z/', $valor) || (float) $valor > STOCK_MAXIMO) {
        return null;
    }

    return (int) $valor;
}

function validarProducto(array $datos, array $categorias): array
{
    $errores = [];
    $camposTexto = [
        'codigo' => ['Código', 30],
        'nombre' => ['Nombre', 120],
        'descripcion' => ['Descripción', 255],
    ];

    foreach ($camposTexto as $campo => [$etiqueta, $limite]) {
        if ($campo !== 'descripcion' && $datos[$campo] === '') {
            $errores[$campo] = "$etiqueta es obligatorio.";
        } elseif (!mb_check_encoding($datos[$campo], 'UTF-8') || mb_strlen($datos[$campo], 'UTF-8') > $limite) {
            $errores[$campo] = "$etiqueta debe tener hasta $limite caracteres.";
        }
    }

    $idCategoria = leerIdCatalogo($datos['id_categoria']);
    if ($idCategoria === null || !array_key_exists($idCategoria, $categorias)) {
        $errores['id_categoria'] = 'Selecciona una categoría existente.';
    }

    if (!preg_match('/\A[0-9]{1,10}(?:[.,][0-9]{1,2})?\z/', $datos['precio'])) {
        $errores['precio'] = 'El precio debe ser cero o mayor, con hasta diez dígitos enteros y dos decimales.';
    }

    foreach (['stock_actual' => 'Stock actual', 'stock_minimo' => 'Stock mínimo'] as $campo => $etiqueta) {
        if (leerUnidadesStock($datos[$campo]) === null) {
            $errores[$campo] = "$etiqueta debe ser un número entero entre 0 y 2.147.483.647.";
        }
    }

    return $errores;
}

function crearProducto(PDO $conexion, array $valores): void
{
    $consulta = $conexion->prepare(
        'INSERT INTO producto (codigo, nombre, id_categoria, descripcion, precio, stock_actual, stock_minimo)
         VALUES (:codigo, :nombre, :id_categoria, :descripcion, :precio, :stock_actual, :stock_minimo)'
    );
    $consulta->execute($valores);
}

// La revisión representa el contenido de la fila cuando se abrió el formulario.
function revisionProducto(array $producto): string
{
    return hash('sha256', json_encode($producto, JSON_THROW_ON_ERROR));
}

function actualizarProducto(PDO $conexion, int $id, array $valores, string $revision): bool
{
    $conexion->beginTransaction();

    try {
        // La fila se mantiene bloqueada entre la comprobación y el guardado.
        $actual = buscarProducto($conexion, $id, true);
        if ($actual === false || !hash_equals(revisionProducto($actual), $revision)) {
            $conexion->rollBack();
            return false;
        }

        $consulta = $conexion->prepare(
            'UPDATE producto
             SET codigo = :codigo, nombre = :nombre, id_categoria = :id_categoria,
                 descripcion = :descripcion, precio = :precio,
                 stock_actual = :stock_actual, stock_minimo = :stock_minimo
             WHERE id_producto = :id'
        );
        $consulta->execute($valores + ['id' => $id]);
        $conexion->commit();
        return true;
    } catch (Throwable $error) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        throw $error;
    }
}

function desactivarProducto(PDO $conexion, int $id): bool
{
    // Una segunda solicitud deja la misma fila inactiva y conserva su historial.
    $consulta = $conexion->prepare('UPDATE producto SET estado = 0 WHERE id_producto = :id AND estado = 1');
    $consulta->execute(['id' => $id]);
    return $consulta->rowCount() === 1;
}
