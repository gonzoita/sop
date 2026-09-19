# 01 — Arquitectura sobre Hostinger

## Por qué esta arquitectura es distinta a la "ideal"

El plan original de este proyecto contemplaba PostgreSQL, Redis y Laravel Horizon sobre un VPS. Al
mover el despliegue a **hosting compartido de Hostinger**, ese diseño dejó de ser viable: no hay
root, no hay procesos permanentes y no hay Redis. Lo que sigue es el diseño que sí corre ahí, sin
perder ninguna funcionalidad del producto —solo cambiando cómo se implementa.

Si en algún momento migras a un VPS de Hostinger, casi nada de esto hay que rehacerlo: se cambian
drivers en `.env` y se añade un worker permanente. La sección final explica esa ruta.

## Stack

| Capa | Elección | Por qué |
|---|---|---|
| PHP | 8.3 mínimo (8.4 en producción) | Requisito de Laravel 13 |
| Framework | Laravel 13 (versión estable vigente) | Base del backend |
| Frontend | Vue 3 + Inertia.js | Vue real sin mantener una API aparte |
| Estilos | Tailwind CSS | Sin librería de componentes pesada |
| Base de datos | MySQL 8 / MariaDB 11.8+ | Es lo que ofrece el plan; `JSON` nativo alcanza |
| Colas | Driver `database` + cron | No hay Redis ni demonios |
| Caché / sesión | `database` o `file` | Igual |
| Archivos | `storage/app` + `storage:link` | Sin S3 al inicio |
| Búsqueda | `LIKE` + índices; Scout más adelante | Meilisearch necesita un servicio propio |
| IA | OpenRouter | Un solo proveedor, muchos modelos |

## Por qué Inertia y no una SPA separada

Con una SPA de Vue contra una API de Laravel tendrías que construir y mantener dos capas de rutas,
dos capas de validación, autenticación por tokens y documentación de endpoints. Siendo un solo
desarrollador, eso es trabajo duplicado permanente.

Con Inertia escribes componentes Vue normales —incluido el constructor drag & drop— pero el
controlador de Laravel les pasa las props directamente. Una sola capa de rutas, una sola de
validación, sesiones normales. Si algún día necesitas una API pública de verdad, la añades entonces
para los endpoints que la requieran.

## El problema de las colas sin demonios, y su solución

Las llamadas a IA tardan entre 5 y 60 segundos y a veces fallan. El `max_execution_time` del
servidor ronda los 60–120 segundos. Ejecutarlas dentro de una petición web es garantía de timeouts y
de usuarios mirando una pantalla congelada.

La solución en hosting compartido:

1. Las peticiones que disparan IA solo **encolan** un Job y responden de inmediato.
2. Un único cron ejecuta `php artisan schedule:run` cada minuto.
3. El scheduler de Laravel lanza un worker de vida corta:

```php
// routes/console.php
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping();
```

El worker procesa lo que haya en la cola y muere antes de que el siguiente minuto lo relance.
`withoutOverlapping()` evita que se apilen. El resultado práctico: una tarea de IA empieza a
ejecutarse en menos de un minuto desde que se pidió, lo cual es perfectamente aceptable para este
producto.

4. El frontend consulta el estado con polling ligero cada pocos segundos (no WebSockets, que aquí no
   existen) y muestra el resultado cuando el Job termina.

Si el plan de Hostinger no permite cron cada minuto sino cada cinco, todo funciona igual: solo hay
que decirle al usuario en la interfaz que el resultado puede tardar unos minutos.

## Estructura de carpetas en el servidor

La aplicación **no** vive dentro de `public_html`. Si viviera ahí, cualquiera podría leer `.env`
por URL.

```
/home/uXXXXXXX/domains/sop.tudominio.com/
├── app/                  ← proyecto Laravel completo
│   ├── app/ config/ database/ resources/ routes/ storage/ vendor/
│   ├── public/           ← el único directorio que debe ser accesible por web
│   └── .env              ← fuera del alcance del navegador
└── public_html           ← symlink → app/public
```

El detalle de cómo crear ese symlink y las alternativas si tu plan no lo permite están en
`docs/05-deploy-hostinger.md`.

## Assets

No hay Node en el servidor. El ciclo es: compilas en local con `npm run build`, y subes la carpeta
`public/build` generada. Conviene **no** ignorarla en Git, o bien tener un paso explícito de subida
en cada despliegue. Decide una de las dos y anótalo; la inconsistencia aquí produce el clásico
"funciona en local pero en producción se ve roto".

## Capas de la aplicación

```
Controlador   valida (Form Request) → autoriza (Policy) → delega
    ↓
Action        un caso de uso, un método handle()
    ↓
Job           todo lo lento o falible: IA, correos, exportaciones
    ↓
Service       integraciones externas (OpenRouter) tras una interfaz
```

`app/Support/Sop/` guarda la lógica pura: el parser de bloques y el resolutor de variables
`{{clave}}`. Sin dependencias de framework, para poder probarla sin base de datos.

## Rendimiento en compartido

El hosting compartido perdona poco. Tres cosas hacen casi toda la diferencia:

- `config:cache`, `route:cache` y `view:cache` tras cada despliegue.
- `composer install --no-dev --optimize-autoloader`.
- Índices en `team_id` y en las columnas por las que filtras. Un listado de ejecuciones sin índice
  se nota en cuanto hay unos miles de filas.

Vigila también el tamaño de `ai_generations`: guarda prompts y respuestas completas y crece rápido.
Programa una tarea que archive o purgue lo más antiguo según la política que definas.

## Ruta de salida hacia un VPS

Cuando el producto justifique el salto —y antes de que datos reales de muchos clientes vivan ahí—
migrar a un VPS de Hostinger implica: cambiar `QUEUE_CONNECTION` a `redis`, añadir Horizon con
supervisor, mover caché y sesiones a Redis, y opcionalmente montar Meilisearch para búsqueda.
Ningún cambio en el modelo de datos ni en la lógica de negocio. Diseñar pensando en esto no cuesta
nada hoy: basta con no acoplar el código a las particularidades del driver `database`.
