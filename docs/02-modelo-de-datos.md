# 02 — Modelo de datos

Este documento es la fuente de verdad del esquema. Si el código y este documento discrepan, se
corrige uno de los dos de forma deliberada —nunca se deja la discrepancia.

El DDL completo está en `sql/schema-v1.sql`. Aquí se explica el **porqué** de cada pieza y el
contrato de los bloques del SOP, que es la parte que no se deduce leyendo tablas.

---

## 1. Idea central: el SOP como programa

Un SOP tiene dos caras:

- **La plantilla** (`sops` + `sop_versions`): la definición, versionada. Es el "código fuente".
- **La ejecución** (`sop_runs` + `sop_run_steps`): una corrida concreta con datos concretos. Es el
  "proceso en ejecución".

Separarlas es lo que permite responder "¿con qué versión del procedimiento se hizo este onboarding
en marzo?" —pregunta que aparece en cuanto el proceso cambia y algo sale mal.

---

## 2. Los bloques

Los bloques de una versión de SOP se guardan en la columna `sop_versions.blocks` (tipo `JSON`), como
un arreglo ordenado. Guardarlos como JSON y no como tabla es deliberado: una versión es inmutable y
se lee siempre entera, así que no gana nada con normalizarse, y el versionado se vuelve trivial.

### Estructura general

```json
{
  "schema_version": 1,
  "blocks": [
    { "id": "b1", "type": "input", "props": { ... } },
    { "id": "b2", "type": "ai_task", "props": { ... } }
  ]
}
```

`id` es único dentro de la versión y **estable**: los pasos de ejecución lo referencian. No lo
reutilices ni lo renumeres al reordenar bloques.

### Tipos de bloque

**`heading`** — título de sección.
```json
{ "text": "Fase 1: información de marca" }
```

**`text`** — instrucciones para la persona. `html` saneado al guardar y escapado al mostrar.
```json
{ "html": "<p>Confirma con el cliente el acceso al Business Manager.</p>" }
```

**`checklist`** — verificaciones manuales.
```json
{ "items": [ { "id": "i1", "text": "Acceso a BM recibido", "required": true } ] }
```

**`input`** — captura un dato. **Define una variable**: su `key` es lo que luego se referencia como
`{{key}}`.
```json
{
  "key": "nombre_marca",
  "label": "Nombre de la marca",
  "field": "text",
  "required": true,
  "help": "Como aparece en la facturación",
  "options": [],
  "filled_by": "client"
}
```
`field`: `text` · `textarea` · `number` · `date` · `select` · `multiselect` · `url` · `file`.
`filled_by`: `team` o `client` — decide quién ve el campo y dónde.
`key` es obligatoria, única en la versión, en minúsculas con guiones bajos.

**`media`** — video de referencia (Loom, YouTube), imagen o archivo adjunto.
```json
{ "kind": "video", "url": "https://...", "caption": "Cómo pedir accesos" }
```

**`decision`** — bifurcación. Los bloques no listados en ninguna rama se ejecutan siempre.
```json
{
  "question": "¿El cliente ya tiene píxel instalado?",
  "branches": [
    { "label": "Sí", "goto": "b8" },
    { "label": "No", "goto": "b6" }
  ]
}
```

**`ai_task`** — el bloque que hace el trabajo. Consume variables y produce una salida.
```json
{
  "skill_slug": "brief-de-marca",
  "mode": "internal",
  "model": "openrouter/auto",
  "inputs": { "marca": "{{nombre_marca}}", "objetivo": "{{objetivo_campana}}" },
  "output_key": "brief",
  "requires_approval": true,
  "instructions_override": null
}
```
`mode`: `internal` (llama a OpenRouter) o `export` (genera Markdown para IA externa y espera
reimportación). `output_key` **define una variable nueva** disponible para bloques posteriores.
`requires_approval` es `true` por defecto y solo se baja conscientemente.

**`approval`** — punto de revisión humana explícito.
```json
{ "role": "editor", "instructions": "Verifica tono de marca antes de continuar" }
```

**`handoff`** — entrega el control al cliente o al equipo y notifica.
```json
{ "to": "client", "message": "Revisa el brief y confirma", "notify": true }
```

---

## 3. El sistema de variables

Es el mecanismo que convierte un documento en un programa. Reglas:

1. Una variable se **define** de dos maneras: por la `key` de un bloque `input`, o por la
   `output_key` de un bloque `ai_task`.
2. Se **usa** con `{{clave}}` dentro de los `inputs` de un `ai_task`, del `message` de un `handoff`
   o del texto de un `text`.
3. Solo puede usarse una variable definida **antes** en el orden de los bloques. Una referencia
   hacia adelante es un error de validación, no una advertencia.
4. Los nombres son `snake_case`, sin espacios ni acentos.

La validación corre en dos sitios: en el editor de Vue mientras se escribe (marcando en rojo), y en
el backend al **publicar** una versión. Una versión con variables sin resolver no se publica.

El resolutor vive en `app/Support/Sop/VariableResolver.php` y es lógica pura: recibe el arreglo de
bloques y el mapa de valores, devuelve el texto resuelto o lanza `VariableUnresolved`.

