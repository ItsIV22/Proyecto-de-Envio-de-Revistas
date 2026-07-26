Actúa como un Desarrollador Full Stack Senior experto en PHP puro, JavaScript Vanilla y PostgreSQL. Necesito que generes la estructura y el código base completo para un sistema de **Administración de Envío de Revistas a Domicilio**.

### 1. REQUERIMIENTOS DE ARQUITECTURA Y RESTRICCIONES
- **Sin Frameworks:** NO utilices frameworks de Backend (Laravel, Symfony, etc.) ni de Frontend (React, Vue, Angular, jQuery).
- **Separación de Responsabilidades:** El Frontend y el Backend deben estar totalmente desacoplados.
  - **Backend:** Desarrollado en PHP nativo (usando la extensión PDO para PostgreSQL). Funciona exclusivamente como una API REST que responde únicamente en formato JSON (`header('Content-Type: application/json')`).
  - **Frontend:** HTML5 puro, CSS (Bootstrap 5 vía CDN para los estilos) y JavaScript Vanilla nativo utilizando la API `fetch()` para realizar peticiones HTTP asíncronas hacia la API en PHP.
- **Base de Datos:** PostgreSQL local (preparada para migrar fácilmente a Render).
- **Documentación:** Todo el código (PHP, JS, SQL, HTML) debe incluir **comentarios didácticos y detallados** explicando el propósito de cada función, bloque o consulta.

---

### 2. MODELO DE DATOS (PostgreSQL)
Genera un archivo `schema.sql` bien documentado con la creación de la base de datos y sus 5 tablas relacionadas:
1. `revista` (id_revista PRIMARY KEY, titulo, categoria, periodicidad)
2. `ejemplar` (id_ejemplar PRIMARY KEY, id_revista FOREIGN KEY, numero_edicion, fecha_publicacion)
3. `persona` (id_persona PRIMARY KEY, nombre_completo, direccion_envio, ciudad, telefono)
4. `agencia_transporte` (id_agencia PRIMARY KEY, nombre_agencia, contacto)
5. `envio` (id_envio PRIMARY KEY, id_ejemplar FK, id_persona FK, id_agencia FK, fecha_despacho, estado ['Pendiente', 'En tránsito', 'Entregado'], numero_guia)

---

### 3. ESTRUCTURA DE ARCHIVOS A GENERAR

Asegúrate de separar estrictamente los archivos en las siguientes carpetas:

```text
proyecto-envio-revistas/
│
├── config/
│   └── database.php       # Conexión PDO a PostgreSQL (usando variables/constantes locales)
│
├── api/                   # BACKEND: Endpoint API REST en PHP
│   ├── revistas.php       # CRUD básico de Revistas (JSON)
│   ├── ejemplares.php     # CRUD básico de Ejemplares (JSON)
│   ├── personas.php       # CRUD básico de Clientes/Personas (JSON)
│   ├── agencias.php       # CRUD básico de Agencias (JSON)
│   └── envios.php         # Gestión del ciclo de vida de Envíos (JSON)
│
├── public/                # FRONTEND
│   ├── css/
│   │   └── styles.css     # Estilos personalizados adicionales
│   ├── js/
│   │   ├── app.js         # Funciones globales y utilidades de fetch
│   │   └── envios.js      # Lógica de interacción dinámica para el panel
│   └── index.html         # Panel de Control (Dashboard) estructurado con Bootstrap 5
│
└── schema.sql             # Script SQL de creación de tablas e inserción de datos de prueba

4. FUNCIONALIDAD DEL FRONTEND (public/index.html + js/envios.js)

El frontend debe presentar un Dashboard limpio con Bootstrap 5 que contenga:

    Pestañas o secciones para gestionar el catálogo (Revistas, Ejemplares, Personas, Agencias).

    Un módulo principal "Crear Envío" que tenga un formulario dinámico con campos desplegables (<select>) alimentados mediante fetch() desde la API:

        Seleccionar Persona.

        Seleccionar Ejemplar de Revista.

        Seleccionar Agencia de Transporte.

        Definir Fecha y Número de Guía.

    Una tabla interactiva para listar todos los envíos, mostrando sus datos vinculados y un botón/desplegable para actualizar rápidamente su estado (ej. cambiar de Pendiente a Entregado).

5. INSTRUCCIONES DE ENTREGA

Genera todo el código necesario archivo por archivo. Asegúrate de que el código sea limpio, legible, seguro (usando Sentencias Preparadas en PDO para evitar inyección SQL) y completamente comentado.