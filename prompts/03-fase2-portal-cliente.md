# Prompt 03 — Fase 2: Ejecución, portal de cliente y onboarding

**Objetivo:** que un SOP deje de ser un documento y pase a ejecutarse, con clientes participando
desde su propio portal. Todavía sin IA.

**Duración razonable:** 3 a 4 sesiones.

**Requisito previo:** Fase 1 completa, con al menos tres SOP reales cargados.

---

## Copia desde aquí

```
Fase 2: ejecución de SOP, portal de cliente y onboarding automatizado.
Lee docs/02-modelo-de-datos.md (tablas de ejecución) y .agents/rules/03-seguridad.md.

Recuerda: los bloques ai_task todavía NO ejecutan nada. En esta fase se muestran como
"pendiente de IA" y se pueden completar manualmente. El motor llega en la Fase 3.

Trabaja en pasos. Párate al final de cada uno.

PASO 1 — Motor de ejecución
- Migraciones y modelos: sop_runs y sop_run_steps.
- Action StartSopRun: recibe un sop_version_id y un cliente opcional, crea la ejecución y un
  paso por cada bloque ejecutable, congelando la versión usada.
- Action AdvanceRun: recalcula qué pasos quedan disponibles según los bloques decision y el
  estado de los anteriores.
- Los valores de inputs se guardan en sop_runs.inputs a medida que se llenan.

PASO 2 — Pantalla de ejecución (equipo)
- Runs/Show.vue: los bloques en modo lectura/ejecución, con los campos input rellenables,
  checklists marcables y estado por paso.
- Asignación de pasos a personas del equipo y fechas límite.
- Runs/Index.vue con filtros por estado, cliente y responsable.

PASO 3 — Portal de cliente
- Grupo de rutas y middleware aparte para el rol cliente. Layout propio, más simple.
- El cliente ve SOLO: las ejecuciones asignadas a su cliente, y dentro de ellas SOLO los
  bloques marcados con filled_by = "client".
- No ve: skills, prompts, costos de IA, otros clientes, ni el resto de bloques del SOP.
- Invitación por correo con enlace de un solo uso y caducidad.
- Subida de archivos con lista blanca de tipos, límite de tamaño, nombre generado por el
  sistema y almacenamiento fuera de public_html servido por ruta con verificación de permisos.

PASO 4 — Automatización por eventos
- Tabla automation_triggers y su modelo.
- Eventos de dominio: client.created, run.completed, run.step.approved.
- Listener que, al dispararse un trigger activo, instancia el SOP configurado, lo asigna y
  envía las notificaciones correspondientes (todo en cola, nunca en la petición web).
- Pantalla de configuración de triggers para admin.

PASO 5 — Notificaciones
- Notificaciones por correo, en cola: paso asignado, ejecución completada, cliente invitado,
  recordatorio de paso vencido.
- Plantillas de correo en español, sobrias, con enlace directo al paso correspondiente.

Criterios de aceptación:
1. Lanzo una ejecución de un SOP real, lleno los datos y avanzo hasta completarla.
2. Un bloque decision manda la ejecución por la rama correcta según lo que responda.
3. Invito a un cliente de prueba; entra al portal y ve únicamente sus bloques. Verifícalo
   iniciando sesión como ese usuario, no solo revisando el código.
4. Crear un cliente dispara automáticamente su SOP de onboarding y llega el correo.
5. El correo se envía desde la cola, no bloquea la petición.

Al terminar: actualiza el grafo, commit, y dime qué probar a mano.
```

## Hasta aquí

---

## Verificación que no puedes delegar

El punto 3 de los criterios pruébalo tú mismo, entrando como usuario cliente en una ventana de
incógnito. El aislamiento del portal es el control donde un error tiene consecuencias reales frente
a tus clientes, y es exactamente el tipo de cosa que "se ve bien en el código" y falla en la
práctica por una ruta que se olvidó de verificar la asignación.
