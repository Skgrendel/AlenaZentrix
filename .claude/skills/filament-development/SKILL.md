---
name: filament-development
description: "Use this skill for Filament PHP 5.x development. Trigger when working with Filament resources, pages, widgets, forms, tables, actions, infolists, or the admin panel. Covers: Resources (CRUD), custom pages, dashboard widgets, form components, table columns/filters/actions, notifications, modals, panels, tenancy, and plugins. Do not use for non-Filament admin panels, plain Blade/Livewire without Filament, or frontend-only tasks."
license: MIT
metadata:
  author: custom
---

# Filament PHP 5.x Development

## Documentation

Use `search-docs` if available. Otherwise refer to https://filamentphp.com/docs.

## Artisan Commands

```bash
# Panel
php artisan make:filament-panel admin

# Resource (CRUD)
php artisan make:filament-resource Post
php artisan make:filament-resource Post --generate          # auto-generate form & table from model
php artisan make:filament-resource Post --simple            # modal resource (no dedicated pages)
php artisan make:filament-resource Post --soft-deletes
php artisan make:filament-resource Post --view              # adds ViewRecord page

# Pages
php artisan make:filament-page Settings
php artisan make:filament-page Settings --resource=PostResource

# Widgets
php artisan make:filament-widget StatsOverview --stats-overview
php artisan make:filament-widget LatestOrders --table
php artisan make:filament-widget RevenueChart --chart

# Other
php artisan make:filament-action BanUser
php artisan make:filament-form-field RangeSlider
php artisan make:filament-column ProgressBar
php artisan make:filament-infolist-entry AuditTrail
php artisan make:filament-notification WelcomeNotification
php artisan make:filament-relation-manager PostResource tags TagResource
```

## Panel Setup

```php
// app/Providers/Filament/AdminPanelProvider.php
public function panel(Panel $panel): Panel
{
    return $panel
        ->default()
        ->id('admin')
        ->path('admin')
        ->login()
        ->colors(['primary' => Color::Amber])
        ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
        ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
        ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
        ->middleware([...])
        ->authMiddleware([Authenticate::class]);
}
```

## Resources

### Structure

```php
class PostResource extends Resource
{
    protected static ?string $model = Post::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Content';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form { ... }
    public static function table(Table $table): Table { ... }
    public static function getRelations(): array { return []; }
    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPosts::route('/'),
            'create' => Pages\CreatePost::route('/create'),
            'edit'   => Pages\EditPost::route('/{record}/edit'),
        ];
    }
}
```

### Eloquent Queries

```php
// Override base query (e.g., for tenancy or soft deletes)
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
}
```

## Forms

```php
public static function form(Form $form): Form
{
    return $form->schema([
        Forms\Components\TextInput::make('title')
            ->required()
            ->maxLength(255),

        Forms\Components\Select::make('category_id')
            ->relationship('category', 'name')
            ->searchable()
            ->preload()
            ->required(),

        Forms\Components\RichEditor::make('content')
            ->columnSpanFull(),

        Forms\Components\Toggle::make('is_published')
            ->default(false),

        Forms\Components\DateTimePicker::make('published_at'),

        Forms\Components\FileUpload::make('cover_image')
            ->image()
            ->disk('public'),

        Forms\Components\Repeater::make('tags')
            ->relationship()
            ->schema([
                Forms\Components\TextInput::make('name')->required(),
            ]),

        Forms\Components\Section::make('SEO')
            ->collapsed()
            ->schema([
                Forms\Components\TextInput::make('meta_title'),
                Forms\Components\Textarea::make('meta_description'),
            ]),
    ]);
}
```

### Layout Components

| Component | Purpose |
|-----------|---------|
| `Section` | Grouped fields with heading |
| `Grid` | Multi-column layout |
| `Fieldset` | HTML fieldset wrapper |
| `Tabs` | Tabbed form sections |
| `Wizard` | Multi-step form |
| `Split` | Side-by-side layout |

## Tables

```php
public static function table(Table $table): Table
{
    return $table
        ->columns([
            Tables\Columns\TextColumn::make('title')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('category.name')->badge(),
            Tables\Columns\IconColumn::make('is_published')->boolean(),
            Tables\Columns\TextColumn::make('published_at')->dateTime()->sortable(),
            Tables\Columns\ImageColumn::make('cover_image'),
        ])
        ->filters([
            Tables\Filters\SelectFilter::make('category')->relationship('category', 'name'),
            Tables\Filters\TernaryFilter::make('is_published'),
            Tables\Filters\TrashedFilter::make(),
        ])
        ->actions([
            Tables\Actions\ViewAction::make(),
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ])
        ->bulkActions([
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
                Tables\Actions\ForceDeleteBulkAction::make(),
                Tables\Actions\RestoreBulkAction::make(),
            ]),
        ])
        ->defaultSort('created_at', 'desc');
}
```

