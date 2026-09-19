---
trigger: glob
globs: ["**/*.vue", "**/resources/js/**"]
description: Convenciones de frontend Vue 3 + Inertia para el constructor de SOP
---

# Convenciones de frontend

## Base

- Vue 3 con **Composition API y `<script setup>`**. Nada de Options API ni de mixins.
- Inertia.js para la comunicación con Laravel: `router.post()`, `useForm()`, props que llegan desde
  el controlador. **No construyas endpoints REST paralelos** ni uses `axios` para lo que Inertia ya
  resuelve.
- Tailwind para estilos. Nada de CSS global suelto ni de librerías de componentes pesadas.
- Textos visibles en español. Nombres de componentes, props y variables en inglés.

## Organización

```
resources/js/
  Pages/              Una página Inertia por ruta (Sops/Index.vue, Sops/Edit.vue, Runs/Show.vue)
  Components/Sop/     Un componente por tipo de bloque + el lienzo del constructor
  Components/Ui/      Botones, modales, campos: primitivos reutilizables
  Composables/        useSopBuilder, useVariables, useAutosave
  Support/            Helpers puros sin estado
```

## Componentes de bloque

Cada tipo de bloque del SOP es un componente propio en `Components/Sop/Blocks/`, con el mismo
contrato:

```vue
<script setup>
const props = defineProps({
  block: { type: Object, required: true },   // { id, type, props }
  readonly: { type: Boolean, default: false }, // true en portal de cliente y en ejecución
})
const emit = defineEmits(['update:block', 'remove'])
</script>
```

Tipos a implementar: `heading`, `text`, `checklist`, `input`, `media`, `decision`, `ai_task`,
`approval`, `handoff`. El contrato de cada uno está en `docs/02-modelo-de-datos.md`; respétalo tal
cual, porque el backend lo valida.

**Nunca inventes un tipo de bloque nuevo sin actualizar antes el esquema en
`docs/02-modelo-de-datos.md` y su validación en el backend.**

## Constructor drag & drop

- Reordenar bloques: `vuedraggable` (SortableJS). Es la opción probada para listas ordenables en
  Vue 3.
- Si un SOP necesita ramificación visual tipo diagrama (bloques `decision` conectados), usa Vue Flow
  solo para esa vista; el constructor lineal sigue siendo el modo principal.
- El estado del constructor vive en un composable (`useSopBuilder`), no esparcido por los
  componentes.
- Autoguardado con *debounce* de 2–3 segundos, con indicador visible de "guardando / guardado". Cada
  guardado crea o actualiza un borrador, **no** una versión publicada.

## Variables `{{clave}}`

El editor debe ayudar activamente con las variables, porque son el mecanismo que hace que un SOP
ejecute trabajo real:

- Al escribir `{{` en un bloque `ai_task`, muestra un autocompletado con las claves disponibles:
  las de los bloques `input` anteriores y las `output_key` de los `ai_task` anteriores.
- Marca en rojo toda referencia a una clave que no existe o que se define **después** del bloque
  actual. El backend también lo valida, pero el usuario debe verlo antes de guardar.

## Rendimiento y contexto

- Nada de librerías pesadas para problemas pequeños. Antes de añadir una dependencia, pregúntate si
  son 30 líneas de composable.
- Componentes por debajo de ~200 líneas. Si crece, extrae subcomponentes.
- Recuerda que los assets se compilan **en local** y se sube `public/build`: cada dependencia nueva
  engorda el bundle que tendrás que subir por FTP o Git.

## Accesibilidad mínima

Etiquetas `<label>` asociadas a cada campo, foco visible, y operación por teclado en el constructor
(mover un bloque arriba/abajo sin ratón). El portal de cliente lo van a usar personas que no son del
equipo: no puede depender de gestos finos.
