---
trigger: always_on
description: Disciplina de contexto y uso de Graphify para no desperdiciar tokens
---

# Ahorro de tokens

El presupuesto de tokens es una restricción real de este proyecto. Cada archivo que abres sin
necesitarlo es presupuesto que no estará disponible para construir.

## Antes de abrir archivos, consulta el grafo

Este proyecto usa **Graphify**: un grafo de conocimiento del código en `graph.json` que responde
dónde vive cada cosa y cómo se conecta, sin leer los archivos.

```
graphify query "dónde se resuelven las variables {{clave}}"
graphify path ModeloA ModeloB
graphify explain app/Actions/StartSopRun.php
```

Flujo correcto: **consultar el grafo → identificar los 1 a 3 archivos relevantes → abrir solo
esos**. Flujo incorrecto: abrir media carpeta "para entender el contexto".

Si el grafo no existe todavía, créalo (`/graphify .`) antes de la primera tarea grande.
Actualízalo (`graphify update`) después de cambios estructurales: carpetas movidas, modelos
renombrados, módulos nuevos. Un grafo desactualizado es peor que no tenerlo, porque te hace buscar
en el sitio equivocado.

## Nunca leas

`vendor/`, `node_modules/`, `public/build/`, `storage/logs/`, archivos `.lock`, migraciones ya
aplicadas que no vas a modificar, ni el historial completo de Git.

Si necesitas saber qué hace un paquete de `vendor/`, consulta su documentación, no su código.

## Lee de forma quirúrgica

- Necesitas una función: lee esa función, no el archivo de 600 líneas.
- Necesitas el nombre de una columna: consulta `docs/02-modelo-de-datos.md` o `sql/schema-v1.sql`,
  que para eso existen, en vez de rastrear migraciones.
- Necesitas saber cómo se llama un método: búscalo, no leas la clase entera.

## Escribe de forma quirúrgica

- Edita por diferencias. No reescribas un archivo completo para cambiar tres líneas.
- No reimprimas en el chat el contenido de un archivo que acabas de escribir. Di qué cambiaste.
- No generes documentación, comentarios ni ejemplos que nadie pidió.

## Tareas pequeñas

Una tarea = un objetivo verificable. Cuando una petición sea grande, propón dividirla y confirma el
plan antes de escribir código. Es más barato corregir un plan de diez líneas que 400 líneas de
implementación equivocada.

## No repitas el contexto

`AGENTS.md`, las reglas y los documentos de `docs/` ya están disponibles. No los resumas ni los
cites extensamente en tus respuestas: refiérete a ellos por nombre.

## Al terminar una tarea

Responde con: qué archivos tocaste, qué debe probar el usuario manualmente, y qué queda pendiente.
Tres líneas. Sin recapitular todo lo que hiciste paso a paso.
