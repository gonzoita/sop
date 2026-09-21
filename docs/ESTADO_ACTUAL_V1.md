# SOPForge — Estado del Proyecto v1.0 (MVP) y Especificación para Claude

> **Propósito de este documento:** Este archivo sirve como **contexto maestro y transferencia técnica completa** del estado de la plataforma SOPForge en su versión 1.0 (MVP en producción). Léelo para entender la arquitectura, las decisiones cerradas, qué está 100% implementado, cómo opera el entorno de despliegue y cuáles son las prioridades inmediatas.

---

## 1. Visión y Propósito del Software

**SOPForge** es una plataforma web especializada para agencias de marketing digital y empresas de servicios, diseñada para crear, versionar y **ejecutar** SOP (Procedimientos Operativos Estándar) interactivos.

A diferencia de un gestor de documentos estático (Notion, Google Docs), SOPForge convierte un procedimiento en un **programa ejecutable**:
1. **Captura de datos mediante variables:** Algunos bloques del SOP son campos de entrada (`input`) cuyas respuestas se capturan y referencian dinámicamente como `{{clave}}`.
2. **Generación con IA:** Otros bloques consumen esas variables y redactan entregables reales (briefs, copys de anuncios, estructuras web, reportes) usando OpenRouter (Vía A) o exportando/importando Markdown con front-matter para trabajar con Gemini, ChatGPT o Claude (Vía B).
3. **Aprobación Humana Obligatoria:** Ninguna salida de IA se da por válida ni se propaga a los pasos siguientes sin la revisión y aprobación explícita de una persona (quien puede editar el texto antes de aprobarlo).
4. **Portal Externo de Clientes:** Los clientes de la agencia acceden a un portal aislado (`/portal`) donde solo ven y completan las encuestas y pasos que se les asignaron, sin acceso a la agencia, skills ni otros clientes.

---

## 2. Stack Tecnológico y Restricciones Cerradas

> ⚠️ **REGLA CRÍTICA DE ARQUITECTURA:** Las tecnologías de la siguiente tabla son **decisiones cerradas**. No deben cambiarse ni proponerse alternativas incompatibles (ej. NO Redis, NO Docker en servidor, NO Horizon).

| Capa | Tecnología | Notas / Restricciones |
|---|---|---|
| **Lenguaje** | PHP 8.3 / 8.4 | Producción corre sobre PHP 8.3.33 / 8.4 en CloudLinux |
| **Framework** | Laravel 11 / 12 (v13 compatible) | Laravel Jetstream + Fortify |
| **Frontend** | Vue 3 (Composition API, `<script setup>`) | Sobre Inertia.js |
| **Estilos** | Tailwind CSS | Interfaz moderna, minimalista y responsiva |
| **Base de Datos** | MariaDB 11.8+ / MySQL 8 | Columnas `JSON` para bloques inmutables y metadatos |
| **Colas (Queue)** | Driver `database` | **Sin demonios persistentes**. Avanza mediante el scheduler |
| **Caché / Sesión** | `database` o `file` | Prohibido Redis |
| **Archivos** | Disco local `storage/app` + symlink | Subidas aisladas en carpetas privadas |
| **Permisos** | `spatie/laravel-permission` | Roles: `admin`, `editor`, `ejecutor`, `cliente` |
| **Auditoría** | `spatie/laravel-activitylog` | Registro inmutable de cada acción en el sistema |
| **Proveedor IA** | OpenRouter API (OpenAI-compatible) | Timeout 60s, presupuesto y control de costes en USD |
| **Hosting** | Hostinger compartido (`sop.briela.app`) | Sin acceso root, sin Node.js en servidor |

### Restricciones duras del entorno Hostinger:
1. **Sin procesos demonio:** No existe `supervisor` ni `queue:work` permanente. El cron de Hostinger ejecuta cada minuto `php artisan schedule:run`, y dentro de `routes/console.php` el scheduler ejecuta `queue:work --stop-when-empty --max-time=50`.
2. **Cero llamadas de IA en el ciclo HTTP:** `max_execution_time` en servidor es bajo (60-120s). Toda llamada a OpenRouter va **obligatoriamente** en un Job asíncrono (`RunAiTask`).
3. **Assets compilados en local:** En el servidor no hay Node/npm. Todo cambio frontend se compila localmente con `npm run build` y se sube la carpeta `public/build` versionada en Git.
4. **Respaldo antes de migrar:** Antes de cualquier `php artisan migrate --force` en producción, se genera un dump de la base de datos en `~/backups/backup_*.sql`.

---

## 3. Estado de lo Implementado (v1.0 MVP Desplegado)

El software se encuentra en producción en **`https://sop.briela.app`** con 127 pruebas automatizadas pasando al 100% (508 assertions) y árbol de Git limpio.

