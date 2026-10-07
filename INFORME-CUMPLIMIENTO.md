# Informe de cumplimiento · Sistema digital de operación Viña Stage

**Fecha:** 6 de octubre de 2026
**Documento de referencia:** "Sistema digital de operación Viña Stage" v1.0 (ExpressWebsite · Viña Stage)
**Objeto de este informe:** comparar el alcance definido en ese documento con lo que existe hoy implementado en el repositorio `reservas-stage`.

## Resumen ejecutivo

**Estado general: CUMPLIMIENTO PARCIAL — un módulo de tres.**

El documento de definición describe una plataforma de tres módulos (cartelera, promociones, reservas) con roles diferenciados, integraciones Google y un servicio de gestión semanal. Lo que existe hoy es **un sistema de reservas de cupos por horario**, técnicamente sólido (transacciones, validaciones duplicadas cliente/servidor, auditoría), pero acotado a esa única pieza. Cartelera y promociones no son módulos administrables: son contenido fijo en el código fuente. No hay reserva por mesa ni por cantidad de personas. El rol "Staff" existe en la base de datos pero no está aplicado en ningún endpoint: toda cuenta admin tiene acceso total.

En términos de arquitectura, el sistema ya está construido como **Nivel 3 / Plataforma propia** (backend PHP + MySQL + panel administrativo propio, sin depender de Google Sheets/Calendar/Drive) — es decir, se saltó directamente la Alternativa 1 y 2 del §8 del documento. El esfuerzo pendiente no es de arquitectura sino de **alcance funcional**: construir los módulos de cartelera y promociones sobre la misma base, y aplicar roles reales.

## Comparación por sección

| Sección del documento | Requisito | Estado actual | Evidencia |
|---|---|---|---|
| §3.1 Inicio | Identidad, destacado, acceso a cartelera/promos/reservas/contacto/ubicación | Cumplido como landing estática: hero, cartelera, mapa, contacto, reservas | `src/App.vue` |
| §3.2 Cartelera semanal | Evento con fecha, hora, descripción, imagen, estado, capacidad, modalidad — administrable | **No cumplido.** Es un array fijo en el código (`App.vue:43`); cambiar un evento requiere editar código y recompilar, no hay CRUD ni panel | `src/App.vue:43` |
| §3.3 Promociones | Precio, vigencia, cupos, condiciones, imagen, estado activo/inactivo | **No cumplido.** No existe tabla ni endpoint de promociones en la base de datos | `database/schema.sql` |
| §4.1 Reserva individual | Un cupo = una persona | **Cumplido.** Es el único modo implementado | `database/schema.sql:35-57` |
| §4.2 Reserva por cantidad de personas | Reserva para N personas con máximo configurable | **No cumplido.** Cada reserva ocupa un solo cupo de persona | `database/schema.sql:55` (unicidad por RUT+fecha) |
| §4.3 Reserva de mesa | Mesas con capacidad configurable asociadas a eventos | **No cumplido.** No existe entidad "mesa" | — |
| §5 Estados de reserva | Pendiente, Confirmada, Cancelada, Modificada, Utilizada, No asistió | **Parcial.** Implementados: `confirmed`, `cancelled`, `attended`, `no_show`. Faltan "Pendiente" y "Modificada" como estados explícitos | `database/schema.sql:46` |
| §6 Roles Admin/Staff | Matriz de permisos diferenciada | **No aplicado.** La columna `role` existe (`admin`/`staff`) pero ningún endpoint la verifica; cualquier sesión admin tiene acceso completo | `api/auth.php`, `api/config.php:52` |
| §7 Integraciones Google | Sheets, Calendar, Drive, Maps, WhatsApp | Solo **Maps** (iframe estático) y **WhatsApp** (enlace `wa.me` clickeable). Sheets/Calendar/Drive no existen — no se necesitan porque hay base de datos propia | `src/App.vue:228` |
| §8 Arquitectura | 3 alternativas a evaluar en Fase 0 | **Resuelto de facto como Alternativa 3** (plataforma propia con backend y BD propios), sin haber pasado por una Fase 0 formal de auditoría | `README.md`, `database/schema.sql` |
| §9-10 Fase 0 / Auditoría | Documento "Arquitectura definitiva" previo al desarrollo | No se ejecutó como fase formal; la arquitectura se decidió directamente durante la construcción | — |
| §11 Gestión semanal ExpressWebsite | Servicio recurrente de actualización de contenido | No aplica al código: es un acuerdo de servicio, no una funcionalidad del sistema. Hoy, actualizar cartelera/promos requiere intervención de desarrollo (editar y desplegar), no solo gestión de contenido | — |
| §15 Requerimientos funcionales (RF-001 a RF-016) | Ver detalle abajo | Ver tabla de requerimientos funcionales | — |

