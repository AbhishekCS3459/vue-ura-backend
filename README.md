# Vue URA Backend

Laravel backend API for Vue URA Dashboard with Clean Architecture, IAM, and role-based access control.

## Features

- **Authentication**: Login/logout with Laravel Sanctum
- **IAM (Identity and Access Management)**: User management with role-based access
- **Page Permissions**: Configurable page access per role
- **Branch Filtering**: Automatic branch-based data filtering
- **Clean Architecture**: Separation of concerns with Domain, Application, and Infrastructure layers

## Roles

- **Super Admin**: Can access all branches and all pages
- **Branch Manager**: Can access only their assigned branch, can see: Schedule, Inactive Patients, Staff Management, Branch Analytics, Branch Settings, Report Builder, Report History
- **Staff**: Can access only their assigned branch, can see: Schedule, Inactive Patients (configurable via IAM)

## Setup

### Prerequisites

- PHP 8.2+
- Composer
- Docker and Docker Compose
- Laravel Herd (or any PHP environment)

### Installation

1. **Start PostgreSQL with Docker:**
   ```bash
   docker-compose up -d
   ```

2. **Install dependencies:**
   ```bash
   composer install
   ```

3. **Configure environment:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Update `.env` with PostgreSQL settings:**
   ```env
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=vue_ura_db
   DB_USERNAME=vue_ura_user
   DB_PASSWORD=vue_ura_password
   ```

5. **Run migrations and seeders:**
   ```bash
   php artisan migrate
   php artisan db:seed --class=PagePermissionSeeder
   ```

6. **Start the development server:**
   ```bash
   php artisan serve
   ```

## API Endpoints

### Authentication

- `POST /api/auth/login` - Login
- `POST /api/auth/logout` - Logout (requires auth)
- `GET /api/auth/me` - Get current user (requires auth)

### IAM

- `GET /api/iam/users` - Get all users (filtered by branch)
- `POST /api/iam/users` - Create user
- `PUT /api/iam/users/{id}` - Update user
- `DELETE /api/iam/users/{id}` - Delete user
- `GET /api/iam/page-permissions` - Get all page permissions
- `GET /api/iam/role-permissions/{role}` - Get allowed pages for a role
- `POST /api/iam/role-permissions` - Set role page permission

## Architecture

Following Clean Architecture principles:

- **Domain**: Core business entities and interfaces
- **Application**: Business logic services
- **Infrastructure**: Data access (repositories) and external services
- **Controllers**: HTTP request/response handling only

## Database

PostgreSQL database running in Docker. Connection details:
- Host: `127.0.0.1`
- Port: `5432`
- Database: `vue_ura_db`
- Username: `vue_ura_user`
- Password: `vue_ura_password`

## Security

- All inputs are validated and sanitized
- Password hashing with bcrypt
- Token-based authentication with Sanctum
- Branch-based access control
- Role-based page permissions
