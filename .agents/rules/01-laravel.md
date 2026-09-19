---
trigger: glob
globs: ["**/*.php"]
description: Convenciones de backend Laravel para este proyecto
---

# Convenciones de backend

## Estructura de responsabilidades

- **Controladores delgados.** Un controlador valida (vía Form Request), autoriza (vía Policy) y
  delega. Si un método pasa de ~20 líneas, la lógica va a una Action.
- **Actions.** Una clase por caso de uso en `app/Actions/`, con un único método público `handle()`.
  Ejemplos: `CreateSopVersion`, `StartSopRun`, `ApproveRunStep`, `ImportSkillFromMarkdown`.
- **Jobs.** Todo lo lento o falible va a `app/Jobs/`. Obligatorio para IA, correos y exportaciones
  pesadas.
- **Services.** Integraciones externas en `app/Services/`, con interfaz propia para poder
  sustituirlas o simularlas en pruebas.
- **Support.** Lógica pura sin dependencias de framework en `app/Support/` (parser de bloques,
  resolutor de variables `{{clave}}`).

## Multi-tenancy — la regla que no se rompe

Toda tabla de negocio lleva `team_id` con índice y clave foránea. Todo modelo correspondiente usa el
trait `BelongsToTeam`, que aplica un global scope filtrando por el equipo activo y rellena `team_id`
al crear.

```php
// app/Models/Concerns/BelongsToTeam.php
trait BelongsToTeam
{
    protected static function bootBelongsToTeam(): void
    {
        static::addGlobalScope('team', function (Builder $query) {
            if ($teamId = currentTeamId()) {
                $query->where($query->getModel()->getTable().'.team_id', $teamId);
            }
        });

        static::creating(function ($model) {
            $model->team_id ??= currentTeamId();
        });
    }
}
```

Si alguna vez necesitas saltarte el scope (comandos de consola, jobs en cola), usa
`withoutGlobalScope('team')` **explícitamente y con un comentario que diga por qué**. Un job en cola
no tiene sesión: debe recibir el `team_id` en su constructor y establecerlo al arrancar.

## Consultas

- Nada de `DB::raw` con interpolación de variables. Siempre bindings.
- Prohibido el N+1: usa `with()` para relaciones que vas a recorrer.
- Paginación obligatoria en cualquier listado que pueda crecer (SOPs, ejecuciones, generaciones).
- Para leer dentro de columnas JSON en MySQL usa la sintaxis de Laravel (`where('meta->clave', ...)`),
  no funciones específicas de Postgres.

## Migraciones

- Una migración por cambio, nombrada en inglés y descriptiva.
- **Nunca edites una migración ya aplicada en producción.** Crea una nueva.
- Toda clave foránea declarada, con `cascadeOnDelete` o `nullOnDelete` decidido conscientemente.
- Índices en `team_id` y en toda columna por la que filtres o ordenes habitualmente.
- Columnas JSON con `$table->json('blocks')`; no uses `text` para guardar JSON.
- `softDeletes()` en `sops`, `skills` y `clients`; borrado real solo por proceso de eliminación de
  datos.

## Validación y autorización

- Toda entrada del usuario pasa por un Form Request con reglas explícitas. Nada de `$request->all()`
  hacia un `create()`.
- Toda acción sobre un recurso pasa por una Policy. No confíes en que la ruta esté protegida.
- Los usuarios con rol `cliente` solo acceden a recursos explícitamente asignados; verifica la
  asignación, no solo el rol.

## Errores

- Excepciones de dominio propias en `app/Exceptions/` (`SopBlockNotFound`, `VariableUnresolved`,
  `AiProviderFailed`), no `\Exception` genérica.
- En los Jobs, captura la excepción, registra el fallo en la fila correspondiente
  (`ai_generations.status = 'failed'`, con `error`) y **relanza** para que el reintento de la cola
  funcione.
- Nunca registres en el log el contenido de una clave de API ni el prompt completo si contiene
  datos personales del cliente.

## Pruebas

Como mínimo, pruebas de feature para: aislamiento por `team_id` (un equipo no ve datos de otro),
creación y versionado de un SOP, ejecución de un SOP con variables, y el flujo de aprobación de una
salida de IA. Usa una base de datos SQLite en memoria para las pruebas, no la de producción.