## Requerimientos funcionales (§15)

| ID | Requerimiento | Estado |
|---|---|---|
| RF-001 | Mostrar cartelera semanal | Cumplido (contenido fijo, no dinámico) |
| RF-002 | Publicar eventos con fecha/hora/descripción/imagen | No cumplido (no hay publicación, solo edición de código) |
| RF-003 | Capacidad por evento | No cumplido para eventos; sí existe capacidad por bloque horario |
| RF-004 | Modalidad de reserva: individual / por personas / por mesa | Solo individual |
| RF-005 | Bloquear reservas al alcanzar capacidad | **Cumplido** (transacción + `FOR UPDATE`, sin sobrecupo) |
| RF-006 | Registrar una reserva | Cumplido |
| RF-007 | Identificador único de reserva | Cumplido (`VS-XXXXXXXX`, con reintento ante colisión) |
| RF-008 | Consultar una reserva | Cumplido vía panel admin (no hay consulta pública por código) |
| RF-009 | Modificación y cancelación | Parcial: cancelación sí (panel admin cambia estado); modificación de datos de la reserva, no |
| RF-010 | Crear y administrar promociones | No cumplido |
| RF-011 | Configurar precio/descripción/vigencia/imagen/condiciones/estado de promoción | No cumplido |
| RF-012 | Diferenciar permisos Admin/Staff | No cumplido (ver §6) |
| RF-013 | Admin gestiona usuarios Staff | No cumplido (no hay UI de gestión de usuarios en el panel) |
| RF-014 | Interfaz operativa para Staff | No aplica, no existe distinción de rol |
| RF-015 | Responsive / móvil | **Cumplido** (verificado a 360, 390 y 1440px según `README.md`) |
| RF-016 | Evolucionable a backend propio sin rehacer el sitio público | **Cumplido** — ya es backend propio |

## Lo que sí está sólido

- Reservas de cupos por horario: validación de fecha real, no pasada, máximo 60 días, correspondencia de `weekday`, sin duplicados, sin sobrecupo, WhatsApp 8–15 dígitos, reintento de código ante colisión.
- Panel admin funcional: resumen, gestión de días/horarios/cupos sin deploy, filtros y cambio de estado de reservas, configuración del sitio, todo con `audit_logs`.
- Autenticación por sesión PHP con cookies `httpOnly`/`SameSite`.
- Despliegue reproducible (Docker, Railway, Vercel) y documentado.

## Brechas principales frente al documento, en orden de impacto

1. **Cartelera administrable** — hoy es código fuente fijo. Es el bloqueador más directo para que Viña Stage pueda operar sin pedirle cambios a ExpressWebsite cada semana (contradice el espíritu del §11).
2. **Promociones administrables** — no existe el módulo; tampoco la validación de cupos diarios por promoción (ej. el caso TNE del documento).
3. **Rol Staff real** — la columna existe pero no protege nada; cualquier credencial admin es equivalente a "Administrador" sin restricción.
4. **Reserva por personas y por mesa** — solo cubre reserva individual por cupo de horario.
5. **Estados "Pendiente" y "Modificada"** — faltan para cerrar el ciclo de vida de reserva descrito en el §5.

## Conclusión

El repositorio no implementa el sistema descrito en el documento: implementa una parte de él, la de reservas, y la implementa bien. Cartelera y promociones —dos de los tres pilares del documento— siguen siendo contenido estático de código. Si el objetivo es llegar al alcance descrito en el documento, el trabajo pendiente es de construcción de módulos (cartelera, promociones, mesas, roles), no de arquitectura: la base técnica (PHP + MySQL + panel propio) ya soporta extenderse en esa dirección.