### A. Autenticación y Multi-Tenancy
- Registro de cuentas nuevas en `/register`. Cada usuario nuevo crea su propio espacio de trabajo (**Team**) y recibe automáticamente el rol `admin` de su workspace.
- Aislamiento estricto por equipo (`team_id`) mediante el trait global `BelongsToTeam` en todos los modelos de negocio. Una consulta nunca mezcla datos de dos equipos.
- Autenticación de Dos Factores (2FA) integrada con Jetstream/Fortify.
- Middleware `admin.2fa`: Para ingresar a la configuración sensible (`/admin/*`), un usuario con rol `admin` debe tener 2FA activado.

### B. Constructor Visual de SOPs (`/sops`)
- Creación, edición, archivado y duplicación de SOPs con un clic.
- **Versionado inmutable (`sop_versions`):** Los cambios se guardan en borrador (`status: draft`). Al hacer clic en *"Publicar Versión"*, se valida la sintaxis y se congela la versión como inmutable (`v1`, `v2`, etc.).
- **Bloques interactivos soportados:**
  - `heading`: Títulos de sección.
  - `text` / `instruction`: Texto informativo con soporte para variables resueltas en vivo (`v-html="resolveText(block.props?.html)"`).
  - `input`: Campos de texto, número, fecha, select o textarea. Define una variable `{{key}}`. Soporta atributo `filled_by: team|client`.
  - `checklist`: Casillas de verificación con ítems obligatorios requeridos para poder avanzar.
  - `decision`: Bifurcación condicional con saltos dinámicos (`goto`). Al seleccionar una opción en la ejecución, los pasos de las ramas no seleccionadas se omiten automáticamente (`skipped`).
  - `media`: Enlaces a videos (Loom, YouTube) o recursos externos.
  - `ai_task`: Bloque que delega trabajo a un Skill de IA.
  - `approval`: Puerta de aprobación por rol de equipo.
  - `handoff`: Transferencia de control entre equipo y cliente.

### C. Motor de Ejecución en Vivo (`/runs`)
- Iniciar una ejecución clona y congela la versión actual del SOP en `sop_runs` y genera los pasos individuales en `sop_run_steps`.
- Avance interactivo paso a paso con barra de progreso porcentual en tiempo real.
- Asignación de pasos específicos a usuarios del equipo con fechas límite (`due_at`) y notas.
- **Captura reactiva de variables:** Al guardar un `input`, el valor se almacena en `sop_runs.inputs` y en el panel lateral *"Variables Capturadas"*.
- **Resolución en vivo:** Los bloques de texto posteriores que usan `{{nombre_variable}}` muestran automáticamente el valor real recién introducido en la pantalla.

### D. Motor de IA y Biblioteca de Skills (`/skills` y `/admin/ai-settings`)
- **Gestión de Claves de IA:** Almacenamiento con cifrado simétrico AES-256 (`cast: encrypted`), mutador `masked_api_key` (`••••••••1234`), oculto en JSON y excluido de logs.
- **Control de Presupuesto:** Límite mensual en USD (`monthly_limit_usd`) y umbral de alerta (`alert_at_percent`). Corte preventivo si se supera el presupuesto.
- **Librería de Skills Versionada:**
  - Prompts estructurados con System Prompt e Instrucciones de Usuario.
  - Detección automática y extracción de variables `{{clave}}` mediante `VariableResolver`.
  - Historial inmutable de versiones con notas de cambio (changelog).
- **Vía A (Ejecución Interna con OpenRouter):**
  - Job en cola `RunAiTask`. Llama a OpenRouter mediante cliente HTTP desacoplado (`AiProvider`).
  - Registra tokens de entrada/salida, latencia en ms y costo real en USD en la tabla `ai_generations`.
  - Estado `awaiting_approval`: El entregable generado **no se propaga** a las variables del SOP hasta que un humano lo aprueba en la interfaz. El revisor puede editar el texto generado antes de aprobarlo.
- **Vía B (Exportación / Importación Markdown):**
  - Exportación de cualquier Skill a un archivo `.md` con front-matter YAML listo para copiar en Gemini, Claude o ChatGPT.
  - Importación de archivos `.md` que genera una nueva versión del Skill de forma automática.
- **Panel de Consumo:** Métricas agregadas del mes (total de llamadas, tokens consumidos, gasto en USD) e historial de ejecuciones recientes.

### E. Portal de Clientes Externos (`/portal`)
- Vista independiente para usuarios con rol `cliente` (`ClientPortalController`).
- Aislamiento total: El cliente **solo ve los bloques donde `filled_by === 'client'`** de las ejecuciones asignadas a su empresa.
- Cero visibilidad de skills, prompts internos, otros clientes o costos de la agencia.
- Soporte para subida de archivos adjuntos por el cliente en almacenamiento privado (`client_files`).

### F. Exportaciones
- **Exportar PDF:** Generación con `barryvdh/laravel-dompdf` de la plantilla del SOP con portada formal, tabla de variables y maquetación para impresión.
- **Exportar Markdown:** Descarga de la plantilla estructurada en formato `.md`.

---

## 4. Hallazgos y Pendientes Identificados en las Pruebas

Durante la prueba completa del MVP (realizada por el usuario y su colaborador David), se validó el flujo y se detectaron las siguientes necesidades clave:

