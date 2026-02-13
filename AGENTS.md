# Impostor Game - AI Agent Guide

This document provides essential information for AI coding agents working on the Impostor game project.

## Project Overview

This is a **social deduction multiplayer game** where players join lobbies, receive secret words (with impostors getting similar but different words), and vote to eliminate suspected impostors. The game supports real-time gameplay via polling with Laravel Echo broadcasting ready for WebSocket integration.

### Key Features
- **Lobby System**: Create/join lobbies with auto-generated 6-character codes
- **Turn-Based Gameplay**: Players take turns describing their word without revealing it
- **Real-Time Updates**: Game state polling (2-second intervals) with broadcasting events
- **Configurable Settings**: Impostor count, max players, discussion time, voting time, word difficulty
- **Chat System**: Public chat and private DMs between players
- **Voting Mechanics**: Vote to start voting immediately (70% threshold) or reroll words
- **Win Tracking**: Persistent win counters for impostors and crew across multiple rounds
- **Word Selection**: Cooldown system (100 rounds) to prevent word repetition

## Technology Stack

| Component | Version | Purpose |
|-----------|---------|---------|
| PHP | ^8.2 (8.4 recommended) | Backend language |
| Laravel | ^12.0 | Backend framework |
| Inertia.js | ^2.0 | SPA without API complexity |
| Vue.js | ^3.5 | Frontend framework |
| Tailwind CSS | ^4.1 | Utility-first CSS |
| TypeScript | ^5.2 | Type-safe JavaScript |
| Pest | ^4.3 | PHP testing framework |
| Vite | ^7.0 | Build tool |
| Wayfinder | ^0.1 | Type-safe Laravel routes in TypeScript |

### Key Dependencies
- `inertiajs/inertia-laravel` - Server-side Inertia adapter
- `laravel/wayfinder` - Route generation for TypeScript
- `@inertiajs/vue3` - Client-side Inertia for Vue
- `@tailwindcss/vite` - Tailwind CSS Vite plugin
- `axios` - HTTP client
- `@vueuse/core` - Vue utility library

## Project Structure

```
app/
├── Events/                 # Broadcasting events
│   ├── GameStarted.php
│   ├── PlayerJoined.php
│   ├── PlayerLeft.php
│   ├── PlayerVoted.php
│   ├── VotingEnded.php
│   ├── SettingsUpdated.php
│   ├── MessageSent.php
│   ├── TurnAdvanced.php
│   ├── VoteNowUpdated.php
│   ├── RerollUpdated.php
│   └── WordRerolled.php
├── Http/
│   ├── Controllers/
│   │   └── GameController.php    # Main game logic (all routes)
│   └── Middleware/
│       └── HandleInertiaRequests.php
├── Models/
│   ├── Lobby.php           # Game lobby with settings JSON
│   ├── Player.php          # Player with session-based tracking
│   ├── Word.php            # Word pairs (crew/impostor relationship)
│   ├── Message.php         # Chat messages (public and DM)
│   └── User.php            # Laravel default (unused - no auth)
├── Services/
│   └── WordSelectionService.php  # Word selection with 100-round cooldown
└── Providers/
    └── AppServiceProvider.php

resources/js/
├── pages/                  # Vue page components (Inertia pages)
│   ├── Welcome.vue         # Home page (create/join lobby)
│   ├── Lobby.vue           # Lobby waiting room with settings
│   └── Game.vue            # Active game screen with chat
├── components/             # Reusable Vue components
│   ├── ChatBox.vue         # Chat with public/DM support
│   ├── TurnIndicator.vue   # Turn display and controls
│   └── VoteButton.vue      # Vote now/reroll buttons
├── routes/                 # Wayfinder generated routes (auto-generated)
├── actions/                # Wayfinder generated actions (auto-generated)
├── types/                  # TypeScript type definitions
├── lib/
│   └── utils.ts            # Utility functions (cn helper)
├── app.ts                  # Frontend entry point
└── ssr.ts                  # SSR entry point

resources/css/
└── app.css                 # Tailwind CSS with custom animations

routes/
├── web.php                 # Web routes (single controller)
├── channels.php            # Broadcasting channel definitions
└── console.php             # Console commands

database/
├── migrations/             # Database migrations
└── factories/              # Model factories (Lobby, Player, Word)

tests/
├── Feature/                # Feature tests (Pest)
│   ├── GameTest.php
│   └── WordSelectionServiceTest.php
├── Unit/                   # Unit tests (Pest)
│   └── WordSelectionServiceTest.php
└── Pest.php                # Pest configuration
```

## Available Skills

The project includes specialized skills in `.claude/skills/`. **Always activate the relevant skill** when working in that domain:

| Skill | When to Activate |
|-------|------------------|
| `wayfinder-development` | Importing from `@/actions` or `@/routes`, calling Laravel routes from TypeScript |
| `inertia-vue-development` | Creating Vue pages, using `<Link>`, `<Form>`, `useForm`, or `router` |
| `tailwindcss-development` | Adding styles, working with CSS utilities, responsive design |
| `pest-testing` | Writing or modifying tests, assertions, mocking |

