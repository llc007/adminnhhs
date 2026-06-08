# 🛠️ Documentación Técnica del Sistema de Gestión

Este documento contiene la arquitectura de datos y el mapa de relaciones del ecosistema de la plataforma (Laravel 12 + Livewire 3 + Flux UI).

---

## 1. 🗺️ Mapa de Dominios (Módulos del Sistema)

Para mantener el orden conceptual y guiar el desarrollo con IA, las tablas se agrupan en 4 dominios principales:

| Dominio | Tablas Incluidas | Propósito / Alcance |
| :--- | :--- | :--- |
| **Core & Multi-tenancy** | `schools`, `users`, `school_user` | Permite que el sistema escale a múltiples colegios compartiendo la misma BD. Soportado por la tabla pivote de usuarios. |
| **Gestión Académica** | `academic_years`, `academic_terms`, `cursos`, `estudiantes` | Estructura los periodos escolares, niveles, letras (ej: 1° Medio D) y fichas de alumnos. |
| **Inventario y Bitácoras**| `articulo_inventarios`, `acta_entregas`, `acta_entrega_detalles`, `bitacoras` | Controla los dispositivos tecnológicos (tablets, notebooks), movimientos de carros y firmas de actas de entrega. |
| **Soporte y Entrevistas**| `requerimientos`, `requerimiento_items`, `entrevistas`, `lugares_atencion` | Flujo de tickets de soporte técnico interno y agenda de entrevistas/atenciones en el establecimiento. |
| **Estructura Laravel** | `migrations`, `cache`, `cache_locks`, `failed_jobs`, `jobs`, `job_batches`, `notifications`, `sessions`, `password_reset_tokens` | Tablas nativas del framework para manejo de colas (queues), sesiones, caché y seguridad. |

---

## 2. 📊 Diagrama Entidad-Relación (ERD)

A continuación se detalla el mapa relacional del sistema utilizando sintaxis Mermaid.js.

```mermaid
erDiagram
    %% Core & Multi-tenancy
    schools ||--o{ school_user : "pertenece a"
    users ||--o{ school_user : "tiene asignado"
    
    %% Gestión Académica
    schools ||--o{ academic_years : "configura"
    academic_years ||--o{ academic_terms : "se divide en"
    academic_years ||--o{ cursos : "contiene"
    cursos ||--o{ estudiantes : "tiene inscritos"
    
    %% Inventario y Actas
    schools ||--o{ articulo_inventarios : "posee"
    articulo_inventarios ||--o{ bitacoras : "registra eventos en"
    acta_entregas ||--o{ acta_entrega_detalles : "contiene"
    users ||--o{ acta_entregas : "gestiona / entrega"
    estudiantes ||--o{ acta_entregas : "recibe (opcional)"
    
    %% Soporte y Atención
    users ||--o{ requerimientos : "crea ticket"
    requerimientos ||--o{ requerimiento_items : "se detalla en"
    lugares_atencion ||--o{ entrevistas : "ocurre en"
    users ||--o{ entrevistas : "entrevista a"
    estudiantes ||--o{ entrevistas : "es entrevistado"

    schools {
        int id PK
        string nombre
    }
    users {
        int id PK
        string name
        string email
    }
    cursos {
        int id PK
        int academic_year_id FK
        string nivel
        string letra
        string modalidad
    }
    estudiantes {
        int id PK
        int curso_id FK
        string rut
        string nombres
        string apellidos
        string email_institucional
    }
    bitacoras {
        int id PK
        int articulo_inventario_id FK
        text detalle
    }
