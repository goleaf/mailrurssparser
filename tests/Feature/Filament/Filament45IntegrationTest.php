<?php

use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\RssFeeds\Pages\CreateRssFeed;
use App\Filament\Resources\Tags\Pages\CreateTag;
use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use App\Providers\Filament\AdminPanelProvider;
use App\Services\StorageDisk;
use Filament\Actions\Action;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\Email\EmailAuthentication;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Panel;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel((new AdminPanelProvider(app()))->panel(new Panel));
    $this->actingAs(filamentAdminUser());
});

function pageField(string $pageClass, string $field): Field
{
    $fields = Livewire::test($pageClass)
        ->instance()
        ->getSchema('form')
        ->getFlatFields(withHidden: true);

    $component = $fields[$field]
        ?? collect($fields)
            ->first(fn (Field $component, string $key): bool => $key === $field || str_ends_with($key, ".{$field}"));

    expect($component)->toBeInstanceOf(Field::class);

    return $component;
}

it('enables the filament profile page and multi factor authentication providers', function () {
    $panel = Filament::getCurrentPanel();
    $providers = $panel->getMultiFactorAuthenticationProviders();

    expect($panel->hasProfile())->toBeTrue()
        ->and($panel->isProfilePageSimple())->toBeFalse()
        ->and($panel->hasMultiFactorAuthentication())->toBeTrue()
        ->and($providers)->toHaveCount(2)
        ->and($providers['app'])->toBeInstanceOf(AppAuthentication::class)
        ->and($providers['app']->isRecoverable())->toBeTrue()
        ->and($providers['email_code'])->toBeInstanceOf(EmailAuthentication::class)
        ->and(Schema::hasColumns('users', [
            'app_authentication_secret',
            'app_authentication_recovery_codes',
            'has_email_authentication',
        ]))->toBeTrue();
});

it('supports the filament mfa traits on the user model and role-based panel access', function () {
    $guardName = Filament::getCurrentPanel()->getAuthGuard();
    $superAdmin = User::factory()->create();
    $admin = User::factory()->create();
    $editor = User::factory()->create();
    $plainUser = User::factory()->create();

    Role::findOrCreate('super_admin', $guardName);
    Role::findOrCreate('admin', $guardName);
    Role::findOrCreate('editor', $guardName);

    $superAdmin->assignRole('super_admin');
    $admin->assignRole('admin');
    $editor->assignRole('editor');

    $superAdmin->saveAppAuthenticationSecret('secret-key');
    $superAdmin->saveAppAuthenticationRecoveryCodes(['code-1']);
    $superAdmin->toggleEmailAuthentication(true);

    expect($superAdmin->fresh()->getAppAuthenticationSecret())->toBe('secret-key')
        ->and($superAdmin->fresh()->getAppAuthenticationRecoveryCodes())->toBe(['code-1'])
        ->and($superAdmin->fresh()->hasEmailAuthentication())->toBeTrue()
        ->and($superAdmin->canAccessPanel(Filament::getCurrentPanel()))->toBeTrue()
        ->and($admin->canAccessPanel(Filament::getCurrentPanel()))->toBeTrue()
        ->and($editor->canAccessPanel(Filament::getCurrentPanel()))->toBeFalse()
        ->and($plainUser->canAccessPanel(Filament::getCurrentPanel()))->toBeFalse();
});

it('registers category and tag mentions for article rich content rendering', function () {
    $category = Category::factory()->create([
        'name' => 'Политика',
        'slug' => 'politics',
    ]);
    $tag = Tag::factory()->create([
        'name' => 'Эксклюзив',
        'slug' => 'exclusive',
    ]);
    $article = new Article([
        'full_description' => '<p>Body</p>',
    ]);

    $attribute = $article->getRichContentAttribute('full_description');
    $providers = $attribute?->getMentionProviders() ?? [];

    expect($attribute)->not()->toBeNull()
        ->and($providers)->toHaveCount(2)
        ->and($providers[0]->getChar())->toBe('@')
        ->and($providers[0]->getSearchResults('Пол'))->toHaveKey((string) $category->id, 'Политика')
        ->and($providers[0]->getUrl((string) $category->id, $category->name))
        ->toBe(route('category.show', ['slug' => 'politics']))
        ->and($providers[1]->getChar())->toBe('#')
        ->and($providers[1]->getSearchResults('Экск'))->toHaveKey((string) $tag->id, 'Эксклюзив')
        ->and($providers[1]->getUrl((string) $tag->id, $tag->name))
        ->toBe(route('tag.show', ['slug' => 'exclusive']));
});

