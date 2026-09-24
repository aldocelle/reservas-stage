# Viña Stage · flyers de eventos

Documentación de los assets de la cartelera. Este archivo vive en `design/` (no en `public/`) para que no se publique en el build.

- `public/flyers/*.jpg` → flyers publicados (JPG ~900 px de ancho, vertical).
- `design/flyers-origen/*.png` → capturas originales (pesadas, ignoradas por git; fuente para regenerar).

## Mapeo actual (evento en `src/App.vue` → archivo)

| Evento (`title`) | Tono | Archivo | Flyer de origen |
| --- | --- | --- | --- |
| LIVE SESSION | lime | `live-session.jpg` | DJ Urania · Sunset Set (terraza, viernes) |
| STAGE COMEDY | orange | `stage-comedy.jpg` | Stage Up Comedy (jueves 24 sept, 21:30, entrada libre) |
| NIGHT STAGE | violet | `night-stage.jpg` | Hoy te esperamos · 3 pisos, 3 ambientes |
| STAGE UP | red | `stage-up.jpg` | DJ Bio Red · Hoy sábado, 3 ambientes |

Para cambiar una imagen: reemplaza el archivo manteniendo el nombre, o edita el campo `image` del evento en `src/App.vue`. El panel de cartelera usa `flyers/<archivo>.jpg` (ruta relativa, se sirve desde `public/`).

## Cómo regenerar los JPG

Los PNG originales (capturas) están en `design/flyers-origen/` (fuera de `public/`, no se publican ni se commitean). Para regenerar un flyer a 900 px de ancho:

```bash
python3 - <<'PY'
from PIL import Image
im = Image.open('design/flyers-origen/<origen>.png').convert('RGB')
im = im.resize((900, round(im.height * 900 / im.width)), Image.LANCZOS)
im.save('public/flyers/<destino>.jpg', 'JPEG', quality=82, optimize=True, progressive=True)
PY
```

Las tarjetas miden 300×425 px (260×385 en móvil) con `object-fit: cover`. Los flyers actuales son 900×1091–1159 (aspecto ~0.78–0.83) y con `cover` se recorta ~9–15% de ancho, siempre centrado: mantén el contenido importante centrado y con margen respecto a los bordes laterales (verificado: los 4 flyers actuales no pierden logo, caras, fecha ni dirección).

## Notas

- `public/` se publica tal cual (Vercel / GitHub Pages), por eso los originales pesados no viven aquí.
- En las tarjetas de la cartelera, el flyer es el protagonista: con imagen se oculta el caption HTML (`small`/`h3`/`p`) y quedan sólo la marca y el footer de la card; sin imagen se muestra el diseño original (texto al pie). Reglas en `src/styles.css` (`.event-card:has(.flyer-img) ...`). Nota: `.flyer-mark`/`.flyer-content`/`.flyer-footer`/`.flyer-noise` deben seguir con `position:absolute` propia + `z-index:1`; si se les pone `position:relative` el texto deja de anclarse abajo y se recorta arriba.
- Los flyers con fecha impresa ("jueves 24 de septiembre") quedan desactualizados: para la cartelera permanente conviene la versión sin fecha o mostrar la fecha en el HTML (campo `date` del evento).
- El logo circular de la marca está en `public/favicon.png` (512×512, generado desde `design/flyers-origen/`) y se enlaza en `index.html` con `<link rel="icon" type="image/png" href="/favicon.png">`.
