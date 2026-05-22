# GrowthOS

GrowthOS es una plataforma SaaS interna desarrollada con Laravel 12, Vue 3 e Inertia.js siguiendo arquitectura limpia, principios SOLID y buenas prácticas enterprise.

Actualmente incluye:
- Sistema de autenticación
- RBAC (Roles & Permissions)
- CRUD profesional de usuarios
- Arquitectura modular
- Service Layer
- Componentes reutilizables
- UI moderna tipo SaaS

---

# Stack Tecnológico

## Backend
- PHP 8.3
- Laravel 12
- MySQL
- Spatie Laravel Permission

## Frontend
- Vue 3
- Inertia.js
- TailwindCSS

## Entorno
- Windows + Laragon
- Composer
- Node.js + NPM

---

# Características Implementadas

## Autenticación
- Login
- Logout
- Middleware auth

## RBAC
- Roles
- Permissions
- Super Admin
- Employee

## CRUD Usuarios
- Listado
- Crear usuarios
- Editar usuarios
- Eliminar usuarios
- Búsqueda realtime
- Validaciones
- Flash messages

## Arquitectura
- Service Layer
- Form Requests
- Policies preparadas
- Componentes Vue reutilizables
- Arquitectura escalable

---

# Instalación

## 1. Clonar repositorio

```bash
git clone https://github.com/TU-USUARIO/growthos.git
```

## 2. Entrar al proyecto
```bash
cd growthos
```

## 3. Instalar dependencias PHP
```bash
composer install
```

## 4. Instalar dependencias frontend
```bash
npm install
```

## 5. Crear archivo .env
```bash
cp .env.example .env
```

## 6. Configurar base de datos
Editar .env
```bash
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=growthos
DB_USERNAME=root
DB_PASSWORD=
```

## 7. Generar APP_KEY
```bash
php artisan key:generate
```

## 8. Ejecutar migraciones
```bash
php artisan migrate
```

## 9. Ejecutar seeders
```bash
php artisan db:seed
```

## 10. Levantar frontend
```bash
npm run dev
```

## 11. Levantar servidor Laravel
```bash
php artisan serve
```

## Usuario Administrador
```bash
Email: admin@growthos.com
Password: password
```

## Estructura del Proyecto
```bash
app/
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   └── Middleware/
├── Models/
├── Policies/
├── Services/
└── Providers/

resources/
└── js/
    ├── Components/
    ├── Layouts/
    └── Pages/
```

## Componentes Reutilizables
## Forms
- TextInput.vue
- SearchInput.vue

## UI
- PrimaryButton.vue
- FlashMessage.vue

## Arquitectura Aplicada
- SOLID
- DRY
- KISS
- Service Layer
- Reusable Components
- Escalabilidad
- Clean Code

## Comandos útiles
## Backend
```bash
php artisan serve
php artisan migrate
php artisan optimize:clear
php artisan permission:cache-reset
php artisan tinker
```
## Frontend
```bash
npm run dev
npm run build
```

## Roadmap
## Infraestructura UI
- Modal system
- DataTable reusable
- Toast notifications
- Confirm dialogs
- Pagination component
## SaaS Core
- Dashboard
- Activity logs
- Notifications
- Reports
- Settings
## Multi-Tenancy
- Tenant isolation
- Tenant middleware
- Tenant settings
- Subscription system

## Licencia
MIT
