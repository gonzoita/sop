# AGENTS.md — SOPForge

Contexto maestro del proyecto. Léelo completo antes de escribir código. Las reglas detalladas están
en `.agents/rules/`; la documentación de diseño en `docs/`. Cuando algo de este archivo choque con
una instrucción directa del usuario en el chat, gana el usuario.

Idioma: **todo en español** — comentarios, mensajes de commit, textos de interfaz, nombres de rutas
visibles. El código (clases, métodos, variables, tablas, columnas) va en **inglés**, como es
convención en Laravel.

---

## 1. Qué es esto

Plataforma web para crear, versionar y **ejecutar** SOP (procedimientos operativos estándar) de una
agencia de marketing digital. Dos cosas la separan de un editor de documentos:

1. Un SOP se arma con bloques arrastrables, y algunos bloques son **campos de entrada** cuyo valor
   se referencia después con `{{clave}}`.
2. Otros bloques son **tareas de IA** que consumen esas variables y producen entregables reales
   (borradores de brief, copys, estructuras de campaña, reportes), ya sea llamando a OpenRouter
   desde el servidor o exportando Markdown para procesarlo en una IA externa y reimportarlo.

El objetivo declarado: que llenar los datos de entrada de un SOP adelante **más del 50% del trabajo
real**, dejando a las personas la revisión y las decisiones, no la ejecución repetitiva.

Usuarios: el equipo interno de la agencia (roles admin / editor / ejecutor) y **clientes externos**
con acceso restringido a un portal donde ven y completan solo los SOP que se les asignaron.

Detalle completo: `docs/00-vision-y-alcance.md`.

---

## 2. Stack — decisiones cerradas

No las re-discutas ni propongas alternativas salvo que el usuario lo pida.

| Capa | Decisión |
|---|---|
| Lenguaje | PHP 8.3 mínimo (8.4 en servidor de producción) |
| Framework | Laravel, versión estable vigente (v13+) |
| Frontend | Vue 3 (Composition API, `<script setup>`) sobre Inertia.js |
| Estilos | Tailwind CSS |
| Base de datos | **MySQL 8 / MariaDB 11.8+** (no PostgreSQL) con columnas `JSON` |
| Colas | Driver `database` + cron (no Redis, no Horizon, no supervisor) |
| Caché / sesión | `database` o `file` (no Redis) |
| Archivos | Disco local `storage/app` + `storage:link` |
| IA | OpenRouter (API compatible con OpenAI) |
| Permisos | `spatie/laravel-permission` |
| Auditoría | `spatie/laravel-activitylog` |
| Hosting | Hostinger, hosting compartido, subdominio propio |

**Importante:** el plan original de este proyecto contemplaba PostgreSQL, Redis y Horizon. Quedó
descartado al mover el despliegue a hosting compartido de Hostinger. Si encuentras esas tecnologías
mencionadas en algún documento viejo, la tabla de arriba es la que manda.

---

## 3. Restricciones del entorno — esto condiciona todo

El hosting compartido de Hostinger **no puede** correr procesos permanentes. Consecuencias directas
que debes respetar en cada decisión de diseño:

- **No hay demonios.** Nada de `queue:work` permanente ni `supervisor`. Las colas avanzan con un
  cron que ejecuta `schedule:run` cada minuto, y el scheduler dispara
  `queue:work --stop-when-empty --max-time=50`.
- **No hay Redis.** Colas, caché y sesiones van a MySQL o a archivos.
- **`max_execution_time` es bajo** (60–120 s). Ninguna llamada a IA puede ocurrir dentro del ciclo
  petición→respuesta. Toda llamada a OpenRouter va **obligatoriamente** dentro de un Job en cola.
- **Probablemente no hay Node/npm en el servidor.** Los assets se compilan **localmente** con
  `npm run build` y se sube `public/build` ya compilado. Nunca asumas que puedes compilar en
  producción.
- **No hay root.** Nada de instalar extensiones de PHP, servicios ni paquetes del sistema.
- **La app vive fuera de `public_html`.** `public_html` apunta (por symlink) a la carpeta `public`
  de Laravel. Ver `docs/05-deploy-hostinger.md`.

Si una tarea parece exigir algo de esa lista, **detente y dilo** en vez de improvisar una solución
que no va a correr en producción.

---

## 4. Mapa del repositorio

