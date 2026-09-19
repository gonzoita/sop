# Prompt 00 — Bootstrap

Ejecuta este prompt **primero**, con el proyecto vacío salvo este paquete de contexto.

Su objetivo no es escribir la aplicación: es que el agente **verifique la realidad del entorno**
antes de instalar nada. La mayoría de los desastres de inicio vienen de asumir versiones.

---

## Copia desde aquí

```
Lee AGENTS.md, .agents/rules/00-stack-y-entorno.md y docs/01-arquitectura.md antes de responder.

Esta es una tarea de VERIFICACIÓN Y PLANIFICACIÓN. No escribas todavía código de la aplicación.

Paso 1 — Verifica el entorno y repórtame, en una tabla corta:
- Versión de PHP disponible en local y, si puedes comprobarlo, en el servidor.
- Cuál es la versión estable vigente de Laravel hoy (búscalo, no lo asumas).
- Qué starter kits oficiales ofrece esa versión de Laravel para Vue + Inertia, y si alguno
  incluye equipos (teams) y 2FA de fábrica. Si el concepto de "Jetstream con Teams" ya no
  existe o está descontinuado, dímelo y propón la alternativa vigente.
- Versión de Node y npm en local (para compilar assets).
- Si `composer` y `git` están disponibles.

Paso 2 — Con esos datos, proponme el plan de instalación concreto:
- Comando exacto de creación del proyecto.
- Lista de paquetes a instalar con su propósito en una línea cada uno. Como mínimo:
  permisos (spatie/laravel-permission), auditoría (spatie/laravel-activitylog), y lo que
  haga falta para 2FA si el starter kit no lo trae.
- Qué queda pendiente de construir a mano por no venir en el starter kit (especialmente
  equipos y roles).
- Configuración de .env para: MySQL, cola en driver database, caché y sesión sin Redis.

Paso 3 — Señálame cualquier punto donde la documentación de este paquete
(docs/01-arquitectura.md, docs/02-modelo-de-datos.md) haya quedado desactualizada respecto a
lo que encontraste. Prefiero corregir el documento ahora que arrastrar el desfase.

NO ejecutes la instalación todavía. Muéstrame el plan y espera mi confirmación.
```

## Hasta aquí

---

## Qué deberías obtener

Una tabla con versiones reales, un plan de instalación concreto y —lo más valioso— una lista de
puntos donde este paquete de documentación se quedó corto frente a lo que existe hoy.

Corrige esos puntos en los documentos **antes** de seguir. Cuesta cinco minutos ahora y evita que el
agente trabaje tres semanas contra un mapa equivocado.

## Después de confirmar

Cuando apruebes el plan, pide la ejecución con algo tan simple como:

```
Ejecuta el plan que propusiste. Al terminar:
- Confirma que `php artisan --version` responde y que la app carga en local.
- Haz el primer commit con mensaje en español.
- Ejecuta `graphify .` para crear el grafo inicial del proyecto.
- Dime qué debo configurar yo manualmente en hPanel antes del primer despliegue.
```
