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
use Awcodes\Curator\Components\Forms\CuratorPicker;
use Filament\Actions\Action;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\Email\EmailAuthentication;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
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
    $featuredImageField = pageField(CreateArticle::class, 'featured_image');
    $curatorPicker = pageField(CreateArticle::class, 'curator_media_id');

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
        ->and($uploadField->isSaved())->toBeFalse()
        ->and($featuredImageField)->toBeInstanceOf(SpatieMediaLibraryFileUpload::class)
        ->and($featuredImageField->getCollection())->toBe('featured_image')
        ->and($featuredImageField->getDiskName())->toBe(StorageDisk::Public->value)
        ->and($featuredImageField->getVisibility())->toBe('public')
        ->and($featuredImageField->getConversion())->toBe('card')
        ->and($featuredImageField->hasResponsiveImages())->toBeTrue()
        ->and($featuredImageField->getAcceptedFileTypes())->toBe([
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
        ])
        ->and($curatorPicker)->toBeInstanceOf(CuratorPicker::class)
        ->and($curatorPicker->getRelationshipName())->toBe('curatorMedia')
        ->and($curatorPicker->isConstrained())->toBeTrue()
        ->and($curatorPicker->getDiskName())->toBe(StorageDisk::Public->value)
        ->and($curatorPicker->getDirectory())->toBe('curator')
        ->and($curatorPicker->getVisibility())->toBe('public')
        ->and($curatorPicker->getMaxSize())->toBe(10240)
        ->and($curatorPicker->getAcceptedFileTypes())->toBe([
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
            'image/svg+xml',
            'image/x-icon',
            'image/vnd.microsoft.icon',
        ]);
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

it('configures curator pickers for category and rss feed media fields', function () {
    $categoryMediaField = pageField(CreateCategory::class, 'cover_image');
    $categoryPicker = pageField(CreateCategory::class, 'curator_cover_id');
    $feedMediaField = pageField(CreateRssFeed::class, 'logo');
    $feedPicker = pageField(CreateRssFeed::class, 'curator_logo_id');

    expect($categoryMediaField)->toBeInstanceOf(SpatieMediaLibraryFileUpload::class)
        ->and($categoryMediaField->getCollection())->toBe('cover_image')
        ->and($categoryMediaField->getDiskName())->toBe(StorageDisk::Public->value)
        ->and($categoryMediaField->getVisibility())->toBe('public')
        ->and($categoryMediaField->getConversion())->toBe('banner')
        ->and($categoryMediaField->hasResponsiveImages())->toBeTrue()
        ->and($categoryPicker)->toBeInstanceOf(CuratorPicker::class)
        ->and($categoryPicker->getRelationshipName())->toBe('coverImage')
        ->and($categoryPicker->isConstrained())->toBeTrue()
        ->and($categoryPicker->getDiskName())->toBe(StorageDisk::Public->value)
        ->and($categoryPicker->getDirectory())->toBe('curator')
        ->and($categoryPicker->getMaxSize())->toBe(10240)
        ->and($feedMediaField)->toBeInstanceOf(SpatieMediaLibraryFileUpload::class)
        ->and($feedMediaField->getCollection())->toBe('logo')
        ->and($feedMediaField->getDiskName())->toBe(StorageDisk::Public->value)
        ->and($feedMediaField->getVisibility())->toBe('public')
        ->and($feedMediaField->getConversion())->toBe('icon')
        ->and($feedPicker)->toBeInstanceOf(CuratorPicker::class)
        ->and($feedPicker->getRelationshipName())->toBe('logoMedia')
        ->and($feedPicker->isConstrained())->toBeTrue()
        ->and($feedPicker->getDiskName())->toBe(StorageDisk::Public->value)
        ->and($feedPicker->getDirectory())->toBe('curator')
        ->and($feedPicker->getMaxSize())->toBe(10240);
});

it('keeps curator storage defaults aligned with the public filesystem disk', function () {
    $publicDisk = config('filesystems.disks.public');

    expect($publicDisk['driver'])->toBe('local')
        ->and($publicDisk['root'])->toBe(storage_path('app/public'))
        ->and($publicDisk['url'])->toBe(rtrim((string) env('APP_URL', 'http://localhost'), '/').'/storage')
        ->and($publicDisk['visibility'])->toBe('public')
        ->and($publicDisk['throw'])->toBeFalse()
        ->and(config('curator.default_disk'))->toBe(StorageDisk::Public->value)
        ->and(config('curator.default_directory'))->toBe('curator')
        ->and(config('curator.resource.label'))->toBe('Media')
        ->and(config('curator.resource.plural_label'))->toBe('Media Library')
        ->and(config('curator.resource.navigation.group'))->toBe('Media')
        ->and(config('curator.resource.navigation.sort'))->toBe(10)
        ->and(config('curator.resource.navigation.should_show_badge'))->toBeTrue()
        ->and(config('media-library.disk_name'))->toBe(StorageDisk::Public->value)
        ->and(config('media-library.max_file_size'))->toBe(1024 * 1024 * 10)
        ->and(config('media-library.queue_connection_name'))->toBe(config('queue.default'))
        ->and(config('media-library.queue_conversions_by_default'))->toBeTrue()
        ->and(config('media-library.responsive_images.width_calculator'))->toBe(App\Support\MediaLibrary\NewsPortalWidthCalculator::class)
        ->and(Schema::hasTable('curator'))->toBeTrue()
        ->and(Schema::hasTable('media'))->toBeTrue()
        ->and(Schema::hasColumns('articles', ['curator_media_id']))->toBeTrue()
        ->and(Schema::hasColumns('categories', ['curator_cover_id']))->toBeTrue()
        ->and(Schema::hasColumns('rss_feeds', ['curator_logo_id']))->toBeTrue();
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
