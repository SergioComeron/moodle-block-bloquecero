# Bloquecero - Moodle Block Plugin

[![Moodle](https://img.shields.io/badge/Moodle-3.11%2B-orange)](https://moodle.org/)
[![License](https://img.shields.io/badge/License-GPL--3.0-blue)](LICENSE)

Bloque personalizado para Moodle que proporciona una interfaz mejorada de cabecera de curso con información de profesores, navegación por secciones, integración de foros, bibliografía y sesiones en vivo.

## Características

- **Información de profesores**: Muestra fotos, datos de contacto y horarios de tutorías
- **Navegación por secciones**: Carrusel interactivo para explorar semanas/temas del curso
- **Gestión de sesiones en vivo**: Sistema de programación con integración a calendario
- **Integración con foros**: Acceso rápido a foros de avisos, tutorías y estudiantes
- **Bibliografía del curso**: Lista enlazable de recursos bibliográficos
- **Integración Zoom**: Muestra sesiones programadas vía API
- **Personalización visual**: Imagen de fondo configurable para el header

## Instalación

1. Descarga o clona este repositorio
2. Copia la carpeta a `{tu-moodle}/blocks/bloquecero`
3. Accede como administrador a tu Moodle
4. Ve a "Administración del sitio" → "Notificaciones"
5. Sigue las instrucciones de instalación

## Uso

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

### Despliegue masivo por categoría o shortname (CLI)

Para añadir el bloque a varios cursos de una sola vez, usa el script CLI
`cli/add_block_to_category.php`. Debe ejecutarse desde el servidor con un usuario
con permisos sobre los archivos de Moodle.

Los cursos se seleccionan por la **unión** de los filtros indicados: un curso entra
si pertenece a la categoría (o sus subcategorías), **o** su shortname empieza por el
prefijo de `--shortname`, **o** su shortname está en la lista de `--shortnames`, **o**
lo devuelve la búsqueda de `--search` (la misma búsqueda de la página de gestión de
cursos de Moodle). Hay que indicar al menos uno de los selectores.

```bash
# Simular (no escribe nada) sobre la categoría con id 12:
php blocks/bloquecero/cli/add_block_to_category.php --category=12 --dry-run

# Aplicar, colocando el bloque en la zona content-upper:
php blocks/bloquecero/cli/add_block_to_category.php --category=12 --region=content-upper

# Usar el idnumber de la categoría en lugar del id:
php blocks/bloquecero/cli/add_block_to_category.php --idnumber=GRADO_INF --dry-run

# Cursos cuyo shortname empieza por un prefijo literal:
php blocks/bloquecero/cli/add_block_to_category.php --shortname=2000-01_5 --dry-run

# Lista concreta de shortnames exactos:
php blocks/bloquecero/cli/add_block_to_category.php --shortnames=MAT101,FIS202,QUI303 --dry-run

# Búsqueda libre, igual que el buscador de la gestión de cursos de Moodle:
php blocks/bloquecero/cli/add_block_to_category.php --search=Plantilla-5008- --dry-run

# Combinar categoría + prefijo + lista + búsqueda (todo en unión):
php blocks/bloquecero/cli/add_block_to_category.php --category=12 --shortname=2000-01_5 --shortnames=MAT101,FIS202 --search=Plantilla-5008-

# Forzar la zona también en cursos que ya tienen el bloque en otra región
# (incluida una colocación manual de un profesor):
php blocks/bloquecero/cli/add_block_to_category.php --category=12 --region=content-upper --move-existing
```

**Opciones:**

| Opción | Descripción |
|---|---|
| `-c`, `--category=ID` | Id de la categoría a procesar (incluye todas sus descendientes). |
| `-i`, `--idnumber=TEXT` | Idnumber de la categoría, como alternativa a `--category`. |
| `-s`, `--shortname=TEXT` | Prefijo **literal** del shortname: selecciona los cursos cuyo shortname empieza por ese texto. `%` y `_` se tratan como literales (no comodines). |
| `-n`, `--shortnames=LIST` | Lista de shortnames **exactos** separados por comas (p. ej. `MAT101,FIS202`). Se recortan espacios y se ignoran los vacíos. |
| `-q`, `--search=TEXT` | Búsqueda libre idéntica a la del buscador de la gestión de cursos de Moodle (busca en shortname, fullname, idnumber y summary en todo el sitio). |
| `-r`, `--region=NAME` | Zona (nombre interno) donde se coloca el bloque. Por defecto `content-upper`. |
| `-w`, `--weight=N` | Peso/orden dentro de la zona. Por defecto `-10` (arriba). |
| `-m`, `--move-existing` | Fuerza a la zona indicada los cursos que ya tienen el bloque en otra región (actualiza `block_instances.defaultregion` y los overrides de `block_positions`). Sin este flag, los existentes se respetan. |
| `-d`, `--dry-run` | Muestra qué haría sin escribir en la base de datos. |
| `-h`, `--help` | Muestra la ayuda. |

**Notas:**

- El bloque es de instancia única por curso: el script **no duplica** y es
  **idempotente** (puedes relanzarlo sin riesgo).
- Los shortnames de `--shortnames` que no existan se ignoran sin error (no aparecen
  en la salida). Usa `--dry-run` para confirmar qué cursos se tocarían antes de aplicar.
- Usa siempre el **nombre interno** de la zona, no la etiqueta visible. Con el tema
  **Boost Union** las zonas disponibles incluyen `content-upper`, `content-lower`,
  `outside-top`, `outside-bottom`, `outside-left`, `outside-right`, `header`,
  `footer-left/center/right` y `offcanvas-left/center/right`. La zona elegida debe
  estar **habilitada** en los ajustes del tema para el layout de curso; si no, el
  bloque se inserta pero queda oculto. El script avisa cuando detecta este caso.

## Enfoque: bloque vs theme

Esta funcionalidad está implementada como **bloque** y no como theme de forma
deliberada. El motivo principal es el **despliegue selectivo**: el bloque se aplica
solo a los cursos elegidos (por ejemplo, los de una categoría mediante el script
CLI), mientras que un theme se aplica de forma global al sitio, categoría o usuario.
Además, el bloque mantiene configuración y datos propios por curso (profesores,
foros, guías, sesiones, bibliografía) y es independiente del theme del sitio.

A futuro se valora convertir el bloque en un theme cuando el objetivo sea un
aspecto uniforme en todo el sitio. El punto a rediseñar en ese caso sería la
manipulación del layout, que hoy se hace por JavaScript; la estética actual ya se
apoya en la región `content-upper` de Boost Union.

## Requisitos

- Moodle 3.11 o superior
- PHP 7.4+
- (Opcional) Plugin `block_zoom_udima` para integración con Zoom

## Documentación

- [CONTRIBUTING.md](CONTRIBUTING.md) - Guía de contribución
- [CHANGELOG.md](CHANGELOG.md) - Historial de cambios

## Issues y sugerencias

Usa el [sistema de issues de GitHub](https://github.com/SergioComeron/moodle-block-bloquecero/issues) para:
- Reportar bugs
- Solicitar nuevas funcionalidades
- Proponer mejoras

## Contribuir

Las contribuciones son bienvenidas! Lee [CONTRIBUTING.md](CONTRIBUTING.md) para conocer el proceso.

## Proyecto

Sigue el progreso del desarrollo en el [tablero del proyecto](https://github.com/users/SergioComeron/projects/1).

## Licencia

GPL v3 - Ver [LICENSE](LICENSE) para más detalles.

## Autor

Desarrollado para mejorar la experiencia en cursos Moodle.
