<?php
declare(strict_types=1);

require_once __DIR__ . '/catalogo.php';

function listarMovimientos(PDO $conexion): array
{
    // Se conserva el historial de los productos inactivos y se muestran los nombres vinculados por id.
    $consulta = $conexion->prepare(
        'SELECT m.fecha_hora, m.tipo, m.cantidad, m.observacion, p.codigo,
                p.nombre AS producto, u.nombre AS usuario
         FROM movimiento AS m
         JOIN producto AS p ON p.id_producto = m.id_producto
         JOIN usuario AS u ON u.id_usuario = m.id_usuario
         ORDER BY m.fecha_hora DESC, m.id_movimiento DESC'
    );
    $consulta->execute();
    return $consulta->fetchAll();
}

function listarProductosActivos(PDO $conexion): array
{
    $consulta = $conexion->prepare(
        'SELECT id_producto, codigo, nombre, stock_actual
         FROM producto
         WHERE estado = 1
         ORDER BY nombre, codigo'
    );
    $consulta->execute();
    return $consulta->fetchAll();
}

function registrarMovimiento(
    PDO $conexion,
    int $idProducto,
    int $idUsuario,
    string $tipo,
    int $cantidad,
    ?string $observacion
): array {
    if (!in_array($tipo, ['ENTRADA', 'SALIDA'], true) || $cantidad < 1 || $cantidad > STOCK_MAXIMO) {
        throw new DomainException('El movimiento requiere un tipo válido y una cantidad positiva.');
    }

    $conexion->beginTransaction();

    try {
        // El usuario sale de la sesión y se verifica su estado en la base.
        $usuario = $conexion->prepare('SELECT id_usuario FROM usuario WHERE id_usuario = :id AND estado = 1 FOR UPDATE');
        $usuario->execute(['id' => $idUsuario]);
        if ($usuario->fetchColumn() === false) {
            throw new DomainException('Para registrar un movimiento necesitas una cuenta activa.', 403);
        }

        // El bloqueo evita que dos operaciones calculen su saldo a partir de la misma cantidad.
        $producto = buscarProducto($conexion, $idProducto, true);
        if ($producto === false || (int) $producto['estado'] !== 1) {
            throw new DomainException('Selecciona un producto activo.');
        }

        $stock = (int) $producto['stock_actual'];
        if ($tipo === 'SALIDA' && $cantidad > $stock) {
            throw new DomainException("Stock insuficiente. La cantidad disponible es $stock.");
        }
        if ($tipo === 'ENTRADA' && $cantidad > STOCK_MAXIMO - $stock) {
            throw new DomainException('La entrada supera el stock máximo permitido para el producto.');
        }

        $stockNuevo = $tipo === 'ENTRADA' ? $stock + $cantidad : $stock - $cantidad;
        $actualizar = $conexion->prepare('UPDATE producto SET stock_actual = :stock WHERE id_producto = :id');
        $actualizar->execute(['stock' => $stockNuevo, 'id' => $idProducto]);

        // La base asigna la fecha y la hora. El movimiento y el saldo se confirman juntos.
        $insertar = $conexion->prepare(
            'INSERT INTO movimiento (id_producto, id_usuario, tipo, cantidad, observacion)
             VALUES (:producto, :usuario, :tipo, :cantidad, :observacion)'
        );
        $insertar->execute([
            'producto' => $idProducto,
            'usuario' => $idUsuario,
            'tipo' => $tipo,
            'cantidad' => $cantidad,
            'observacion' => $observacion,
        ]);
        $conexion->commit();

        return ['codigo' => $producto['codigo'], 'stock' => $stockNuevo];
    } catch (Throwable $error) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        throw $error;
    }
}