## Actions

```php
// Inline table action with form
Tables\Actions\Action::make('approve')
    ->icon('heroicon-o-check')
    ->color('success')
    ->requiresConfirmation()
    ->action(fn (Post $record) => $record->approve()),

// Action with modal form
Tables\Actions\Action::make('sendNotification')
    ->form([
        Forms\Components\Textarea::make('message')->required(),
    ])
    ->action(function (array $data, Post $record): void {
        $record->notify(new CustomNotification($data['message']));
    }),
```

## Widgets

### Stats Overview

```php
class StatsOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Posts', Post::count()),
            Stat::make('Published', Post::published()->count())
                ->description('Active posts')->color('success'),
            Stat::make('Draft', Post::draft()->count())
                ->chart([7, 3, 4, 5, 6, 3, 5])
                ->color('warning'),
        ];
    }
}
```

### Table Widget

```php
class LatestPostsWidget extends BaseWidget
{
    protected function getTableQuery(): Builder
    {
        return Post::latest()->limit(5);
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('title'),
            TextColumn::make('created_at')->dateTime(),
        ];
    }
}
```

## Infolists (View Pages)

```php
public static function infolist(Infolist $infolist): Infolist
{
    return $infolist->schema([
        Infolists\Components\TextEntry::make('title'),
        Infolists\Components\TextEntry::make('category.name')->badge(),
        Infolists\Components\IconEntry::make('is_published')->boolean(),
        Infolists\Components\ImageEntry::make('cover_image'),
        Infolists\Components\Section::make('Content')
            ->schema([
                Infolists\Components\TextEntry::make('content')->html(),
            ]),
    ]);
}
```

## Relation Managers

```php
class TagsRelationManager extends RelationManager
{
    protected static string $relationship = 'tags';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([TextColumn::make('name')])
            ->headerActions([Tables\Actions\CreateAction::make()])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DetachAction::make()])
            ->bulkActions([Tables\Actions\DetachBulkAction::make()]);
    }
}
```

## Notifications

```php
use Filament\Notifications\Notification;

// Success
Notification::make()->title('Saved')->success()->send();

// With body and actions
Notification::make()
    ->title('New order received')
    ->body('Order #1234 needs review.')
    ->actions([
        \Filament\Notifications\Actions\Action::make('view')
            ->button()
            ->url(route('filament.admin.resources.orders.edit', 1)),
    ])
    ->sendToDatabase(auth()->user());
```

## Authorization

```php
// In Resource
public static function canCreate(): bool { return auth()->user()->can('create', Post::class); }
public static function canEdit(Model $record): bool { return auth()->user()->can('update', $record); }
public static function canDelete(Model $record): bool { return auth()->user()->can('delete', $record); }

// Or use Laravel policies — Filament auto-discovers them
```

## Global Search

```php
protected static ?string $recordTitleAttribute = 'title';

public static function getGloballySearchableAttributes(): array
{
    return ['title', 'content', 'category.name'];
}

public static function getGlobalSearchResultDetails(Model $record): array
{
    return ['Category' => $record->category->name];
}
```

## Testing

```php
use Filament\Tests\TestCase;
use function Pest\Livewire\livewire;

it('can list posts', function () {
    $posts = Post::factory(5)->create();

    livewire(ListPosts::class)
        ->assertCanSeeTableRecords($posts);
});

it('can create a post', function () {
    livewire(CreatePost::class)
        ->fillForm(['title' => 'Test Post', 'content' => 'Hello'])
        ->call('create')
        ->assertHasNoFormErrors();

    assertDatabaseHas('posts', ['title' => 'Test Post']);
});

it('can edit a post', function () {
    $post = Post::factory()->create();

    livewire(EditPost::class, ['record' => $post->getRouteKey()])
        ->fillForm(['title' => 'Updated'])
        ->call('save')
        ->assertHasNoFormErrors();
});
```

## Common Pitfalls

- Forgetting `->preload()` on large `Select` relationships — causes slow loads
- Using `columnSpanFull()` outside a `Grid` or `Section` with `columns()` set
- Not adding `SoftDeletingScope` to `getEloquentQuery()` when using soft deletes
- Registering widgets in `getWidgets()` on the page instead of the panel provider
- Missing `->relationship()` on `FileUpload` or `Select` when a relation is expected
- Not running `php artisan icons:cache` after installing a new icon set