it('configures the article editor with rich content and image workflow enhancements', function () {
    $titleField = pageField(CreateArticle::class, 'title');
    $richEditor = pageField(CreateArticle::class, 'full_description');
    $uploadField = pageField(CreateArticle::class, 'uploaded_image');

    expect($titleField)->toBeInstanceOf(TextInput::class)
        ->and($titleField->getChildSchema(Field::AFTER_CONTENT_SCHEMA_KEY)?->getComponents())
        ->toHaveCount(1)
        ->and($richEditor)->toBeInstanceOf(RichEditor::class)
        ->and($richEditor->hasResizableImages())->toBeTrue()
        ->and($richEditor->getFileAttachmentsDiskName())->toBe('public')
        ->and($richEditor->getFileAttachmentsMaxSize())->toBe(5120)
        ->and($richEditor->getFileAttachmentsAcceptedFileTypes())->toBe([
            'image/jpeg',
            'image/png',
            'image/webp',
        ])
        ->and(collect($richEditor->getMentionProviders())->map->getChar()->all())->toBe(['@', '#'])
        ->and($uploadField)->toBeInstanceOf(FileUpload::class)
        ->and($uploadField->getImageAspectRatio())->toBe('16:9')
        ->and($uploadField->shouldAutomaticallyOpenImageEditorForAspectRatio())->toBeTrue()
        ->and($uploadField->hasImageEditor())->toBeTrue()
        ->and($uploadField->isSaved())->toBeFalse();
});

it('stores uploaded article images on the public disk and maps them to image_url', function () {
    Storage::fake(StorageDisk::Public);

    $category = Category::factory()->create();

    Article::withoutSyncingToSearch(function () use ($category): void {
        Livewire::test(CreateArticle::class)
            ->fillForm([
                'title' => 'Uploaded image story',
                'slug' => 'uploaded-image-story',
                'category_id' => $category->id,
                'short_description' => 'Краткое описание статьи.',
                'source_name' => '',
                'content_type' => 'news',
                'importance' => 6,
                'status' => 'draft',
                'uploaded_image' => UploadedFile::fake()->image('hero.jpg', 1600, 900),
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified();
    });

    $article = Article::query()->where('slug', 'uploaded-image-story')->first();

    expect($article)->not()->toBeNull()
        ->and($article?->image_url)->toContain('/storage/article-images/');

    $storedPath = ltrim((string) str($article?->image_url ?? '')->after('/storage/'), '/');

    Storage::disk(StorageDisk::Public)->assertExists($storedPath);
});

it('uses saved(false) for read only filament helper fields', function () {
    expect(pageField(CreateArticle::class, 'rss_content')->isSaved())->toBeFalse()
        ->and(pageField(CreateTag::class, 'usage_count')->isSaved())->toBeFalse()
        ->and(pageField(CreateRssFeed::class, 'last_error')->isSaved())->toBeFalse();
});

it('adds slug generation actions to the category and tag forms', function () {
    $categoryNameField = pageField(CreateCategory::class, 'name');
    $tagNameField = pageField(CreateTag::class, 'name');

    expect($categoryNameField->getChildSchema(Field::AFTER_CONTENT_SCHEMA_KEY)?->getComponents())
        ->toHaveCount(1)
        ->and($tagNameField->getChildSchema(Field::AFTER_CONTENT_SCHEMA_KEY)?->getComponents())
        ->toHaveCount(1);
});

it('translates shared filament labels to russian', function () {
    $importanceField = Select::make('importance');
    $seoPreview = Placeholder::make('seo_preview')->label('SEO Preview');
    $sessionEntry = TextEntry::make('session_id')->label('Session ID');
    $rssColumn = TextColumn::make('last_parsed_at');
    $categoryFilter = SelectFilter::make('category');
    $featureAction = Action::make('featureArticle')->label('Отметить как featured');

    expect((string) $importanceField->getLabel())
        ->toBe('Важность')
        ->and((string) $seoPreview->getLabel())
        ->toBe('SEO-превью')
        ->and((string) $sessionEntry->getLabel())
        ->toBe('Идентификатор сессии')
        ->and((string) $rssColumn->getLabel())
        ->toBe('Последний запуск')
        ->and((string) $categoryFilter->getLabel())
        ->toBe('Рубрика')
        ->and((string) $featureAction->getLabel())
        ->toBe('Пометить как рекомендуемое');
});