## Build and Development Commands

### Setup (Fresh Installation)
```bash
composer setup
```
Runs: composer install, .env copy, key generation, migrations, npm install, build

### Development
```bash
# Start all dev services (PHP server, queue worker, Vite)
composer run dev

# Or start services individually
php artisan serve           # PHP development server
php artisan queue:listen    # Queue worker
npm run dev                 # Vite dev server only
```

### Building for Production
```bash
# Build frontend assets
npm run build

# Build with SSR support
npm run build:ssr
```

### Code Quality
```bash
# PHP linting with Pint
composer lint           # Fix issues
composer test:lint      # Check only

# JavaScript/TypeScript linting
npm run lint            # ESLint with auto-fix

# Formatting
npm run format          # Prettier format
npm run format:check    # Check only
```

### Type Checking
```bash
npx vue-tsc --noEmit      # TypeScript type checking
```

## Testing Commands

```bash
# Run all tests (includes linting + Pest tests)
composer test

# Run tests only (compact output)
php artisan test --compact

# Run specific test file
php artisan test --compact tests/Feature/GameTest.php

# Run with filter
php artisan test --compact --filter=can_create_lobby

# Create new test
php artisan make:test --pest TestName
```

### Test Structure
- Uses **Pest 4** (not PHPUnit directly)
- Feature tests extend `Tests\TestCase` with `RefreshDatabase`
- Located in `tests/Feature/` and `tests/Unit/`
- Factories available for `Lobby`, `Player`, `Word`, and `User` models

## Code Style Guidelines

### PHP
- **Laravel Pint** with `laravel` preset (see `pint.json`)
- Constructor property promotion for dependencies
- Explicit return type declarations on all methods
- PHPDoc blocks for array shape type definitions
- **No `env()` calls outside config files** - use `config()` instead
- Always use curly braces for control structures
- Use Eloquent relationships over raw queries

### TypeScript/Vue
- **Prettier**: Single quotes, semicolons, 4-space tabs, 150 char width
- **ESLint**: Vue essential + TypeScript recommended + import/order
- Type imports must use `import type` syntax
- Import order: builtin → external → internal → parent → sibling → index
- Vue components must have a **single root element**
- Use `defineProps<>` for type-safe props from backend

### Tailwind CSS v4
- Use `@import 'tailwindcss'` (not `@tailwind` directives)
- CSS-first configuration via `@theme` directive
- Custom animations and utilities defined in `resources/css/app.css`
- Use `gap-*` for spacing between siblings
- Custom classes: `.glass`, `.card-hover`, `.btn-animate`, `.gradient-text`

## Key Architectural Patterns

### Backend (Laravel)

1. **Single Controller Pattern**: `GameController` handles all game logic
   - 20+ methods covering lobby, game, chat, voting, and turn management
   - Session-based player identification (`session('current_player_id')`)
   - Host validation via `validateHost()` private method

2. **Broadcasting Events**: Real-time updates via Laravel Echo
   - `GameStarted` - When host starts game
   - `PlayerJoined` / `PlayerLeft` - Lobby membership changes
   - `PlayerVoted` - Vote updates
   - `VotingEnded` - Voting phase completion
   - `SettingsUpdated` - Lobby settings changed
   - `MessageSent` - New chat message
   - `TurnAdvanced` - Turn progression
   - `VoteNowUpdated` / `RerollUpdated` - Vote thresholds
   - `WordRerolled` - Word change

3. **Word Selection Service**: `WordSelectionService`
   - Prevents word repetition using 100-round cooldown system
   - Tracks usage in `word_usage` table
   - Transaction-safe with row locking

4. **Session-Based Player Tracking**: No authentication required
   - Players identified by Laravel session ID
   - `session(['current_player_id' => $playerId, 'current_lobby_code' => $code])`

### Frontend (Vue + Inertia)

1. **Page Components**: Located in `resources/js/pages/`
   - Must have a **single root element**
   - Use `defineProps<>` for type-safe props from backend
   - Polling pattern: `window.setInterval(pollFunction, 2000)`

2. **Wayfinder Routes**: Type-safe route helpers
   ```typescript
   // Import from @/routes/ or @/actions/
   import { create, join } from '@/routes/lobby';
   import { start, state } from '@/routes/game';
   
   // Usage
   create.url();           // "/lobby/create"
   join.url('ABC123');     // "/lobby/join/ABC123"
   start.post('ABC123');   // POST to "/lobby/ABC123/start"
   ```

3. **Form Handling**: Two approaches available
   - `<Form>` component with Wayfinder: `<Form v-bind="store.form()">`
   - `useForm` composable: `const form = useForm({ ... })`

4. **Polling Pattern**: Used for game state updates
   ```typescript
   const fetchGameState = async () => {
       const response = await axios.get(state.url(props.code));
       gameState.value = response.data;
   };
   onMounted(() => {
       polling = window.setInterval(fetchGameState, 2000);
   });
   ```

### Database Models

