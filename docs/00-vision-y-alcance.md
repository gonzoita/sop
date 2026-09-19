# 00 — Visión y alcance

## El problema

Los procesos de una agencia de marketing viven en la cabeza de dos o tres personas, en documentos
sueltos y en hilos de chat. Cada cliente nuevo se onboardea distinto, cada campaña se arma desde
cero, y el conocimiento se pierde cuando alguien se va. Documentarlo en Notion o en Google Docs
ayuda a recordar el proceso, pero no hace nada del trabajo: sigue siendo una persona leyendo pasos
y ejecutándolos a mano.

## La apuesta

Un SOP no debería ser solo un documento que se lee. Debería ser un **programa que se corre**.

Esta plataforma convierte cada procedimiento en una secuencia de bloques donde algunos piden datos
al usuario y otros producen entregables con IA. Llenas el formulario de entrada de "Lanzamiento de
campaña para cliente nuevo", y el sistema devuelve el brief redactado, la estructura de campaña
propuesta, los ángulos de copy y el checklist de configuración, listos para que una persona los
revise y ajuste.

La meta explícita: **que entrar los datos adelante más del 50% del trabajo real.** No el 100%. El
criterio de éxito no es que la IA reemplace al equipo, sino que el equipo deje de hacer a mano la
parte mecánica y dedique su tiempo a revisar, decidir y afinar.

## Quién lo usa

**Equipo interno de la agencia**, con tres roles:
- *Admin*: configura la cuenta, gestiona usuarios, credenciales de IA y permisos.
- *Editor*: crea y versiona SOPs y skills.
- *Ejecutor*: corre SOPs, llena datos, aprueba o rechaza salidas de IA.

**Clientes externos**, con acceso restringido a un portal donde ven únicamente los SOP que se les
asignaron (típicamente su propio onboarding), llenan formularios, suben archivos y siguen el
progreso. Un cliente nunca ve la librería de skills, los prompts internos, los costos de IA ni nada
de otro cliente.

## Las dos vías de IA

Ambas existen desde la primera versión, porque cubren situaciones distintas:

**Vía A — ejecución interna.** El servidor llama a OpenRouter y ejecuta el bloque de IA dentro del
SOP. Es el camino automático: sirve para procesos repetitivos donde el prompt ya está afinado.

**Vía B — exportación Markdown.** El SOP o el skill se exporta como Markdown con metadatos, se
procesa manualmente en ChatGPT, Gemini o Claude, y el resultado se reimporta como nueva versión del
skill. Sirve cuando quieres control manual, cuando el modelo que necesitas se usa mejor desde su
propia interfaz, o cuando estás iterando un prompt y todavía no vale la pena automatizarlo.

Las dos alimentan la misma **librería de skills**, que es el activo real que acumula la agencia: el
conjunto versionado de instrucciones que saben producir cada entregable. Desde ahí también se
exportan a GPTs personalizados, Gems de Gemini y Proyectos de Claude.

## Qué NO es

- **No es un gestor de proyectos.** No compite con Asana o ClickUp. Genera tareas dentro de una
  ejecución de SOP, pero no pretende ser el tablero donde vive todo el trabajo de la agencia.
- **No es un CRM.** Guarda clientes como contexto de los SOP, no como pipeline de ventas.
- **No publica automáticamente en GPTs, Gems o Proyectos.** Esas plataformas no exponen una API
  pública para crearlos; la plataforma genera el archivo o el texto en el formato correcto y tú lo
  pegas. Esto es una limitación real, no una fase pendiente.
- **No es, todavía, un SaaS multi-agencia.** La arquitectura deja la puerta abierta (todo lleva
  `team_id`), pero la primera versión es para una sola agencia con sus clientes.

## Criterio de éxito de la v1

Tres SOP reales de la agencia documentados en la plataforma y ejecutándose de punta a punta, con al
menos un bloque de IA produciendo un entregable que el equipo efectivamente usa —corrigiéndolo, no
reescribiéndolo desde cero—, y un cliente real completando su onboarding a través del portal.

Si eso funciona, todo lo demás es ampliación. Si eso no funciona, ninguna función adicional lo va a
arreglar.
