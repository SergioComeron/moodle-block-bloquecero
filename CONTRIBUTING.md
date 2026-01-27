# Guía de Contribución

Gracias por tu interés en contribuir a Bloquecero!

## 🚀 Flujo de trabajo

### 1. Branches
- `master` - rama principal estable
- `dev` - rama de desarrollo activo
- `feature/nombre` - nuevas funcionalidades
- `fix/nombre` - corrección de bugs

**IMPORTANTE**: Los PRs deben apuntar a `dev`, no a `master`

### 2. Commits
Usa mensajes descriptivos en español:
```
[TIPO] Descripción breve

Descripción más detallada si es necesario

Closes #123
```

Tipos:
- `[FEAT]` - Nueva funcionalidad
- `[FIX]` - Corrección de bug
- `[REFACTOR]` - Mejora de código sin cambiar funcionalidad
- `[DOCS]` - Cambios en documentación
- `[STYLE]` - Formato, espacios, etc.
- `[TEST]` - Añadir o modificar tests

### 3. Issues
Antes de empezar a trabajar:
1. Busca si ya existe un issue relacionado
2. Si no existe, créalo usando los templates
3. Asígnate el issue o comenta que vas a trabajar en él
4. Vincula el issue en tu PR

### 4. Pull Requests
1. Actualiza tu branch con `dev`
2. Asegúrate de que el código funciona
3. Actualiza CHANGELOG.md si aplica
4. Crea el PR apuntando a `dev`
5. Incluye: `Co-Authored-By: Warp <agent@warp.dev>` si usaste IA

## 🧪 Testing

Antes de crear un PR:
1. Instala el plugin en Moodle local
2. Prueba en diferentes roles (estudiante, profesor, manager)
3. Verifica en diferentes navegadores si cambias UI
4. Revisa la consola del navegador para errores JS

## 📋 Checklist de PR

- [ ] El código sigue los estándares de Moodle
- [ ] Se han probado los cambios localmente
- [ ] CHANGELOG.md está actualizado
- [ ] No hay console.log() ni error_log() de debug
- [ ] Los strings están en archivos de idioma
- [ ] El PR apunta a `dev`

## 🎨 Estilo de código

Sigue las [Moodle Coding Guidelines](https://moodledev.io/general/development/policies/codingstyle):
- Indentación: 4 espacios
- PHP: snake_case para funciones
- JavaScript: camelCase
- CSS: kebab-case

## 💬 ¿Preguntas?

Abre un issue con la etiqueta `question` o contacta al mantenedor.
