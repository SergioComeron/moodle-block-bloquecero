# Changelog

Todos los cambios notables en este proyecto serán documentados en este archivo.

El formato está basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/),
y este proyecto adhiere a [Semantic Versioning](https://semver.org/lang/es/).

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
