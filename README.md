# Bloquecero - Moodle Block Plugin

[![Moodle](https://img.shields.io/badge/Moodle-3.11%2B-orange)](https://moodle.org/)
[![License](https://img.shields.io/badge/License-GPL--3.0-blue)](LICENSE)

Bloque personalizado para Moodle que proporciona una interfaz mejorada de cabecera de curso con información de profesores, navegación por secciones, integración de foros, bibliografía y sesiones en vivo.

## 🌟 Características

- **Información de profesores**: Muestra fotos, datos de contacto y horarios de tutorías
- **Navegación por secciones**: Carrusel interactivo para explorar semanas/temas del curso
- **Gestión de sesiones en vivo**: Sistema de programación con integración a calendario
- **Integración con foros**: Acceso rápido a foros de avisos, tutorías y estudiantes
- **Bibliografía del curso**: Lista enlazable de recursos bibliográficos
- **Integración Zoom**: Muestra sesiones programadas vía API
- **Personalización visual**: Imagen de fondo configurable para el header

## 📦 Instalación

1. Descarga o clona este repositorio
2. Copia la carpeta a `{tu-moodle}/blocks/bloquecero`
3. Accede como administrador a tu Moodle
4. Ve a "Administración del sitio" → "Notificaciones"
5. Sigue las instrucciones de instalación

## 🚀 Uso

### Añadir el bloque a un curso

1. Entra al curso como profesor/administrador
2. Activa la edición
3. Usa "Añadir un bloque" → "Bloquecero"

### Configuración

Haz clic en el icono de configuración del bloque para:
- Configurar URL de la guía del curso
- Seleccionar foros a mostrar
- Añadir información de contacto de profesores
- Gestionar bibliografía
- Acceder a la gestión de sesiones en vivo

### Gestión de sesiones

1. En la configuración del bloque, haz clic en "Gestionar sesiones"
2. Añade, edita o elimina sesiones
3. Opcionalmente sincroniza con el calendario del curso

## 🔧 Requisitos

- Moodle 3.11 o superior
- PHP 7.4+
- (Opcional) Plugin `block_zoom_udima` para integración con Zoom

## 📖 Documentación

- [CLAUDE.md](CLAUDE.md) - Documentación técnica completa para desarrollo
- [CONTRIBUTING.md](CONTRIBUTING.md) - Guía de contribución
- [CHANGELOG.md](CHANGELOG.md) - Historial de cambios

## 🐛 Issues y sugerencias

Usa el [sistema de issues de GitHub](https://github.com/SergioComeron/moodle-block-bloquecero/issues) para:
- Reportar bugs
- Solicitar nuevas funcionalidades
- Proponer mejoras

## 🤝 Contribuir

Las contribuciones son bienvenidas! Lee [CONTRIBUTING.md](CONTRIBUTING.md) para conocer el proceso.

## 📊 Proyecto

Sigue el progreso del desarrollo en el [tablero del proyecto](https://github.com/users/SergioComeron/projects/1).

## 📄 Licencia

GPL v3 - Ver [LICENSE](LICENSE) para más detalles.

## ✨ Autor

Desarrollado con ❤️ para mejorar la experiencia en cursos Moodle.
