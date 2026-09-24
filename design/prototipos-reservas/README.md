# Prototipos · Reservas (letrero + calendario)

Tres direcciones visuales estáticas para la sección de reservas, hechas para **decidir antes de tocar la app**. Viven en `design/` (fuera de `public/`), así que no se publican ni entran al build.

## Cómo verlos

Abrí `index.html` en el navegador (doble clic alcanza: son HTML estáticos, sin build ni servidor). Dentro de cada página hay una barra superior para saltar entre direcciones.

- `paleta-logo.html` → **Sistema de diseño desde el logo**: colores muestreados del PNG, mapa a los tokens de la app y el mismo componente en papel y en noche.
- `direccion-d.html` → **D · Noche neón**: el sitio completo (portada, cartelera, reservas, contacto, footer) con la paleta del logo.
- `direccion-a.html` → **A · Marquesina**: placa nocturna contenida en la sección de reservas. Es lo que está implementado en `src/App.vue`.
- `direccion-b.html` → **B · Noche total**: solo la sección de reservas como fachada oscura; el resto del sitio en papel.
- `direccion-c.html` → **C · Imprenta neón**: papel + tipografía display, letrero como placa impresa, neón solo como acento.
- `_base.css` → base compartida: tokens reales de `src/vs-refine.css` + topbar, calendario, horarios y formulario idénticos a los de la app.
- `_sitio-base.css` → shell de sitio completo (portada, cartelera, contacto, sección del letrero), escrito solo con tokens.
- `_implementado-a.patch` → respaldo del cambio que hoy está en `src/App.vue`.

## La paleta (muestreada, no estimada)

`python3` sobre el PNG (`logo stage.png`, 1160×610, 710.650 píxeles), agrupando por dominancia y por familia de tono:

| Rol | Valor | Peso real |
| --- | --- | --- |
| Muro (base) | `#0f0b15` · oklch(16% .022 302) | promedio de los oscuros |
| Negro azulado | `#00000a` · oklch(7% .045 264) | 5,5% de la imagen |
| Azul profundo | `#000a14` · oklch(14% .031 239) | 5,5% |
| Cian (acento primario) | `#00aaf0` · oklch(70% .152 237) | **45% del neón** |
| Cian claro | `#1edcf0` · oklch(82% .137 207) | 4,6% del neón |
| Magenta (secundario) | `#f03c8c` · oklch(65% .223 359) | 26% del neón (sumando sus dos familias) |
| Oro de las letras | `#f0c800` · oklch(84% .173 94) | 9% del neón |

El cambio se aplica **remapando los tokens que ya existen** (`--paper`, `--ink`, `--vermilion`, `--acid`, `--blue`, `--line`): ninguna clase ni componente cambia de nombre. Ver la tabla completa y el espécimen comparado en `paleta-logo.html`.


Las imágenes son las reales de `public/flyers/` (`logo stage.png` y `reservas.png`), referenciadas con ruta relativa desde acá.

## Qué comparten las tres

- El **h2** de la sección se mantiene (visible o `sr-only` según la dirección) y el banner conserva su `alt` completo: no se pierde accesibilidad ni SEO.
- Las fichas de datos que el banner no dice: **lunes a viernes, 14:00–20:00, 60 cupos diarios, TNE vigente**.
- El CTA salta al panel del calendario (`#dia`) y el calendario/slots/formulario son los de la app.
- Breakpoints calcados de la app: 1000 px y 760 px.

## Cómo se promueve una dirección a la app

1. Elegir la dirección y quedarse con su bloque de hero + su CSS.
2. En `src/App.vue`, el hero va dentro de `<section id="reservas" class="reservation-section">` como primer hijo (el `id="dia"` del calendario se mantiene para el ancla del CTA).
3. En `src/vs-refine.css`, sumar los tokens que falten (`--night`, `--neon-pink`, `--neon-cyan`) y el módulo del hero. La dirección A ya está integrada ahí.
4. Recordar la convención de este proyecto para imágenes: en `<img>` va **`:src="'flyers/archivo.png'"`** (binding). Un `src` relativo plano hace que Vite lo trate como import y el build falla.

## Notas

- Las direcciones B y C cambian el peso visual de la página completa, no solo de la sección: si se elige B hay que decidir si contacto/footer se oscurecen o quedan en papel.
- El contenido de ejemplo del calendario (septiembre 2026, cupos y bloque elegido) es ficticio y fijo: sirve para ver la pieza, no para probar datos.
