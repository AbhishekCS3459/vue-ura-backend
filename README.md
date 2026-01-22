# Vue URA Backend

Laravel backend API for the Vue URA Dashboard application.

## Features

- **Clean Architecture**: Implemented with Domain, Application, and Infrastructure layers
- **Authentication**: Laravel Sanctum for API token-based authentication
- **IAM System**: Role-based access control with three roles:
  - Super Admin
  - Branch Manager
  - Staff
- **Branch-based Filtering**: Users can only access data for their assigned branch (except Super Admin)
- **Page Permissions**: Configurable page access per user, stored in users table
- **Docker PostgreSQL**: Database setup with Docker Compose

## Tech Stack

- Laravel 11
- PostgreSQL (via Docker)
- Laravel Sanctum (Authentication)
- Clean Architecture / Hexagonal Architecture

## Setup

### Prerequisites

- PHP 8.2+
- Composer
- Docker & Docker Compose
- Laravel Herd (recommended) or PHP CLI

### Installation

1. Install dependencies:
```bash
composer install
```

2. Copy environment file:
```bash
cp .env.example .env
```

3. Generate application key:
```bash
php artisan key:generate
```

4. Start PostgreSQL database:
```bash
docker-compose up -d
```

5. Update `.env` with database credentials:
```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=vue_ura_db
DB_USERNAME=vue_ura_user
DB_PASSWORD=vue_ura_password
```

6. Run migrations:
```bash
php artisan migrate
```

7. Seed initial data:
```bash
php artisan db:seed --class=SuperAdminSeeder
php artisan db:seed --class=BranchSeeder
php artisan db:seed --class=PagePermissionSeeder
```

8. Start the development server:
```bash
php artisan serve
```

## API Endpoints

### Authentication
- `POST /api/auth/login` - User login
- `POST /api/auth/register` - User registration
- `POST /api/auth/logout` - User logout (requires auth)
- `GET /api/auth/me` - Get current user (requires auth)

### Health Check
- `GET /api/health` - Health check endpoint

### Branches
- `GET /api/branches` - Get all branches

### IAM
- `GET /api/iam/users` - Get all users (requires auth)
- `POST /api/iam/users` - Create user (requires auth, super admin only)
- `PUT /api/iam/users/{id}` - Update user (requires auth, super admin only for role changes)
- `DELETE /api/iam/users/{id}` - Delete user (requires auth)
- `GET /api/iam/page-permissions` - Get all page permissions (requires auth)
- `GET /api/iam/role-permissions/{role}` - Get role permissions (requires auth)
- `POST /api/iam/role-permissions` - Set role permission (requires auth)

## Database Schema

### Users
- `id`, `name`, `email`, `password`, `role`, `branch_id`, `page_permissions` (JSON)

### Branches
- `id`, `name`, `city`, `opening_hours` (JSON)

### Page Permissions
- `id`, `page_key`, `page_name`, `description`

### Role Page Permissions
- `id`, `role`, `page_permission_id`, `is_allowed`

## Default Super Admin

- Email: `abhishekverman3459@gmail.com`
- Password: `password123` (change in production!)

## Architecture

The application follows Clean Architecture principles:

- **Domain Layer**: Core business entities and interfaces
  - `app/Domain/Entities/`
  - `app/Domain/Interfaces/`

- **Application Layer**: Business logic and use cases
  - `app/Application/Services/`

- **Infrastructure Layer**: External concerns (database, APIs)
  - `app/Infrastructure/Repositories/`

- **HTTP Layer**: Controllers and routes
  - `app/Http/Controllers/`
  - `routes/api.php`

## License

Proprietary
