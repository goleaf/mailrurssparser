<?php

namespace App\Filament\Resources\NewsletterSubscribers\Tables;

use App\Filament\Resources\NewsletterSubscribers\NewsletterSubscriberResource;
use App\Models\Category;
use App\Models\NewsletterSubscriber;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NewsletterSubscribersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(['email', 'name', 'ip_address'])
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Имя')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('categories_summary')
                    ->label('Интересы')
                    ->state(function (NewsletterSubscriber $record): string {
                        static $categories = null;
                        $categories ??= Category::query()->orderBy('name')->pluck('name', 'id')->all();

                        return collect($record->preferredCategoryIds())
                            ->map(fn (int $categoryId): ?string => $categories[$categoryId] ?? null)
                            ->filter()
                            ->implode(', ');
                    })
                    ->placeholder('Все рубрики')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $categoryIds = Category::query()
                            ->where('name', 'like', '%'.$search.'%')
                            ->pluck('id');

                        if ($categoryIds->isEmpty()) {
                            return $query->whereRaw('0 = 1');
                        }

                        return $query->where(function (Builder $query) use ($categoryIds): void {
                            foreach ($categoryIds as $categoryId) {
                                $query->orWhereJsonContains('category_ids', (int) $categoryId);
                            }
                        });
                    })
                    ->sortable(['category_ids'])
                    ->toggleable(),
                IconColumn::make('confirmed')
                    ->boolean()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('confirmed_at')
                    ->label('Подтверждён')
                    ->dateTime()
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('unsubscribed_at')
                    ->label('Отписка')
                    ->dateTime()
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('country_code')
                    ->label('Страна')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('timezone')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('locale')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Рубрика')
                    ->options(fn (): array => Category::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }

                        return $query->whereJsonContains('category_ids', (int) $data['value']);
                    }),
                SelectFilter::make('locale')
                    ->options(fn (): array => NewsletterSubscriber::query()
                        ->whereNotNull('locale')
                        ->orderBy('locale')
                        ->pluck('locale', 'locale')
                        ->all()),
                TernaryFilter::make('confirmed')
                    ->label('Подтверждён'),
                TernaryFilter::make('unsubscribed')
                    ->label('Отписан')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('unsubscribed_at'),
                        false: fn (Builder $query): Builder => $query->whereNull('unsubscribed_at'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->recordActions([
                Action::make('viewRecord')
                    ->label('Просмотр')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (NewsletterSubscriber $record): string => NewsletterSubscriberResource::getUrl('view', ['record' => $record])),
                Action::make('editRecord')
                    ->label('Открыть')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn (NewsletterSubscriber $record): string => NewsletterSubscriberResource::getUrl('edit', ['record' => $record])),
            ])
            ->toolbarActions([]);
    }
}
