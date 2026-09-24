# Manual de usuario · Viña Stage

**Versión:** 24 de septiembre de 2026

**Sitio público:** https://reservas-stage.vercel.app
**Panel administrativo:** https://reservas-stage.vercel.app/#/admin

Este manual explica cómo utilizar el sitio como visitante y cómo administrar reservas, configuración y disponibilidad. Las capturas corresponden al sitio publicado actualmente.

> No compartas tu contraseña, tokens, archivos `.env` ni capturas que muestren datos de acceso.

## 1. Para visitantes

### 1.1 Entrar al sitio

1. Abre https://reservas-stage.vercel.app.
2. Espera a que cargue la portada.
3. Usa el menú superior para moverte entre **Reservas**, **Cartelera** y **Contacto**.

![Captura de la portada de Viña Stage](capturas/04-portada.png)

### 1.2 Revisar la cartelera

Desde **Cartelera** puedes consultar los eventos y campañas publicados. Cada ficha muestra su fecha, nombre, descripción e imagen cuando existe.

### 1.3 Consultar contacto

En **Contacto** puedes revisar:

- Dirección.
- WhatsApp.
- Instagram y Facebook.
- Correo de contacto.
- Mapa de ubicación.

## 2. Hacer una reserva

### 2.1 Abrir el calendario

1. Selecciona **Reservas** en el menú.
2. Revisa el aviso de reservas, si aparece.
3. Elige un mes disponible usando las flechas del calendario.
4. Selecciona un día marcado como disponible.
5. Selecciona un horario con cupos libres.

![Captura del calendario de reservas](capturas/01-reservas.png)

> Los días sin disponibilidad, cupos agotados o fuera del período publicado no se pueden seleccionar.

### 2.2 Completar los datos

Después de elegir día y horario, completa:

- Nombre.
- Apellido.
- RUT.
- WhatsApp.
- Correo electrónico.
- Aceptación de las condiciones o consentimiento, si aparece.

El RUT debe ser válido. El sistema lo valida antes de confirmar.

### 2.3 Confirmar

1. Revisa que todos los datos sean correctos.
2. Pulsa **Confirmar reserva**.
3. Espera la confirmación de éxito.
4. Guarda el **código de reserva** que aparece en pantalla.

Si la operación no se completa:

- No vuelvas a enviar el formulario repetidamente.
- Revisa tu conexión.
- Comprueba que los campos estén completos.
- Intenta una sola vez más.
- Si persiste el error, contacta al negocio.

## 3. Uso en celular

En celular, la interfaz se adapta al formato vertical. Para reservar:

1. Abre la sección **Reservas**.
2. Desplázate hasta el calendario.
3. Toca el día y luego el bloque horario.
4. Completa el formulario en pantalla.
5. Confirma y espera el código.

![Captura de reservas en celular](capturas/02-reservas-movil.png)

## 4. Acceder al panel administrativo

1. Abre https://reservas-stage.vercel.app/#/admin.
2. Ingresa el correo administrador entregado por la persona responsable del sistema.
3. Escribe la contraseña.
4. Pulsa **Ingresar**.

![Captura del acceso administrativo](capturas/03-admin-login.png)

Si el acceso falla, verifica que estés usando el dominio correcto y que tu sesión no esté bloqueada por cookies del navegador.

## 5. Administración: reservas

### 5.1 Resumen

La pestaña **Resumen** muestra indicadores y actividad reciente. Úsala como primera pantalla para conocer reservas del día y próximos turnos.

### 5.2 Ver reservas

1. Abre **Reservas**.
2. Usa los filtros de fecha, estado y búsqueda.
3. Revisa la tabla con fecha, hora, nombre, WhatsApp, código y estado.
4. Si corresponde, confirma por WhatsApp utilizando el enlace de contacto.

### 5.3 Cambiar estado

En cada fila puedes utilizar:

- **Confirmar**: reserva confirmada.
- **Asistió**: el cliente asistió.
- **Cancelar**: la reserva fue cancelada.

No borres información que necesites para historial. Usa el estado correspondiente.

## 6. Administración: configuración

En **Config** puedes editar y guardar:

- Nombre del sitio.
- Dirección.
- WhatsApp de contacto.
- Instagram.
- Fecha inicial del calendario.
- Activación o desactivación de reservas.
- Aviso visible para usuarios.

Pulsa **Guardar configuración** y espera el mensaje de éxito antes de salir.

## 7. Días y horarios

La disponibilidad y los horarios se administran desde las funciones de administración del sistema. Antes de cambiar:

1. Confirma la fecha y hora nueva.
2. Revisa que el bloque no tenga reservas existentes.
3. Guarda y verifica que el calendario público muestre el cambio.
4. No elimines un bloque que aún tenga reservas activas.

La capacidad definitiva de cupos debe ser confirmada por el negocio antes de cambiar la base de datos.

## 8. Problemas frecuentes

### La disponibilidad no carga

Verifica que tengas conexión a internet. Si el mensaje menciona HTML o JSON, el problema apunta a la URL de la API y debe revisarse en el despliegue.

### El día no se puede seleccionar

Puede estar fuera del rango publicado, desactivado o sin cupos disponibles.

### El RUT es inválido

Revisa que el número tenga dígitos y dígito verificador correctos. No uses letras, espacios especiales ni un RUT de otra persona.

### No se confirma la reserva

No cierres la página inmediatamente. Espera la respuesta. Si sigue fallando, guarda el código o captura el mensaje de error y contacta al negocio.

### El admin no inicia sesión

Confirma correo, contraseña, dominio y conexión. Si usaste Safari o un modo privado, prueba Chrome. También verifica que la URL pública del admin sea `/#/admin`.

## 9. Soporte

Para soporte, entrega al responsable técnico:

- Fecha y hora de la ocurrencia.
- Navegador y dispositivo.
- URL utilizada.
- Mensaje exacto, sin contraseña ni token.
- Captura ocultando datos personales.

Nunca envíes contraseñas, claves de base de datos ni contenido de `.env`.

## 10. Respaldo de la información

Antes de cambiar configuración o eliminar reservas, confirma que existe un respaldo actualizado. El código del sitio está versionado en GitHub, pero los datos de reservas están en MySQL y requieren un respaldo independiente de la base de datos.
