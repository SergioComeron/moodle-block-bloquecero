# Changelog

Todos los cambios notables en este proyecto serán documentados en este archivo.

El formato está basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/),
y este proyecto adhiere a [Semantic Versioning](https://semver.org/lang/es/).

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
