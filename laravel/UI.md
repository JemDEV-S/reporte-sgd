# Interfaz y evolución de pendientes

La presentación comparte el layout `components/layouts/platform`, los tokens de
`resources/css/app.css` y componentes Blade. La tipografía usa fuentes del sistema
sin solicitudes a servicios externos. `x-icon` y `x-illustration` contienen SVG
locales y decorativos; sus acciones deben conservar etiquetas de texto.

## Búsqueda

`buscar` acepta hasta 100 caracteres, sin distinguir mayúsculas ni tildes. En el
resumen encuentra dependencias de todo el catálogo y permite cambiar de foco;
no modifica los indicadores actuales. En el general filtra filas, totales y
exportaciones con el mismo conjunto de resultados. Los enlaces conservan el
estado `0` y la publicación consultada.

## Histórico

`EvolucionPendientes` selecciona el último corte de cada día en America/Lima,
dentro de los 30, 60 o 90 días anteriores al corte visible, incluido ese día.
Solo compara publicaciones finalizadas del mismo perfil y versión de reglas.
Una consulta agrupada cuenta las asignaciones usando las reglas y filtros del
motor. No se consulta el SGD para dibujar el gráfico.

El modo con subdependencias utiliza el conjunto de oficinas de la estructura
actual, indicado en pantalla; no reconstruye organigramas históricos. Los días
sin publicaciones no se convierten en ceros. Una línea continua conecta los cortes
disponibles respetando su distancia temporal; no se crean mediciones intermedias. Con menos
de dos días se muestra un estado informativo. La tabla de valores y el SVG
funcionan sin JavaScript; este añade la consulta de puntos por foco y puntero.

## Verificación

- `npm run build`
- `php artisan view:cache`
- `php artisan test --compact`
- `php vendor/bin/pint --test --dirty`

Las pruebas funcionales cubren cortes diarios y zona horaria, perfiles y reglas,
filtros, búsqueda, exportación, subdependencias, permisos y estados sin histórico.

## Tablas y distribución

La cabecera muestra únicamente el título. Búsqueda y filtros comparten una barra;
los indicadores y los gráficos se distribuyen en grids adaptables. Las tablas del
resumen y del general permiten filtrar las filas cargadas, ordenar por columna y
elegir cuántas filas mostrar. Estas operaciones locales no cambian los indicadores
ni la exportación, que siguen los filtros del reporte. Sin JavaScript se conservan
todas las filas y la navegación del servidor.

Pruebas de ordenación y búsqueda locales: `node --test tests/js/table-data.test.mjs`.