```
app/
  Models/                  Eloquent. Todo modelo con team_id usa el trait BelongsToTeam
  Http/Controllers/        Delgados: validan y delegan en Actions
  Actions/                 Lógica de negocio, una clase por caso de uso
  Jobs/                    Trabajo en cola (todo lo que llame a IA vive aquí)
  Services/OpenRouter/     Cliente HTTP de OpenRouter y mapeo de respuestas
  Support/Sop/             Parser de bloques, resolutor de variables {{clave}}
  Policies/                Autorización por recurso
resources/js/
  Pages/                   Páginas Inertia
  Components/Sop/          Bloques del constructor, uno por tipo
  Composables/             Lógica Vue reutilizable
database/migrations/       Una migración por cambio, nunca editar una ya desplegada
docs/                      Diseño (leer antes de implementar)
prompts/                   Prompts de construcción por fase
```

---

## 5. Reglas de oro

1. **Verifica antes de asumir.** Versiones de PHP, Laravel, starter kits y nombres de paquetes
   cambian. Antes de instalar algo, comprueba qué versión estable existe hoy. No escribas un
   `composer.json` de memoria.
2. **Todo recurso pertenece a un equipo.** Cada tabla de negocio lleva `team_id`. Cada modelo
   correspondiente aplica un *global scope* que filtra por el equipo activo. Una consulta sin ese
   filtro es una fuga de datos entre clientes, no un detalle de estilo.
3. **Los secretos nunca entran al repositorio.** Claves de OpenRouter, credenciales de base de
   datos y `APP_KEY` viven solo en `.env` del servidor. `.env` está en `.gitignore`. Las claves de
   API que guarde la aplicación van cifradas con el cast `encrypted` de Eloquent.
4. **Ninguna llamada a IA fuera de una cola.** Sin excepciones. Ver restricción de
   `max_execution_time` arriba.
5. **Toda salida de IA pasa por aprobación humana** antes de considerarse entregable. El estado
   `awaiting_approval` no es opcional en el flujo.
6. **Migraciones hacia adelante.** Nunca edites una migración ya aplicada en producción; crea una
   nueva. Nunca uses `migrate:fresh` ni `migrate:refresh` contra la base de datos de producción.
7. **Un cambio, un commit, un mensaje claro en español.** Nada de commits gigantes que mezclan
   tres fases.
8. **Si no estás seguro, pregunta.** Es preferible una pregunta a media implementación que 400
   líneas en la dirección equivocada.

---

## 6. Cómo trabajar (disciplina de contexto)

Este proyecto se construye con presupuesto de tokens limitado. Antes de leer archivos a lo ancho:

- Consulta el **grafo de Graphify** (`graph.json`) con `graphify query` / `graphify path` /
  `graphify explain` para ubicar qué archivos importan. Abre solo esos.
- No vuelques archivos completos al contexto si te basta una función. No leas `vendor/`,
  `node_modules/`, `public/build/` ni migraciones antiguas.
- Después de cambios estructurales (mover carpetas, renombrar modelos, añadir módulos), **actualiza
  el grafo** antes de seguir.
- Trabaja en tareas pequeñas y verificables. Al terminar cada una, di qué archivos tocaste y qué
  falta.

Detalle en `.agents/rules/05-ahorro-de-tokens.md` y `docs/06-graphify-y-tokens.md`.

---

## 7. Producción

El usuario trabaja directamente contra su servidor de Hostinger. Eso significa que un error tuyo es
un error visible. Antes de cualquier acción que toque el esquema o los datos:

- Respaldo de base de datos **antes** de correr migraciones. Sin respaldo, no hay migración.
- Los cambios de esquema se prueban primero en el subdominio de pruebas cuando exista.
- Nunca ejecutes comandos destructivos (`migrate:fresh`, `db:wipe`, borrado masivo, `rm -rf`) sin
  que el usuario lo haya pedido explícitamente en ese mismo mensaje.
- Si un despliegue queda a medias, dilo de inmediato y con claridad. No sigas construyendo encima.

Procedimiento completo en `docs/05-deploy-hostinger.md`.

---

## 8. Definición de "terminado"

Una tarea está terminada cuando: el código corre sin errores, respeta el `team_id`, tiene su
migración si hacía falta, no rompe rutas existentes, los textos visibles están en español, y le
dijiste al usuario qué probar manualmente para confirmarlo. Un "ya quedó" sin esa última parte no
sirve.