### 1. Servicio de Correos Transaccionales (Pendiente de Configuración)
- **Situación:** Las invitaciones al portal de clientes se generan con tokens válidos, pero el servidor no tiene configurado un servicio SMTP / API (Resend, Mailgun o el correo propio del hosting).
- **Acción requerida:** Configurar `.env` con las credenciales SMTP para que las invitaciones lleguen a la bandeja de entrada del cliente.

### 2. Exportación de Ejecución a Markdown con Datos Reemplazados
- **Situación:** Actualmente el botón *"Exportar Markdown"* existe en la **plantilla del SOP** (descarga la estructura vacía con `{{tags}}`).
- **Necesidad:** En una **Ejecución concreta** (`/runs/{id}`), se necesita un botón *"Exportar Entregable Markdown"* que genere un archivo `.md` con todos los `{{tags}}` **ya reemplazados por las respuestas reales del cliente**.

### 3. Expediente Centralizado por Cliente ("Ficha del Cliente" - `clients.show`)
- **Situación:** En la base de datos, `Client` ya tiene la relación `hasMany(SopRun)`. Todos los datos de encuestas (`inputs`), entregables de IA (`outputs`) y archivos subidos quedan asociados al `client_id`. Sin embargo, en el frontend solo existe la tabla listado (`clients.index`).
- **Necesidad:** Crear la vista `clients.show` (la Ficha del Cliente) que consolide:
  1. Todos los datos recopilados del cliente a través de sus distintos SOPs.
  2. El repositorio de todos los Markdowns y entregables finales producidos para él.
  3. Los archivos y accesos subidos.

### 4. Pestaña de Documentos en el Portal del Cliente
- **Situación:** En `/portal`, el cliente entra a responder las preguntas asignadas, pero una vez terminado el SOP no tiene un lugar donde ver el documento final resultante.
- **Necesidad:** Crear una pestaña o sección *"Mis Documentos / Entregables"* en `/portal` para que el cliente pueda descargar en PDF o Markdown el brief o documento final aprobado.

---

## 5. Mapa Rápido de Archivos del Código

```
app/
  Models/
    Client.php              -> Modelo de cliente (hasMany SopRun, hasMany ClientFile)
    Sop.php                 -> Plantilla base del SOP (team_id, current_version_id)
    SopVersion.php          -> Versión inmutable con columna JSON 'blocks'
    SopRun.php              -> Ejecución congelada (inputs JSON, outputs JSON)
    SopRunStep.php          -> Pasos individuales ejecutables de la corrida
    Skill.php               -> Skill de IA (prompt reutilizable)
    SkillVersion.php        -> Versión inmutable del skill con variables y changelog
    AiCredential.php        -> Claves cifradas de OpenRouter ('api_key' => 'encrypted')
    AiBudget.php            -> Límite mensual en USD por equipo
    AiGeneration.php        -> Registro auditable de tokens, costo USD y latencia
  Http/Controllers/
    SopController.php       -> CRUD de plantillas, guardado de borrador, publicación, PDF/MD
    SopRunController.php    -> Inicio de ejecución, avance de pasos, aprobación de IA
    SkillController.php     -> CRUD de skills, versionado, export/import markdown
    Admin/AiSettingController.php -> Panel de consumo, presupuesto y credenciales
    Portal/ClientPortalController.php -> Portal aislado para clientes externos
  Jobs/
    RunAiTask.php           -> Job en cola que valida presupuesto y llama a OpenRouter
  Services/OpenRouter/
    OpenRouterClient.php    -> Cliente HTTP hacia la API de OpenRouter
    FakeAiProvider.php      -> Mock para pruebas unitarias sin costo
resources/js/
  Pages/
    Sops/Index.vue, Edit.vue, Show.vue  -> Constructor y vista de SOPs
    Runs/Index.vue, Show.vue            -> Pantalla de ejecución interactiva
    Skills/Index.vue, Show.vue          -> Biblioteca de prompts y versionado
    Admin/Ai/Index.vue                  -> Panel de consumo de IA y presupuesto
    Portal/Runs/Index.vue, Show.vue     -> Experiencia del cliente externo
```

---

## 6. Guía para Claude: Cómo Trabajar en este Proyecto

Cuando el usuario te pida implementar una nueva función:
1. **Respeta las reglas de oro:** No propongas Redis, no inventes migraciones destructivas (`migrate:fresh` está prohibido), y mantén siempre el aislamiento por `team_id`.
2. **Textos en español, código en inglés:** Nombres de clases, métodos, variables y tablas en inglés. Interfaces, botones, mensajes y commits en español.
3. **Flujo de despliegue:**
   - Escribir código limpio.
   - Ejecutar pruebas con `php artisan test`.
   - Compilar frontend en local con `npm run build`.
   - Hacer commit claro en Git y push a `master`.
   - En el servidor de Hostinger: respaldar BD con `mysqldump`, hacer `git pull`, ejecutar `php artisan migrate --force` si hubo migraciones, y limpiar cachés con `config:cache`, `route:cache`, `view:cache`.
