# 06 — Graphify y disciplina de tokens

## El problema que resuelve

Un agente de código, por defecto, entiende un proyecto leyéndolo. Cada sesión nueva vuelve a abrir
los mismos archivos para reconstruir el mismo contexto. En un proyecto pequeño eso es tolerable; en
uno con 150 archivos entre modelos, controladores, actions, jobs y componentes Vue, se convierte en
la mayor fuente de gasto de tokens —y casi todo ese gasto es redundante.

**Graphify** escanea el proyecto una vez y construye un grafo de conocimiento: qué existe, dónde
vive y cómo se conecta. El agente consulta el grafo en lugar de leer archivos, y abre solo los que
realmente necesita tocar. La reducción reportada de tokens de entrada por turno es sustancial
(los distintos tutoriales hablan de órdenes de magnitud, según el tamaño del proyecto).

---

## Instalación

```bash
uv tool install graphifyy          # expone el comando `graphify`
graphify install --platform <plataforma>
```

El segundo comando escribe un `SKILL.md` en el directorio de skills del agente correspondiente.
Añade `--project` si prefieres instalarlo con alcance del proyecto en lugar de global.

Verifica las opciones de `--platform` disponibles en tu versión (`graphify install --help`) y elige
la que corresponda a Antigravity o, si no está listada, la instalación genérica por proyecto.

---

## Uso

**Construir el grafo** (la primera vez, y tras cambios grandes):

```bash
graphify .
```

Genera dos artefactos en el proyecto:
- `graph.json` — el grafo que consulta el agente.
- `graph.html` — una visualización navegable, útil para ti, no para el agente.

**Consultarlo** (esto es lo que hace el agente en lugar de leer archivos):

```bash
graphify query "dónde se resuelven las variables de un SOP"
graphify path StartSopRun OpenRouterClient
graphify explain app/Jobs/RunAiTask.php
```

**Mantenerlo al día:**

```bash
graphify update      # incremental, tras cambios normales
graphify rebuild     # completo, tras reestructurar carpetas
```

---

## Cuándo actualizar el grafo

Actualízalo después de: crear modelos o módulos nuevos, mover o renombrar carpetas, terminar una
fase del roadmap. No hace falta tras cada edición menor.

Esto importa más de lo que parece: **un grafo desactualizado es peor que no tener grafo**, porque
manda al agente a buscar en sitios que ya no existen y acabas gastando más tokens que sin él.
Convierte "actualizar el grafo" en el último paso de cada fase.

---

## Qué añadir al `.gitignore`

```
graph.html
```

`graph.json` puede versionarse si quieres que el grafo viaje con el repositorio, pero cambia mucho y
ensucia los diffs. Si trabajas solo desde una máquina, ignóralo también y reconstrúyelo cuando haga
falta.

---

## La otra mitad: disciplina de contexto

Graphify reduce las lecturas innecesarias, pero no compensa una forma de trabajar derrochadora. Lo
que más cuesta, en orden:

**Tareas demasiado grandes.** "Construye el constructor de SOP completo" obliga al agente a cargar
media aplicación y suele terminar en una implementación que hay que rehacer. Por eso los prompts de
`prompts/` están divididos por fase, y cada fase en pasos con criterios de aceptación.

**Contexto repetido.** No le pegues al agente el contenido de `AGENTS.md` ni de los documentos: ya
los tiene. Refiérete a ellos por nombre.

**Archivos volcados enteros.** Si necesitas discutir una función, comparte esa función.

**Re-explicar el proyecto en cada sesión.** Para eso existe `AGENTS.md`. Si te encuentras
explicando algo por segunda vez, no lo expliques: añádelo a `AGENTS.md` o a una regla, y ya queda.

**Iteración sin decisión.** Tres intentos seguidos de "no, así tampoco" cuestan más que parar y
escribir en dos líneas qué quieres exactamente.

---

## Un hábito que rinde

Al terminar cada fase: actualiza el grafo, actualiza `AGENTS.md` si alguna decisión cambió, y
verifica que los documentos de `docs/` siguen describiendo lo que el código hace de verdad.

Diez minutos por fase. Es lo que evita que el agente trabaje, dentro de tres semanas, sobre un mapa
que ya no corresponde al territorio.
