# Prompt 99 — Plantilla de tarea

Para cualquier cosa fuera del roadmap: una función nueva, un arreglo, un cambio.

Un prompt bien armado cuesta dos minutos y ahorra tres iteraciones. Uno vago ("arregla el
constructor") hace que el agente cargue media aplicación al contexto y devuelva algo que no era.

---

## Plantilla

```
[CONTEXTO]
Qué parte del sistema toca esto y por qué lo pido ahora.
Si ya intentamos algo que no funcionó, dilo: evita que lo repita.

[OBJETIVO]
Una frase. Qué debe ser verdad cuando termines.

[ALCANCE]
Archivos o módulos donde esperas que viva el cambio. Si no lo sabes, dilo así:
"Consulta el grafo de Graphify para ubicarlo antes de abrir archivos."

[RESTRICCIONES]
Lo que NO debe pasar. Por ejemplo:
- Sin migraciones destructivas.
- Sin dependencias nuevas.
- Sin romper el aislamiento por team_id.
- Sin tocar el contrato de bloques de docs/02-modelo-de-datos.md.

[CRITERIOS DE ACEPTACIÓN]
Lista verificable. Cada punto debe poder comprobarse abriendo el navegador o corriendo un test.

[ENTREGA]
Dime qué archivos tocaste, qué debo probar a mano, y qué quedó pendiente.
No me reimprimas los archivos completos.
```

---

## Ejemplo aplicado

```
[CONTEXTO]
En el constructor, cuando un SOP pasa de 30 bloques cuesta encontrar uno concreto.
Es lo primero que me frenó al cargar el SOP real de lanzamiento de campaña.

[OBJETIVO]
Poder navegar un SOP largo sin hacer scroll a ciegas.

[ALCANCE]
Consulta el grafo para ubicar el constructor antes de abrir archivos.
Espero cambios en Sops/Edit.vue y en un componente nuevo de índice lateral.

[RESTRICCIONES]
- Sin dependencias nuevas.
- No cambies el contrato de los componentes de bloque.
- El autoguardado debe seguir funcionando igual.

[CRITERIOS DE ACEPTACIÓN]
1. Panel lateral con la lista de bloques por su título o primeras palabras.
2. Al hacer clic, salta al bloque y lo resalta un instante.
3. El panel se colapsa y recuerda ese estado.
4. Funciona con 60 bloques sin notarse lento.

[ENTREGA]
Archivos tocados, qué probar, qué quedó pendiente.
```

---

## Errores que salen caros

**Pedir tres cosas en un prompt.** Se hace la primera bien y las otras dos a medias. Una cosa por
prompt.

**No dar criterios de aceptación.** Sin ellos, "terminado" significa lo que el agente decida que
significa.

**Dejar el alcance abierto.** "Mejora el rendimiento" hace que se lea el proyecto entero. "El
listado de ejecuciones tarda; mira la consulta" apunta al sitio.

**Aceptar sin probar.** El agente dice que funciona porque el código parece correcto. Abre el
navegador.

**Repetir contexto que ya está en `AGENTS.md`.** Si te descubres explicando el proyecto otra vez, no
lo expliques: añádelo a `AGENTS.md` y queda resuelto para siempre.
