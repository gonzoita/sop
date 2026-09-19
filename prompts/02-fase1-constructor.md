# Prompt 02 — Fase 1: Constructor de SOP

**Objetivo:** poder crear un SOP arrastrando bloques, guardarlo versionado y publicarlo.

**Duración razonable:** 4 a 6 sesiones. Es la fase más larga y la que más valor entrega.

**Requisito previo:** Fase 0 completa y desplegada.

---

## Copia desde aquí

```
Fase 1: constructor de SOP. Lee docs/02-modelo-de-datos.md completo (sección de bloques y
sistema de variables), .agents/rules/02-vue.md y .agents/rules/01-laravel.md.

El contrato de los bloques está definido en docs/02-modelo-de-datos.md. Respétalo exactamente:
el backend valida contra él. No inventes tipos ni campos nuevos sin actualizar antes ese
documento y decírmelo.

Trabaja en pasos. Párate al final de cada uno.

PASO 1 — Backend de SOPs
- Migraciones y modelos: sops y sop_versions, ambos con team_id y el trait BelongsToTeam.
- Actions: CreateSop, SaveSopDraft, PublishSopVersion, DuplicateSop.
- PublishSopVersion debe validar el JSON de bloques antes de publicar y RECHAZAR la
  publicación si: hay ids de bloque duplicados, una variable {{clave}} referencia algo que no
  existe, o referencia algo definido más adelante en el orden de bloques.
- Policies para todo. Un usuario con rol cliente no puede acceder a nada de esto.

PASO 2 — Validador y resolutor de variables
- app/Support/Sop/BlockValidator.php y app/Support/Sop/VariableResolver.php, ambos como
  lógica pura sin dependencias de framework.
- Pruebas unitarias de los dos, cubriendo los casos de error: variable inexistente,
  referencia hacia adelante, id duplicado, bloque de tipo desconocido.

PASO 3 — Lienzo del constructor (Vue)
- Página Sops/Edit.vue con la lista de bloques arrastrables usando vuedraggable.
- Panel lateral para añadir bloques por tipo.
- Composable useSopBuilder con el estado del constructor.
- Autoguardado con debounce de 2-3 segundos e indicador visible de "guardando / guardado".
  El autoguardado escribe un BORRADOR, nunca publica.

PASO 4 — Componentes de bloque
Un componente por tipo en resources/js/Components/Sop/Blocks/, todos con el mismo contrato
de props (block, readonly) y emits (update:block, remove):
heading, text, checklist, input, media, decision, ai_task, approval, handoff.
El bloque ai_task en esta fase solo guarda su configuración; todavía no ejecuta nada.

PASO 5 — Ayuda con variables en el editor
- Al escribir {{ en un campo que admite variables, autocompletado con las claves disponibles
  hasta ese punto del SOP.
- Marcar en rojo las referencias inválidas, en vivo, antes de guardar.

PASO 6 — Listado, versiones y exportación
- Sops/Index.vue con búsqueda, filtro por categoría y estado, y paginación.
- Vista del historial de versiones con changelog y opción de ver una versión anterior.
- Exportación de un SOP a Markdown y a PDF.
- Marcar un SOP como plantilla (is_template) y duplicarlo desde la plantilla.

Criterios de aceptación:
1. Creo un SOP, añado seis bloques de tipos distintos, los reordeno arrastrando y se guarda solo.
2. Publico una versión; si tiene una variable mal referenciada, la publicación se rechaza con
   un mensaje claro que dice cuál.
3. Edito y publico una segunda versión; veo ambas en el historial.
4. Exporto a Markdown y a PDF y el contenido es correcto.
5. Un usuario con rol cliente no puede entrar a ninguna de estas pantallas.

Al terminar: actualiza el grafo de Graphify, commit, y dime qué probar a mano.
```

## Hasta aquí

---

## Tu tarea real de esta fase

Mientras el agente construye, **documenta tres SOP reales de tu agencia** dentro de la plataforma.
No inventados: los que de verdad ejecutas.

Ese ejercicio es el que descubre lo que falta. Un constructor probado solo con datos de ejemplo se
ve perfecto hasta que intentas meterle un proceso real y te falta un tipo de bloque, o el orden no
funciona como creías. Mejor descubrirlo aquí que en la Fase 3.
