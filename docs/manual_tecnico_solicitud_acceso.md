# Documentación Técnica y Funcional: Control de Acceso y Gestión de Funcionarios

Este documento sirve como base de conocimiento detallada para generar un manual de usuario profesional para el cliente final. Contiene las especificaciones exactas del flujo de solicitudes de acceso, restricciones de dominios de correo y gestión dinámica de cargos.

---

## 1. Restricción y Bloqueo de Correos No Institucionales

### Comportamiento del Sistema:
* **Filtro Estricto:** El sistema prohíbe el acceso a cualquier usuario que intente ingresar con un correo personal (ej. `@gmail.com`, `@hotmail.com`, `@yahoo.com`) o corporativo de un dominio que no pertenezca a ningún colegio registrado en el sistema.
* **Validación Dinámica:** Las cuentas permitidas se validan en tiempo real contra la base de datos, verificando que el dominio del correo electrónico (el sufijo después de la `@`) coincida exactamente con la columna `domain` de la tabla `schools` (ej. `newheavenhs.cl` o `eben-ezer.cl`).
* **Expulsión Inmediata (Middleware):** Si un usuario con un correo no autorizado (como `@gmail.com`) intenta acceder de forma directa a cualquier ruta del sistema, el middleware global `RestrictGmailUsers` intercepta la petición, cierra la sesión del usuario inmediatamente, destruye sus cookies de sesión y lo redirige a la pantalla de login.
* **Mensaje de Alerta Estándar:** El usuario expulsado verá un banner rojo con el mensaje:
  > *"Solo se permite el acceso a correos institucionales."*

---

## 2. Flujo de Solicitud de Acceso para Nuevos Usuarios (`/sin-permiso`)

Cuando un funcionario ingresa por primera vez a través de su cuenta de Google institucional autorizada (ej. `@newheavenhs.cl`), pero aún **no tiene ningún rol asignado por el administrador**, el sistema activa el protocolo de registro pendiente:

### Pasos del Flujo:
1. **Asignación del Rol Pendiente:** Al iniciar sesión por primera vez, el sistema crea al usuario y le asigna automáticamente un rol por defecto de seguridad llamado `'externo'`.
2. **Redirección Automática:** El middleware `CheckRole` detecta que el usuario tiene únicamente el rol `'externo'` y lo redirige automáticamente a la pantalla de solicitud de acceso: `/sin-permiso`.
3. **Interfaz Libre de Sidebar (Diseño Limpio):** Esta pantalla cuenta con un diseño a pantalla completa, con fondo animado azul oscuro de alta fidelidad, **completamente libre de barras laterales o menús de administración (sin sidebar)**.
4. **Opciones Disponibles en Pantalla:**
   * **Cerrar Sesión:** Un botón superior derecho que destruye la sesión y regresa al usuario al login.
   * **Parrilla de Selección de Roles:** Tarjetas interactivas donde el usuario puede elegir cuál es su función en el establecimiento:
     * *Docente*
     * *Recepción / Portería*
     * *Directivo*
     * *Estudiante*
     * *Inspectoría*
     * *Asistente de Educación*
5. **Envío de Solicitud en Tiempo Real:** Al hacer clic sobre cualquiera de los cargos:
   * Se despacha una notificación interna en la base de datos (`SolicitudAcceso`) **únicamente** a los usuarios con rol de `administrador` o `superadmin` en el colegio.
   * La sesión del solicitante se cierra automáticamente por seguridad.
   * El usuario es redirigido a la pantalla de login con un mensaje de éxito:
     > *"Solicitud de acceso enviada con éxito. El administrador ha sido notificado."*

---

## 3. Visualización y Gestión de Solicitudes desde la Administración

### Listado de Funcionarios (`/funcionarios`):
* **Filtro de Pendientes:** Se ha añadido una opción llamada **"Pendiente"** en el selector de filtros de "Cargo (Rol)" en la barra superior. Al seleccionarla, la administración puede listar instantáneamente a todos los usuarios que han solicitado acceso y están en espera de aprobación.
* **Cargos Reales (Insignias Dinámicas):** La columna "Cargo" ya no muestra etiquetas estáticas. Ahora lee en tiempo real los roles que posee el funcionario en la base de datos y dibuja insignias de colores personalizadas para cada rol:
  * **Docente:** Badge Azul 🔵
  * **Inspector:** Badge Índigo 🟣
  * **Recepción / Portería:** Badge Esmeralda 🟢
  * **Directivo:** Badge Violeta 🟣
  * **Administrador:** Badge Rosa 🔴
  * **Pendiente (Externo):** Badge Naranja con el texto *"Pendiente"* 🟠

### Ficha Digital y Aprobación de Solicitudes (`/funcionarios/ficha/{id}`):
Cuando el administrador abre la ficha digital de un funcionario marcado como **Pendiente** para aprobarlo:
1. **Casillas de Selección:** El administrador selecciona los roles definitivos que tendrá el funcionario en el colegio (ej. marcando la casilla de *Docente de Aula*).
2. **Depuración Automática de Roles:** Al presionar **"Guardar Ficha"**:
   * El sistema detecta que se le ha asignado uno o más roles de trabajo válidos.
   * Remueve de forma automática el rol temporal `'externo'` (Pendiente) del usuario.
   * Guarda únicamente los nuevos roles activos.
3. **Activación Inmediata:** La próxima vez que este funcionario inicie sesión con su cuenta institucional, el sistema ya no lo enviará a `/sin-permiso`, sino que lo redirigirá directamente a su panel de control correspondiente según su nuevo rol (ej. `/entrevistas/agenda` si es docente).