---

## 4. Tablas

Se listan solo las propias del proyecto. Las de Laravel (`users`, `sessions`, `jobs`,
`failed_jobs`), las de equipos del starter kit y las de `spatie/laravel-permission` y
`spatie/laravel-activitylog` las crean sus paquetes.

### Clientes

**`clients`** — cliente de la agencia.
`id`, `team_id`, `name`, `slug`, `contact_email`, `status` (`active|paused|archived`), `meta` JSON,
timestamps, soft deletes.

**`client_user`** — usuarios externos con acceso al portal.
`id`, `client_id`, `user_id`, `role` (`owner|collaborator`), `invited_at`, `accepted_at`.

**`client_documents`** — entregables revisados y publicados de forma inmutable hacia el portal del cliente.
`id`, `team_id`, `client_id`, `sop_run_id` nullable, `title`, `markdown` LONGTEXT, `published_by`,
`published_at`, `revoked_at` nullable, timestamps. Índice en (`team_id`, `client_id`).

### SOPs

**`sops`** — la plantilla.
`id`, `team_id`, `title`, `slug`, `description`, `category`, `status` (`draft|published|archived`),
`is_template` bool, `current_version_id` nullable, `created_by`, timestamps, soft deletes.
Índice único en (`team_id`, `slug`).

**`sop_versions`** — cada publicación.
`id`, `sop_id`, `version_number` int, `blocks` JSON, `changelog` text, `published_at` nullable,
`created_by`, `created_at`.
Índice único en (`sop_id`, `version_number`). `published_at` nulo = borrador.

### Ejecuciones

**`sop_runs`**
`id`, `team_id`, `sop_id`, `sop_version_id`, `client_id` nullable, `title`,
`status` (`pending|in_progress|awaiting_input|awaiting_approval|completed|cancelled`),
`inputs` JSON (valores capturados), `outputs` JSON (resultados por `output_key`),
`started_by`, `assigned_to` nullable, `started_at`, `completed_at`, timestamps.

**`sop_run_steps`** — un registro por bloque ejecutable de la corrida.
`id`, `sop_run_id`, `block_id` string, `block_type` string,
`status` (`pending|running|awaiting_approval|approved|rejected|skipped|failed`),
`assigned_to` nullable, `output` JSON nullable, `ai_generation_id` nullable, `notes` text,
`due_at` nullable, `completed_at` nullable, timestamps.

### IA

**`skills`** — pieza reutilizable de instrucciones.
`id`, `team_id`, `name`, `slug`, `description`, `tags` JSON, `current_version_id` nullable,
`created_by`, timestamps, soft deletes. Único en (`team_id`, `slug`).

**`skill_versions`**
`id`, `skill_id`, `version_number`, `instructions` LONGTEXT, `variables` JSON,
`source` (`manual|openrouter|import_markdown`), `changelog`, `created_by`, `created_at`.

**`ai_credentials`** — claves de proveedor. `api_key` **cifrada** con el cast `encrypted`.
`id`, `team_id`, `provider` (`openrouter`), `label`, `api_key` text, `is_active`, `last_used_at`,
`created_by`, timestamps.

**`ai_generations`** — registro de cada llamada. Es la tabla de auditoría y de costos.
`id`, `team_id`, `sop_run_id` nullable, `sop_run_step_id` nullable, `skill_version_id` nullable,
`provider`, `model`, `prompt` LONGTEXT, `response` LONGTEXT nullable,
`input_tokens`, `output_tokens`, `cost_usd` DECIMAL(10,6), `latency_ms`,
`status` (`queued|running|succeeded|failed`), `error` text nullable, timestamps.
Índices en `team_id`, `status`, `created_at`. **Esta tabla crece rápido**: programa archivado.

### Automatización

**`automation_triggers`**
`id`, `team_id`, `event` (`client.created`, `run.completed`, `run.step.approved`), `sop_id`,
`conditions` JSON, `is_active`, `created_by`, timestamps.

### Exportación e importación

**`skill_exports`**
`id`, `team_id`, `skill_version_id`, `target` (`markdown|gpt|gem|claude_project`),
`payload` LONGTEXT, `generated_by`, `created_at`.

### Cumplimiento

**`data_requests`** — solicitudes de exportación o borrado de datos.
`id`, `team_id`, `client_id` nullable, `type` (`export|delete`), `status`, `requested_by`,
`completed_at`, `file_path` nullable, timestamps.

---

## 5. Reglas transversales

- **Toda tabla lleva `team_id`** con índice y clave foránea, salvo las pivote que cuelgan de una que
  ya lo tiene (`client_user`, `sop_run_steps`).
- **Todo `id` es `bigIncrements`**; las claves foráneas, `foreignId`.
- **Borrados**: `cascadeOnDelete` de versiones al borrar su padre; `nullOnDelete` en referencias a
  usuarios (que un usuario se vaya no debe borrar el historial).
- **Fechas** en UTC en base de datos, mostradas en la zona horaria del equipo.
- **Nada de datos de tarjetas, documentos de identidad ni credenciales de terceros** en estas
  tablas. Si un SOP necesita que el cliente entregue algo así, se pide fuera de la plataforma.
