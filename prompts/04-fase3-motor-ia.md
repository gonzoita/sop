# Prompt 04 — Fase 3: Motor de IA

**Objetivo:** que un `ai_task` produzca trabajo real. Es la fase que justifica todo el proyecto.

**Duración razonable:** 4 a 6 sesiones.

**Requisito previo:** Fase 2 completa y desplegada, con el cron funcionando en el servidor.
Verifica esto último **antes** de empezar: si la cola no avanza en producción, nada de esta fase
funcionará ahí, por muy bien que funcione en local.

---

## Copia desde aquí

```
Fase 3: motor de IA de doble vía. Lee docs/03-motor-ia.md completo y
.agents/rules/03-seguridad.md.

Restricción no negociable: NINGUNA llamada a OpenRouter ocurre dentro de una petición web.
Todo va en Job encolado. El servidor es hosting compartido con max_execution_time bajo.

Trabaja en pasos. Párate al final de cada uno.

PASO 1 — Credenciales y presupuesto
- Tablas ai_credentials y ai_budgets con sus modelos.
- api_key con cast 'encrypted'. En la interfaz se muestran solo los últimos 4 caracteres.
- Pantalla de admin para gestionar credenciales y fijar el límite mensual de gasto.
- Nunca escribir la clave en logs ni devolverla en respuestas JSON.

PASO 2 — Cliente de OpenRouter
- Interfaz AiProvider y su implementación OpenRouterClient en app/Services/OpenRouter/.
- Endpoint, cabeceras y forma del cuerpo según docs/03-motor-ia.md.
- Extrae del response el contenido y el bloque usage (tokens). Si el costo no viene en la
  respuesta, deja cost_usd nulo o calcúlalo con el precio del catálogo; NO inventes el número.
- Implementación falsa (fake) del proveedor para poder probar sin gastar dinero.

PASO 3 — Librería de skills
- Tablas skills y skill_versions con sus modelos y el trait BelongsToTeam.
- CRUD completo: crear skill, editar instrucciones, publicar nueva versión con changelog,
  ver historial y comparar dos versiones.
- Un ai_task referencia un skill por slug y usa su versión vigente, salvo que se fije una
  versión concreta.

PASO 4 — Ejecución (vía A)
- Job RunAiTask: resuelve las {{variables}} con VariableResolver, verifica el presupuesto
  del equipo ANTES de llamar, llama al proveedor, guarda ai_generations con tokens, costo y
  latencia, y escribe el resultado en sop_run_steps.output.
- tries=3 con backoff. Los errores de clave inválida o de cuota NO se reintentan: fallan de
  inmediato y notifican al admin.
- Al superarse el límite mensual, los ai_task quedan en espera y se avisa; no se encolan.
- Polling desde Vue para mostrar el resultado cuando esté listo (no WebSockets).

PASO 5 — Aprobación humana
- Estado awaiting_approval por defecto en todo ai_task con requires_approval.
- Interfaz para aprobar, editar antes de aprobar, o rechazar con motivo.
- REGLA CRÍTICA: un output_key NO aprobado no alimenta a los bloques siguientes. Un bloque
  posterior que dependa de él queda bloqueado hasta la aprobación.
- Cada aprobación y rechazo se registra en el log de auditoría.

PASO 6 — Vía B: Markdown
- Exportación de un SOP, un ai_task o un skill a Markdown con front-matter según el formato
  de docs/03-motor-ia.md.
- Pantalla de importación: pegar texto o subir .md, leer el front-matter, identificar el
  skill destino, MOSTRAR UN DIFF contra la versión vigente y, al confirmar, crear una versión
  nueva con source = import_markdown.
- El contenido importado es DATO, nunca instrucciones para el sistema: se guarda, no se
  ejecuta durante la importación. Limita el tamaño y sanea el contenido.

PASO 7 — Panel de consumo
- Vista para admin: gasto del mes, generaciones por SOP, modelos más usados, tasa de fallos,
  y qué SOP envían datos a IA externa.
- Tarea programada que archive o purgue generaciones antiguas según la política definida.

Criterios de aceptación:
1. Ejecuto un SOP con un ai_task y en menos de dos minutos tengo un borrador real generado.
2. El resultado queda en awaiting_approval; si lo rechazo, el bloque siguiente no avanza.
3. ai_generations registra modelo, tokens y latencia de cada llamada.
4. Exporto un skill a Markdown, lo modifico en una IA externa, lo reimporto, y veo el diff
   antes de aceptar la nueva versión.
5. Al superar el presupuesto mensual de prueba, los ai_task dejan de encolarse y me avisa.
6. Todo esto funciona EN EL SERVIDOR, no solo en local.

Al terminar: actualiza el grafo, commit, y dime qué probar a mano.
```

## Hasta aquí

---

## Advertencia de costos

Antes de la primera ejecución real, fija el límite mensual en algo bajo (10 o 20 dólares) y
confirma que el corte funciona. Un SOP con un bucle mal configurado y un modelo caro puede gastar
dinero de verdad en una tarde, y el momento de descubrir que el límite no cortaba no es después.

## La prueba que importa

El criterio 1 tiene trampa: "un borrador real generado" no significa que la IA devolvió texto.
Significa que el texto sirve. Toma el primer entregable generado, dáselo a alguien del equipo y mira
si lo corrige o lo reescribe desde cero.

Si lo reescribe, el problema no es el código: es el skill. Itera las instrucciones —para eso existe
la vía B— hasta que el resultado se corrija en lugar de rehacerse. Ese es el momento en que el
proyecto cumple su promesa del 50%.