| Model | Key Relations | Notes |
|-------|---------------|-------|
| `Lobby` | `hasMany(Player)`, `belongsTo(Word)`, `hasMany(Message)` | Settings stored as JSON, turn_order as array |
| `Player` | `belongsTo(Lobby)`, `belongsTo(Word)` | Session-based, boolean flags for host/impostor/eliminated |
| `Word` | `belongsTo(Word, 'impostor_word_id')`, `hasMany(Word, 'impostor_word_id')` | Self-referential for crew/impostor pairs |
| `Message` | `belongsTo(Lobby)`, `belongsTo(Player, 'sender_id')`, `belongsTo(Player, 'recipient_id')` | is_dm flag for private messages |

### Route Reference

| Route | Method | Controller | Description |
|-------|--------|------------|-------------|
| `/` | GET | `showWelcome` | Home page |
| `/lobby/create` | POST | `createLobby` | Create new lobby |
| `/lobby/join/{code}` | POST | `joinLobby` | Join existing lobby |
| `/lobby/{code}` | GET | `showLobby` | Lobby page |
| `/lobby/{code}/start` | POST | `startGame` | Start game (host only) |
| `/lobby/{code}/settings` | POST | `updateSettings` | Update settings (host only) |
| `/lobby/{code}/leave` | POST | `leaveLobby` | Leave lobby |
| `/game/{code}` | GET | `showGame` | Game page |
| `/game/{code}/state` | GET | `getGameState` | Get game state (polling) |
| `/game/{code}/vote` | POST | `votePlayer` | Submit vote |
| `/game/{code}/end-voting` | POST | `endVoting` | End voting phase (host) |
| `/game/{code}/message` | POST | `sendMessage` | Send chat message |
| `/game/{code}/messages` | GET | `getMessages` | Get chat messages |
| `/game/{code}/next-turn` | POST | `nextTurn` | Advance turn |
| `/game/{code}/vote-now` | POST | `voteNow` | Vote to start voting |
| `/game/{code}/vote-reroll` | POST | `voteReroll` | Vote to reroll word |

## Game Logic

### Lobby Settings (Default)
```php
[
    'impostor_count' => 1,
    'max_players' => 10,
    'discussion_time' => 60,
    'voting_time' => 30,
    'word_difficulty' => 3,
]
```

### Win Conditions
- **Crew wins**: All impostors eliminated
- **Impostor wins**: Impostor count >= Crew count

### Voting Thresholds
- **Vote Now**: 70% of active players required
- **Reroll**: 70% of active players required

### Turn System
- Turn order shuffled at game start
- Stored in `lobby.turn_order` array
- Current player ID in `lobby.current_turn_player_id`
- Host or current turn player can advance turn
- Skips eliminated players automatically

## Environment Configuration

Key environment variables (see `.env.example`):
- `APP_NAME` - Application name
- `APP_ENV` - Environment (local/production)
- `APP_URL` - Application URL
- `DB_CONNECTION` - Database (sqlite/mysql)
- `BROADCAST_CONNECTION` - Broadcasting (log/reverb/pusher)
- `CACHE_STORE` - Cache store (database)
- `QUEUE_CONNECTION` - Queue driver (database)
- `SESSION_DRIVER` - Session store (database)

## Security Considerations

1. **No Authentication**: Game uses session-based player tracking
2. **Host Validation**: Critical actions (start game, settings, end voting) validate host status via `validateHost()`
3. **Broadcasting**: Channels allow access if lobby exists and is active (customize for production)
4. **Input Validation**: All forms use Laravel validation in controller methods
5. **CSRF Protection**: Enabled via Inertia/Vite integration
6. **SQL Injection Protection**: Uses Eloquent ORM and query parameter binding

## Common Development Tasks

### Adding a New Route
1. Add route to `routes/web.php` with name
2. Add controller method to `GameController.php`
3. Wayfinder auto-generates TypeScript - import from `@/routes/` or `@/actions/`
4. Restart Vite if TypeScript imports are not recognized

### Adding a New Vue Page
1. Create component in `resources/js/pages/`
2. Must have single root element
3. Use `defineProps<>` for type safety
4. Return from controller via `Inertia::render('PageName')`

### Adding a Model
1. Run `php artisan make:model ModelName --factory --migration`
2. Define `$fillable` and `casts()` method (not `$casts` property)
3. Create factory for testing
4. Add relationships following existing patterns

### Writing Tests
1. Run `php artisan make:test --pest TestName`
2. Use factories for model creation
3. Use `expect()` assertions (Pest style)
4. Run with `php artisan test --compact`

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Vite manifest error | Run `npm run build` or `composer run dev` |
| Route not found in TypeScript | Restart Vite dev server to regenerate Wayfinder routes |
| Test database issues | Ensure `php artisan migrate` has run |
| CSS not updating | Check `resources/css/app.css` imports |
| TypeScript errors | Run `npx vue-tsc --noEmit` to check types |

## Deployment Notes

- Application served via **Laravel Herd** in development
- Run `composer setup` for fresh installation
- Build assets with `npm run build` for production
- Queue worker needed for broadcasting (if using database driver)
- Configure `BROADCAST_CONNECTION` for real-time features in production
