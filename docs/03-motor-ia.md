# 03 — Motor de IA

## Las dos vías, y cuándo usar cada una

| | Vía A — interna | Vía B — Markdown externo |
|---|---|---|
| Quién ejecuta | El servidor, vía OpenRouter | Tú, en ChatGPT / Gemini / Claude |
| Cuándo conviene | Prompt ya afinado, proceso repetitivo | Iterando el prompt, o modelo que usas mejor desde su interfaz |
| Velocidad | Segundos a un minuto | Manual |
| Costo | Se paga por token en OpenRouter | Tu suscripción existente |
| Trazabilidad | Completa (`ai_generations`) | Se registra la importación |

Las dos escriben en la misma librería de skills. No son dos productos: son dos formas de llenar el
mismo repositorio de conocimiento.

---

## Vía A — ejecución interna con OpenRouter

### El cliente HTTP

OpenRouter expone una API compatible con el formato de OpenAI:

- Endpoint: `https://openrouter.ai/api/v1/chat/completions`
- Cabeceras: `Authorization: Bearer <clave>` y `Content-Type: application/json`
- Cabeceras opcionales de atribución (`HTTP-Referer`, título del sitio): solo afectan a los
  rankings públicos de OpenRouter, no al funcionamiento. Confirma su nombre exacto en la
  documentación vigente si decides usarlas.
- Catálogo de modelos y sus identificadores: `GET /api/v1/models`, o el catálogo web.

Implementación en `app/Services/OpenRouter/OpenRouterClient.php`, detrás de una interfaz
`AiProvider` para poder simularla en pruebas y sustituirla más adelante.

```php
$response = Http::withToken($apiKey)
    ->timeout(120)
    ->retry(2, 2000)
    ->post('https://openrouter.ai/api/v1/chat/completions', [
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => $skillInstructions],
            ['role' => 'user',   'content' => $resolvedPrompt],
        ],
        'temperature' => 0.4,
    ]);
```

Del cuerpo de la respuesta se extrae el contenido y el bloque `usage` (tokens de entrada y salida)
para registrar consumo. Si la respuesta no trae costo directamente, se calcula con el precio del
modelo consultado en el catálogo, o se guarda solo el conteo de tokens. **No inventes un campo de
costo que no venga**: es preferible una columna nula que un número inventado en un reporte.

### La elección del modelo

Cada `ai_task` puede fijar un modelo, y la cuenta define uno por defecto. Empieza con un modelo
intermedio: los SOP de marketing producen texto estructurado, no razonamiento matemático, y el
salto de precio hacia los modelos más caros rara vez se justifica. Deja el modelo como parámetro
editable en la interfaz para poder comparar sin tocar código.

### El flujo completo

```
Usuario llena inputs y lanza la ejecución
        ↓
StartSopRun crea sop_run + sop_run_steps (uno por bloque ejecutable)
        ↓
Por cada ai_task alcanzable: se encola RunAiTask (estado: queued)
        ↓
El cron dispara el worker → RunAiTask resuelve {{variables}}, llama a OpenRouter
        ↓
Guarda en ai_generations (prompt, respuesta, tokens, costo, latencia)
        ↓
Escribe el resultado en sop_run_steps.output y en sop_runs.outputs[output_key]
        ↓
Estado → awaiting_approval (si requires_approval) o approved
        ↓
El frontend, que va consultando por polling, muestra el resultado
        ↓
Una persona aprueba, edita o rechaza. Solo entonces el valor queda disponible
para los bloques siguientes.
```

Ese último punto es importante: **un `output_key` no aprobado no alimenta al siguiente bloque.** Si
lo hiciera, un error temprano se propagaría amplificado por toda la cadena.

### Manejo de fallos

- `tries = 3` con backoff exponencial en el Job.
- Errores de cuota o de clave inválida **no se reintentan**: fallan de inmediato y avisan al admin.
- Todo fallo queda en `ai_generations` con `status = failed` y el mensaje de error, y el paso
  correspondiente pasa a `failed` con opción de reintentar desde la interfaz.
- Nunca se registra la clave de API en el log.

### Control de gasto

Desde el primer día: límite mensual de gasto por equipo, configurable, verificado **antes** de
encolar. Al superarse, los `ai_task` quedan en espera y se avisa al admin. Un bucle accidental en un
SOP con un modelo caro puede costar dinero real en una tarde.

---

## Vía B — Markdown para IA externa

### Exportación

Cualquier SOP, bloque `ai_task` o skill se exporta como Markdown con front-matter:

```markdown
---
tipo: skill
nombre: Brief de marca
slug: brief-de-marca
version: 3
variables:
  - nombre_marca
  - objetivo_campana
generado: 2026-09-18
---

## Instrucciones

Eres un estratega de marca de una agencia de marketing digital...

## Variables de entrada

- `nombre_marca`: nombre comercial de la marca
- `objetivo_campana`: objetivo declarado por el cliente

## Formato de salida esperado

...
```

El front-matter es lo que permite reimportar sin ambigüedad: el sistema sabe a qué skill pertenece
y qué versión estaba vigente.

### Importación

La pantalla de importación acepta pegar el texto o subir el `.md`. El sistema:

1. Lee el front-matter e identifica el skill destino (o propone crear uno nuevo).
2. Muestra un **diff** contra la versión vigente, para que veas qué cambia antes de aceptar.
3. Al confirmar, crea un `skill_versions` nuevo con `source = import_markdown`.

Nunca sobrescribe la versión anterior. El historial completo se conserva.

### Validación al importar

El Markdown viene de fuera, así que se trata como entrada no confiable: se limita el tamaño, se
sanea el contenido, y **las instrucciones importadas no se ejecutan durante la importación**, solo
se guardan. Cualquier intento de que el texto importado dé órdenes al sistema se ignora: es
contenido, no configuración.

---

## La librería de skills

Es el activo que acumula valor. Un skill es un conjunto versionado de instrucciones que sabe
producir un entregable concreto.

```
skill: "brief-de-marca"
 ├── v1  manual          instrucciones iniciales
 ├── v2  openrouter      afinado tras 10 ejecuciones
 └── v3  import_markdown  reescrito en Claude y reimportado   ← vigente
```

Un `ai_task` referencia un skill por `slug` y usa siempre su versión vigente, salvo que se fije una
versión concreta. Así, mejorar un skill mejora automáticamente todos los SOP que lo usan —que es
justamente lo que quieres, y también la razón por la que el historial y el diff importan.

---

## Exportación a GPTs, Gems y Proyectos

Cada versión de skill se puede convertir al formato que espera cada plataforma:

- **GPT personalizado**: texto de instrucciones + archivos de conocimiento.
- **Gem de Gemini**: bloque de instrucciones de sistema.
- **Proyecto de Claude**: Markdown de conocimiento del proyecto.

Estas plataformas **no exponen hoy una API pública** para crear o actualizar GPTs, Gems o Proyectos
de forma programática. El flujo real es generar el texto correcto y copiarlo o descargarlo. La
plataforma es donde versionas y mantienes las skills; las otras son destinos.

No prometas en la interfaz una sincronización automática que no existe.
