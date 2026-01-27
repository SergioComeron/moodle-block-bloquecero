# 🚀 Guía Rápida - GitHub CLI

## Comandos más usados

### 📋 Issues

```bash
# Listar todos los issues
gh issue list

# Listar solo issues abiertos con un label
gh issue list --label bug

# Ver un issue específico
gh issue view 1

# Crear un issue
gh issue create --title "[BUG] Descripción" --body "Detalles..." --label bug

# Cerrar un issue (automático al hacer commit con "Closes #X")
gh issue close 1

# Asignarte un issue
gh issue edit 1 --add-assignee @me

# Comentar en un issue
gh issue comment 1 --body "Mensaje"
```

### 📊 Proyecto

```bash
# Ver proyectos
gh project list --owner SergioComeron

# Añadir issue al proyecto
gh project item-add 1 --owner SergioComeron --url https://github.com/SergioComeron/moodle-block-bloquecero/issues/4
```

### 🔖 Labels

```bash
# Ver labels
gh label list

# Crear label
gh label create "nombre" --color "HEXCOLOR" --description "Descripción"

# Añadir label a issue
gh issue edit 1 --add-label bug
```

### 🌿 Pull Requests

```bash
# Crear PR desde branch actual
gh pr create --title "[FEAT] Título" --body "Descripción" --base dev

# Listar PRs
gh pr list

# Ver un PR
gh pr view 1

# Hacer merge de PR
gh pr merge 1 --squash
```

## 💡 Flujo de trabajo típico

### 1. Empezar nueva tarea
```bash
# Crear issue
gh issue create --title "[FEAT] Nueva funcionalidad" --label enhancement

# Crear branch
git checkout -b feature/nueva-funcionalidad

# Trabajar en el código...
```

### 2. Hacer commit vinculado a issue
```bash
git add .
git commit -m "[FEAT] Implementar nueva funcionalidad

Closes #4

Co-Authored-By: Warp <agent@warp.dev>"
```

### 3. Push y crear PR
```bash
git push -u origin feature/nueva-funcionalidad
gh pr create --title "[FEAT] Nueva funcionalidad" --body "Closes #4" --base dev
```

### 4. Después del merge
```bash
git checkout dev
git pull
git branch -d feature/nueva-funcionalidad
```

## 🏷️ Convenciones

### Prefijos de commits
- `[FEAT]` - Nueva funcionalidad
- `[FIX]` - Corrección de bug
- `[REFACTOR]` - Mejora de código
- `[DOCS]` - Documentación
- `[STYLE]` - Formato
- `[TEST]` - Tests

### Cerrar issues automáticamente
En el commit o PR body, usa:
- `Closes #123`
- `Fixes #123`
- `Resolves #123`

## 🔗 Enlaces rápidos

- Issues: https://github.com/SergioComeron/moodle-block-bloquecero/issues
- Proyecto: https://github.com/users/SergioComeron/projects/1
- Repo: https://github.com/SergioComeron/moodle-block-bloquecero

## 📱 Accesos desde web

También puedes gestionar todo desde:
- GitHub.com (interfaz web completa)
- GitHub Mobile (app iOS/Android)
