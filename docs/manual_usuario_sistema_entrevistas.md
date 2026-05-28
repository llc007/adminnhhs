# Base de Conocimiento: Manual de Usuario - Sistema de Gestión de Entrevistas

Este documento detalla el funcionamiento de los tres módulos principales de la plataforma de entrevistas y atención a apoderados. Sirve como insumo estructurado para que otra IA redacte el manual de usuario final para docentes, recepcionistas y directivos.

---

## MÓDULO 1: Agendamiento y Creación de Entrevistas

Este módulo permite a los docentes e inspectores agendar una nueva cita con un apoderado en el establecimiento o de manera virtual.

### Paso 1: Selección del Estudiante
Existen dos formas rápidas de encontrar y seleccionar al estudiante en la pantalla de creación (`/entrevistas/crear`):
* **Búsqueda por Texto (Filtro Rápido):** Escribe el nombre, apellido o el número de RUT del estudiante en el buscador (mínimo 3 caracteres). El sistema desplegará una lista con hasta 5 coincidencias dinámicas para hacer clic y seleccionarlo directamente.
* **Búsqueda Híbrida por Curso:** Selecciona la clase/curso en el selector desplegable. Esto abrirá un modal flotante con la lista completa de alumnos inscritos en ese curso específico, ordenados alfabéticamente para hacer clic en el deseado.

### Paso 2: Datos de la Entrevista
Una vez seleccionado el alumno, se rellenan los datos de agendamiento:
1. **Fecha:** Selección del día de la reunión en el calendario.
2. **Hora:** Especificación de la hora de inicio.
3. **Nivel de Urgencia:** Se categoriza la cita según su relevancia:
   * *Normal*
   * *Prioritario*
   * *Urgente*
4. **Lugar de Atención:** Define la modalidad:
   * *Presencial* (por defecto)
   * *Online* (para reuniones virtuales)
5. **Motivo de la Cita:** Un texto obligatorio que detalla la razón de la citación.
6. **Notas Internas:** Un campo de texto opcional para agregar observaciones previas exclusivas para el docente.

### Paso 3: Control de Choque de Horarios (Tope Horario)
* Si intentas agendar una reunión en una fecha y hora en la que **ya tienes otra entrevista programada**, el sistema te mostrará una advertencia destacada en rojo:
  > *"¡Advertencia! Ya tienes una entrevista con otro apoderado en esta misma fecha y hora. Vuelve a hacer clic en 'Agendar' si deseas guardar de todos modos."*
* Esto funciona como un control de seguridad inteligente que te permite ignorar la advertencia y sobreescribir el horario si de verdad lo requieres, simplemente volviendo a presionar el botón "Agendar".

---

## MÓDULO 2: Consulta del Historial y la Agenda Docente

### 1. Historial General de Entrevistas (`/entrevistas`)
Es la consola de visualización general orientada a directivos y administradores. Cuenta con:
* **Indicadores Bento en Tiempo Real:** Paneles informativos superiores que muestran el *Total de Entrevistas*, *Porcentaje de Realizadas*, *Pendientes* y *Canceladas / Ausentes*.
* **Exportación de Datos:** Un botón para descargar la lista completa de entrevistas filtradas en un formato compatible con Excel (`.xlsx`).
* **Filtros Avanzados (Multi-criterio):**
  * *Buscador libre:* Busca por nombres del alumno, nombres del apoderado, RUT o nombre del profesor a cargo.
  * *Por Profesor:* Filtra para ver las citas de un docente en particular.
  * *Por Curso:* Filtra por la clase del estudiante.
  * *Por Estado:* Filtra por citas *pendientes, ingresadas, realizadas, canceladas o ausentes*.
  * *Rango Temporal Rápido:* Filtros de un solo clic para ver citas del **Día, de la Semana o del Mes** a partir de una fecha base seleccionada en el calendario.

### 2. Agenda Personal del Docente (`/entrevistas/agenda`)
El panel exclusivo para cada profesor, donde visualizan sus compromisos:
* Visualiza una agenda organizada con sus próximas entrevistas asignadas.
* Permite filtrar rápidamente por estado de la cita.
* **Acceso Directo a la Bitácora (Ficha de Reunión):** Los docentes pueden hacer clic en cualquier entrevista en curso o realizada para acceder a la pantalla de **Bitácora** (`/entrevistas/{id}/bitacora`), donde podrán redactar en tiempo real:
  * Los antecedentes expuestos.
  * Observaciones de conducta o académicas.
  * Acuerdos y compromisos específicos firmados por el apoderado y el docente.

---

## MÓDULO 3: Recepción y Control de Acceso (Portería)

Este panel especial (`/entrevistas/recepcion`) es operado por el personal de portería o recepción para gestionar físicamente el flujo de apoderados que ingresan al recinto escolar.

### 1. Monitoreo de Entrada
* **Métricas de Control de Flujo:** Muestra en tiempo real cuántas visitas se esperan hoy, cuántos apoderados ya están adentro y cuántos faltan por llegar.
* **Estado de Boxes (Salas de Reunión):** Muestra una cuadrícula dinámica con el estado de ocupación de las salas físicas del colegio (ej. Box 1, Box 2, etc.):
  * **Verde (Disponible):** Sala lista para ser asignada.
  * **Rojo (Ocupado):** Muestra qué docente y qué apoderado se encuentran actualmente utilizando ese Box.

### 2. Protocolo de Ingreso de Visitas (Check-In)
Cuando un apoderado se presenta en la entrada:
1. El recepcionista lo busca en la lista de citas del día (usando su nombre o el del alumno en el buscador).
2. Presiona el botón de **Marcar Ingreso** (Check-in).
3. Se abre un modal flotante donde el recepcionista:
   * **Asigna un Box/Lugar físico:** Selecciona de la lista de salas disponibles dónde se llevará a cabo la entrevista.
   * **Mensaje de Recepción:** Escribe un mensaje opcional para el docente (ej. *"El apoderado viene acompañado"* o *"Llegó 5 minutos tarde"*).
4. Al confirmar, el sistema cambia automáticamente el estado de la cita a `'ingresada'`, marca el Box asignado como "Ocupado" y **despacha una notificación en tiempo real (un Toast emergente en pantalla)** al docente a cargo, avisándole que su visita ha llegado y se encuentra esperándolo en el Box asignado.

### 3. Protocolo de Salida de Visitas (Check-Out)
Cuando la reunión finaliza y el apoderado se retira del establecimiento:
1. El recepcionista localiza al visitante en la lista de "Ingresados".
2. Presiona el botón de **Marcar Salida** (Check-out).
3. El sistema libera de inmediato la sala asignada (cambiando su estado a "Disponible" en color verde) y actualiza el estado de la entrevista en el registro histórico, notificando al docente que el apoderado ha abandonado el edificio de forma segura.
