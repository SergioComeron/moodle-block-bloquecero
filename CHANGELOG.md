# Changelog

Todos los cambios notables en este proyecto serán documentados en este archivo.

El formato está basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/),
y este proyecto adhiere a [Semantic Versioning](https://semver.org/lang/es/).

## [v0.10] - 2026-04-17

### Nuevas funcionalidades
- autodetectar foros al configurar el bloque por primera vez
- remapear foros (novedades, tutorías, estudiantes) en backup/restore por nombre
- remap IDs de secciones en configdata durante el restore (#15)
- recrear eventos de calendario al restaurar sesiones con sync activo
- añadir soporte backup/restore para sesiones y bibliografía (#15)
- filtros por tipo de elemento en el modal del Gantt
- selector multi-asignatura en el modal del diagrama de Gantt
- botón exportar PDF en el modal del diagrama de Gantt
- añadir tooltips a los iconos del menú de navegación
- cronograma Gantt de secciones, actividades y sesiones (Closes #13)
- destacado automático de sección por fechas programadas (Closes #12)

### Correcciones
- añadir global $DB en define_structure del backup stepslib
- ocultar etiqueta 'Teaching team' cuando no hay profesores seleccionados
- remap de sección en restore usando estrategia lazy (issue #15)
- remap secciones por número de posición en lugar de backup_ids_temp
- consultar backup_ids_temp directamente para remap de secciones en restore
- corregir restore eliminando path /block agrupado que bloqueaba callbacks
- eliminar filtro de fecha en sesiones del Gantt multi-curso
- añadir sesiones en directo al Gantt multi-curso (gantt_ajax.php)
- alinear botón PDF y cerrar en la misma fila del header del modal Gantt
- eliminar require_once de block_bloquecero en gantt_ajax y mejorar manejo de errores
- mover consulta ganttothercourses antes de generar el HTML del modal
- corregir nombre de variable a snake_case para phpcs
- forzar orientación horizontal en exportación PDF del Gantt
- escalar diagrama Gantt para que quepa en una sola página A4 al exportar PDF
- icono del diagrama de Gantt en menú móvil usando FA directamente
- usar cm.instance en lugar de cm.id para resolver sección padre de subsecciones
- corregir estilo de código (phpcs)
- resolver sección padre para actividades en mod_subsection en el Gantt
- mostrar Gantt aunque no haya secciones con fechas configuradas
- propagar sectionnum a ganttactivities para agrupación por sección
- usar DateTime::modify('+1 week') para límites de columna del Gantt
- timezone en cálculo de semanas del Gantt para formato weeks
- corregir cálculo de semanas del Gantt con zona horaria del usuario
- corregir estilo de código (phpcs)
- corregir estilo de código (phpcs)
- fetch --tags antes de calcular bump para detectar tags creados por CI
- fusionar auto-release y release en un único workflow para evitar limitación de GITHUB_TOKEN

### Documentación
- actualizar CLAUDE.md con backup/restore y lazy sectionmap (issue #15 completada)
- actualizar CLAUDE.md con Gantt, multi-curso, filtros y tooltips (v0.8)
- actualizar CLAUDE.md con flujo de versiones, hooks y CI completo

### Otros cambios
- Release: bump version to 0.10
- Release: bump version to 0.9
- tmp: debug con file_put_contents en /tmp/bloquecero_restore.log
- tmp: añadir error_log para depurar restore de secciones
- Refactor: agrupar actividades bajo su sección en el Gantt
- Refactor: separar secciones y actividades en el Gantt (revertir agrupación)


## [v0.8] - 2026-04-10

### Documentación
- actualizar CLAUDE.md con flujo real de push a master y workflows fusionados

### Otros cambios
- Release: bump version to 0.8


## [v0.7] - 2026-04-10

### Correcciones
- fusionar auto-release y release en un único workflow para evitar limitación de GITHUB_TOKEN

### Documentación
- actualizar CLAUDE.md con flujo de versiones, hooks y CI completo

### Otros cambios
- Release: bump version to 0.7


## [v0.6] - 2026-04-10

### Correcciones
- comprobar bump ya hecho antes de modificar version.php en hook pre-push

### Otros cambios
- Release: bump version to 0.6


## [v0.5] - 2026-04-10

### Nuevas funcionalidades
- bump automático de version.php al hacer push a master
- enlazar sesiones con evento de calendario de Moodle (#9)
- auto-release y CHANGELOG automático al cambiar versión
- añadir Moodle Code Style check al CI con moodle-cs

### Correcciones
- corregir auto-bump en hook pre-push y grep en auto-release.yml
- reemplazar grep -P por sed en hook pre-push (incompatible con macOS)
- escapar patrón regex con ( en hook pre-push para evitar error de bash
- eliminar asignación de propiedad dinámica deprecada en PHP 8.2+
- ejecutar CI en push a master (rama de releases)
- ejecutar CI solo en push a dev, no en pull requests
- añadir code sniffer al hook pre-push local
- corregir errores de code style detectados por moodle-cs
- migrar callback before_standard_top_of_body_html al sistema de hooks de Moodle 4.4+ (#10)
- usar vista de día del calendario en lugar de edición del evento
- accesibilidad en botones de navegación circular (#8)
- aplicar estilo circular a botones de navegación de tarjetas (#8)
- mejorar visibilidad de flechas del carrusel de secciones (#8)
- dejar de trackear .claude/settings.local.json
- últimos ajustes de estilo para pasar codechecker sin errores
- corregir errores de estilo Moodle (codechecker)
- CI se ejecuta en push/PR a master en lugar de dev

### Documentación
- actualizar CLAUDE.md con flujo de ramas, hooks locales y CI

### Otros cambios
- Release: bump version to 0.5
- Release: bump version to 0.4


## [Unreleased]

### Añadido
- Sistema de gestión de sesiones en vivo con base de datos
- Integración con calendario de Moodle
- Templates de issues en GitHub
- Proyecto GitHub para gestión de tareas

### Changed

### Fixed
- Año añadido en las fechas (2026-01-27)
- Mejorado mensaje de error y detalles en consola (2026-01-27)

## [0.2.0] - 2025-06-17

### Añadido
- Base de datos para sesiones (`block_bloquecero_sessions`)
- Página de gestión de sesiones (manage_sessions.php)
- Formulario de edición de sesiones (edit_session.php)
- Capability `block/bloquecero:managesessions`
- Sincronización automática con calendario de Moodle

### Changed
- Las sesiones ahora se almacenan en BD en lugar de en config del bloque
- Mejora en la visualización de sesiones (solo futuras + últimas 2 horas)

## [0.1.0] - 2025-05-31

### Añadido
- Bloque inicial con interfaz de cabecera de curso
- Información de profesores con foto y contacto
- Navegación tipo carrusel por secciones del curso
- Integración con foros (avisos, tutorías, estudiantes)
- Gestión de bibliografía
- Integración con API de Zoom
- Configuración de imagen de fondo para header
