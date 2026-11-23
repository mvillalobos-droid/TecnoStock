# TecnoStock

Proyecto universitario de inventario de accesorios tecnológicos de Manuel Villalobos. La aplicación usa PHP 8 con PDO, MySQL o MariaDB, Bootstrap y JavaScript.

## Ejecutar en XAMPP

1. Descarga o clona el repositorio. Copia la carpeta `aplicacion` al directorio `htdocs` de XAMPP y ponle el nombre `tecnostock`.
2. Inicia Apache y MySQL desde XAMPP. Abre phpMyAdmin y, en una instalación nueva, importa `sql/TecnoStock_Fase_1.sql` y después `sql/TecnoStock_Fase_2_Demo.sql`.
3. Abre la aplicación e inicia sesión con la cuenta local de demostración. [TecnoStock en localhost](http://localhost/tecnostock/)

| Cuenta local de demostración | Valor |
| --- | --- |
| Correo | `demo@tecnostock.test` |
| Contraseña | `TecnoStockDemo-26!` |

La conexión usa el servidor local en `127.0.0.1:3306`. Si tu XAMPP usa otra cuenta de base de datos, configura `TECNOSTOCK_DB_USER` y `TECNOSTOCK_DB_PASSWORD` en el entorno de Apache.

## Qué se puede probar

- Iniciar y cerrar sesión. Un acceso directo al panel requiere una sesión válida.
- Buscar productos por nombre, código o categoría. El mouse `PER-001` aparece con stock bajo porque tiene 2 unidades y su mínimo es 5.
- Crear y editar productos con validación de datos, y desactivarlos conservando su ficha y sus movimientos.
- Registrar una entrada o salida. Cada movimiento guarda la fecha, el producto, la cantidad y el responsable, y actualiza el saldo en la misma transacción. Con los datos iniciales, una salida de 3 unidades de `PER-001` se rechaza. Una entrada de 3 lo deja en 5 y quita la alerta.

## Archivos del proyecto

| Archivo | Función |
| --- | --- |
| `aplicacion/db.php` | Conexión PDO a la base `tecnostock`. |
| `aplicacion/index.php`, `sesion.php`, `panel.php` y `salir.php` | Acceso, protección de páginas y cierre de sesión. |
| `aplicacion/productos.php` | Catálogo, búsqueda y alertas de stock bajo. |
| `aplicacion/catalogo.php`, `producto_formulario.php` y `producto_desactivar.php` | Consultas preparadas, validación, creación, edición y baja lógica. |
| `aplicacion/movimientos.php` y `movimiento_formulario.php` | Historial y formulario de entradas y salidas. |
| `aplicacion/movimientos_datos.php` | Guarda el movimiento y el saldo con transacciones y bloqueo de la fila del producto. |
| `aplicacion/vista.php` y `assets/` | Navegación, estilos y validaciones del navegador. |
| `sql/TecnoStock_Fase_1.sql` y `TecnoStock_Fase_2_Demo.sql` | Creación de la base y datos de demostración. |
