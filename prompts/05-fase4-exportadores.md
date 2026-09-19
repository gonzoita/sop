# Prompt 05 — Fase 4: Exportadores y cierre de la v1

**Objetivo:** llevar las skills a GPTs, Gems y Proyectos de Claude, y dejar la plataforma en
condiciones de recibir datos reales.

**Duración razonable:** 2 a 3 sesiones.

**Requisito previo:** Fase 3 funcionando en el servidor, con al menos un skill maduro.

---

## Copia desde aquí

```
Fase 4: exportadores y endurecimiento. Lee docs/03-motor-ia.md (sección de exportación) y
docs/04-seguridad.md.

Contexto importante: GPTs, Gems y Proyectos de Claude NO exponen una API pública para
crearlos o actualizarlos programáticamente. El flujo es generar el formato correcto para que
yo lo copie o descargue. No construyas ni prometas sincronización automática.

PASO 1 — Exportadores
- Tabla skill_exports y su modelo.
- Un generador por destino, cada uno con su formato:
  · markdown: el formato con front-matter que ya existe.
  · gpt: texto de instrucciones + archivo de conocimiento adjunto.
  · gem: bloque de instrucciones de sistema, texto plano.
  · claude_project: Markdown de conocimiento del proyecto.
- Pantalla con vista previa, botón de copiar al portapapeles y descarga del archivo.
- Cada exportación queda registrada con su versión de skill de origen.

PASO 2 — Derechos sobre los datos
- Tabla data_requests y su modelo.
- Exportación completa de todos los datos asociados a un cliente en un archivo descargable
  (ejecuciones, respuestas, archivos subidos, generaciones vinculadas).
- Eliminación o anonimización de un cliente, con constancia en el log de auditoría de quién
  lo pidió y cuándo.
- Ambas operaciones en cola, nunca en la petición web.

PASO 3 — Respaldos programados
- Tarea programada de respaldo diario de la base de datos con retención configurable.
- Comando artisan para restaurar en una base vacía, y documentación de cómo usarlo.
- Tarea de limpieza de generaciones antiguas según la política definida.

PASO 4 — Repaso de seguridad
Revisa y repórtame el estado de cada punto, con el archivo concreto donde lo verificaste:
- ¿Toda tabla de negocio tiene team_id y su modelo aplica el global scope?
- ¿Existe algún endpoint que no pase por Policy?
- ¿Alguna ruta accesible por un usuario con rol cliente que no debería serlo?
- ¿Alguna clave de API que pueda terminar en un log o en una respuesta JSON?
- ¿APP_DEBUG=false y APP_ENV=production en el servidor?
- ¿Las subidas de archivo validan tipo, tamaño y guardan fuera de public_html?
- ¿2FA obligatorio para admin?
Dime lo que encuentres mal sin arreglarlo todavía; decidimos juntos el orden.

PASO 5 — Dashboard
- Completa el dashboard: SOPs más ejecutados, ejecuciones en curso y bloqueadas, consumo de
  IA del mes contra el presupuesto, y pasos vencidos.

Criterios de aceptación:
1. Exporto un skill a los cuatro formatos y el contenido es usable en cada destino.
2. Exporto todos los datos de un cliente de prueba y el archivo está completo.
3. Elimino ese cliente de prueba y queda constancia en el log.
4. El respaldo diario corre solo, y probé una restauración en una base vacía.
5. El repaso de seguridad no deja ningún punto rojo sin plan.

Al terminar: actualiza el grafo, commit, y dame el resumen del estado de la v1.
```

## Hasta aquí

---

## Antes de meter datos reales de clientes

Tres cosas, en este orden:

**Restaura un respaldo.** De verdad, a una base vacía. Un respaldo no probado no es un respaldo.

**Entra como cliente.** Ventana de incógnito, usuario cliente real, recorre todo lo que puede
alcanzar. Busca activamente algo que no debería ver.

**Decide sobre el hosting.** `docs/04-seguridad.md` lo plantea con claridad: el hosting compartido
sirve para construir y validar, pero no es la base adecuada para un producto que apunta a
cumplimiento formal con datos de terceros. No hace falta resolverlo hoy, pero sí ponerle fecha
antes de que varios clientes reales dependan de esto.

---

## Después de la v1

Lo que naturalmente sigue, por orden de valor:

Plantillas compartibles entre agencias, si alguna vez lo conviertes en SaaS. Integraciones por
webhook con las herramientas que ya usas. Métricas de tiempo ahorrado por SOP —la única forma de
demostrar si la promesa del 50% se cumplió—. Y ejecución encadenada de varios `ai_task` sin
intervención entre ellos, que solo tiene sentido cuando confíes lo suficiente en los skills como
para bajar `requires_approval` en algunos pasos.

Ese último punto llega solo cuando los datos te digan que puede llegar. No antes.
