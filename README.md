# Project Board for Laravel Nova

[![Latest Version on Packagist](https://img.shields.io/packagist/v/eavio/project-board.svg?style=flat-square)](https://packagist.org/packages/eavio/project-board)
[![Total Downloads](https://img.shields.io/packagist/dt/eavio/project-board.svg?style=flat-square)](https://packagist.org/packages/eavio/project-board)
[![License](https://img.shields.io/packagist/l/eavio/project-board.svg?style=flat-square)](https://packagist.org/packages/eavio/project-board)

A powerful, feature-rich Kanban board resource tool for Laravel Nova, designed to manage projects, tasks, and workflows seamlessly within your Nova administration panel.

![Nova 5.0+](https://img.shields.io/badge/Nova-5.0+-blue)
![Laravel 10+](https://img.shields.io/badge/Laravel-10+-red)
![PHP 8.1+](https://img.shields.io/badge/PHP-8.1+-purple)

## 🚀 Features

*   **Interactive Kanban Board**
    *   **Drag & Drop**: Smoothly move cards between columns and reorder them within columns.
    *   **Column Management**: Create, rename, and delete columns easily.
    *   **Inline Editing**: Quick-add cards and rename columns/cards directly from the board view.

*   **Rich Card Details**
    *   **Descriptions**: Full markdown support for detailed task descriptions.
    *   **Checklists**: Create multiple checklists with progress tracking.
    *   **Attachments**: Drag-and-drop file uploads with preview support.
    *   **Activity & Comments**: Real-time activity logging and commenting system with user attribution.
    *   **Due Dates**: Set due dates with visual status indicators (Overdue, Complete).

*   **Organization & Search**
    *   **Labels**: Color-coded labelling system (Trello-style) for easy visual categorization.
    *   **Assignees**: Assign users to cards with avatar previews.
    *   **Advanced Search**: Integrated search bar to find cards, comments, and attachments instantly.
    *   **Filtering**: Filter by user, label, or due date (Coming soon).

*   **Native Nova Experience**
    *   **Dark Mode Support**: Fully compatible with Nova's dark mode.
    *   **Responsive**: Optimized for various screen sizes.
    *   **Resource Integration**: Can be attached as a `ResourceTool` to specific resources (e.g., `Project`) or used as a standalone Tool page.

*   **Import from Trello**
    *   **Full Board Import**: Import entire Trello boards including lists, cards, labels, checklists, and comments.
    *   **Queued Processing**: Large boards are imported via background jobs to prevent timeouts.
    *   **Progress Tracking**: Nova notifications keep you informed of import status.
    *   **Attachment Support**: Attachments are downloaded and stored via Spatie MediaLibrary.

*   **External API**
    *   **REST API**: Full API for external integrations (ChatGPT, Zapier, etc.)
    *   **OpenAPI 3.1**: Auto-generated specification for easy integration
    *   **Token Management**: Create and manage API tokens directly from Nova UI

## 📦 Installation

### Requirements

- PHP 8.1+
- Laravel 10, 11, or 12
- Laravel Nova 5.0+
- [Spatie Laravel MediaLibrary](https://github.com/spatie/laravel-medialibrary) 10.15+

### Step 1: Install via Composer

```bash
composer require eavio/project-board
```

> **Note**: This package requires Laravel Nova, which is a commercial product. You must have a valid Nova license and the Nova composer repository configured.

### Step 2: Run Migrations

```bash
php artisan migrate
```

Or publish and customize migrations first:

```bash
php artisan vendor:publish --tag=project-board-migrations
php artisan migrate
```

### Step 3: Register the Tool

Add the tool to your `NovaServiceProvider.php`:

```php
use Eavio\ProjectBoard\ProjectBoard;

public function tools()
{
    return [
        new ProjectBoard,
    ];
}
```

### Step 4 (Optional): Attach to a Resource

To display a project board on a specific resource (e.g., Company, Project):

```php
use Eavio\ProjectBoard\ProjectBoardResourceTool;

public function fields(Request $request)
{
    return [
        // ... other fields
        ProjectBoardResourceTool::make(),
    ];
}
```

### Step 5: Route Configuration

**Important**: If your application has catch-all routes (e.g., `/{slug}`), you must exclude `api` and `project-board` from those patterns to prevent route conflicts:

```php
// In routes/web.php - add 'api' and 'project-board' to your pattern exclusions
Route::pattern('slug', '^(?!console|nova|api|project-board).*');
```

Without this, requests to `/api/project-board/*` may be incorrectly matched by catch-all routes and return 404 errors.

## 🛠 Usage

### Creating a Board
Navigate to the "Projects Board" tool in the Nova sidebar. Click "Create Board" to start a new project board.

### Managing Cards
*   **Add Card**: Click the "+ Add a card" button at the bottom of any column.
*   **Edit Details**: Click on any card to open the detailed modal view.
*   **Move**: Drag cards between columns to change their status.

### Search
Use the search bar in the top right to quickly filter cards across all columns. The search indexes titles, descriptions, and comments.

### Importing from Trello

You can import entire Trello boards into Project Board:

1. **Export from Trello**:
   - Open your Trello board
   - Click "..." menu → "More" → "Print and export"
   - Select "Export as JSON"

2. **Import into Project Board**:
   - Open Project Board in Nova
   - Click the dropdown menu (⋮)
   - Select "Import from Trello"
   - Upload the JSON file
   - Click "Start Import"

3. **What gets imported**:
   - Board name and description
   - All lists (as columns) with correct ordering
   - All cards with title, description, due dates, and position
   - Labels (mapped by color)
   - Checklists with items and completion state
   - Comments (as activity entries)
   - External attachments (downloaded to your storage)

**Note**: The import runs as a background job. For large boards (300+ cards), this may take several minutes. You'll receive a Nova notification when the import completes.

## 🔌 External API

Project Board includes a full REST API for external integrations (ChatGPT, Zapier, custom apps, etc.).

### API Authentication

The API uses Bearer token authentication. There are two ways to create tokens:

#### Option 1: Via Nova UI (Recommended)

1. Open Projects Board in Nova
2. Click the dropdown menu (⋮) next to the board name
3. Select "API Tokens"
4. Click "Create New Token"
5. Copy the token immediately (it won't be shown again)

#### Option 2: Via API (requires Sanctum auth)

```bash
POST /api/project-board/tokens
{
  "name": "My Integration",
  "abilities": ["*"],
  "expires_at": "2025-12-31"
}
```

#### Using the Token

Include the token in your API requests:
```bash
Authorization: Bearer your-token-here
```

### OpenAPI Specification

An OpenAPI 3.1 specification is available for ChatGPT Actions and other integrations:

- **Dynamic endpoint**: `/project-board/openapi.json` (auto-detects your domain)
- **Static file**: `public/openapi/project-board.json`

To use with ChatGPT:
1. Go to your GPT's Actions settings
2. Import the OpenAPI spec from `https://your-domain.com/project-board/openapi.json`
3. Set up authentication with a Bearer token

### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/project-board/boards` | List all accessible boards |
| POST | `/api/project-board/boards` | Create a new board |
| GET | `/api/project-board/boards/{id}` | Get board details |
| PATCH | `/api/project-board/boards/{id}` | Update a board |
| POST | `/api/project-board/boards/{id}/columns` | Create a column |
| POST | `/api/project-board/boards/{id}/columns-with-cards` | Create column with cards |
| POST | `/api/project-board/boards/{id}/cards/bulk` | Bulk create cards |
| PATCH | `/api/project-board/boards/{id}/cards/bulk-update` | Bulk update cards |
| GET | `/api/project-board/cards/search` | Search cards |
| GET | `/api/project-board/cards/{id}` | Get card details |
| POST | `/api/project-board/cards` | Create a card |
| PATCH | `/api/project-board/cards/{id}` | Update a card |
| DELETE | `/api/project-board/cards/{id}` | Delete a card |
| POST | `/api/project-board/cards/{id}/move` | Move card to column |
| POST | `/api/project-board/cards/{id}/comments` | Add comment |
| POST | `/api/project-board/cards/{id}/attachments` | Add attachment via URL |
| PATCH | `/api/project-board/columns/{id}` | Update a column |
| DELETE | `/api/project-board/columns/{id}` | Delete a column |

### User Model Setup

Add the API tokens relationship to your User model:

```php
// In app/Models/User.php

public function apiTokens()
{
    return $this->hasMany(\Eavio\ProjectBoard\Models\UserApiToken::class);
}
```

This relationship is required for the token management functionality to work.

## ⚙️ Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=project-board-config
```

Available options in `config/project-board.php`:

```php
return [
    'user_model' => \App\Models\User::class,
    'api' => [
        'enabled' => true,
        'rate_limit' => 60,
    ],
    'unsplash' => [
        'access_key' => env('UNSPLASH_ACCESS_KEY'),
    ],
];
```

## 📝 License

This project is open-sourced software licensed under the [MIT license](LICENSE).

## 🙏 Credits

- [eAvio Aero](https://eavio.aero)
- [Spatie](https://spatie.be) for Laravel MediaLibrary
- [Laravel Nova](https://nova.laravel.com)

## 🐛 Issues & Contributing

Please report issues on [GitHub](https://github.com/eAvio/project-board/issues).
