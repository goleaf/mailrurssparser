<?php

namespace App\Filament\Pages;

use App\Models\RssFeed;
use App\Models\RssParseLog;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\WithPagination;
use UnitEnum;

class ParseHistory extends Page
{
    use WithPagination;

    protected static ?string $navigationLabel = 'История парсинга';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|UnitEnum|null $navigationGroup = 'Контент';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.parse-history';

    public string $feed = '';

    public string $status = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    /**
     * @var array<int, bool>
     */
    public array $expandedLogs = [];

    public function updatedFeed(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function toggleExpanded(int $logId): void
    {
        if (isset($this->expandedLogs[$logId])) {
            unset($this->expandedLogs[$logId]);

            return;
        }

        $this->expandedLogs[$logId] = true;
    }

    /**
     * @return array<int, string>
     */
    public function getFeedOptionsProperty(): array
    {
        return RssFeed::query()
            ->orderBy('title')
            ->pluck('title', 'id')
            ->all();
    }

    /**
     * @return array{runs_today: int, average_duration_ms: int, total_new_today: int, error_rate: float}
     */
    public function getSummaryProperty(): array
    {
        $todayLogs = RssParseLog::query()->whereDate('started_at', today());
        $runsToday = (clone $todayLogs)->count();
        $failedRuns = (clone $todayLogs)->where('success', false)->count();

        return [
            'runs_today' => $runsToday,
            'average_duration_ms' => (int) round((float) ((clone $todayLogs)->avg('duration_ms') ?? 0)),
            'total_new_today' => (int) ((clone $todayLogs)->sum('new_count') ?? 0),
            'error_rate' => $runsToday > 0 ? round(($failedRuns / $runsToday) * 100, 1) : 0.0,
        ];
    }

    public function getLogsProperty(): LengthAwarePaginator
    {
        return RssParseLog::query()
            ->with('rssFeed')
            ->when($this->feed !== '', fn (Builder $query): Builder => $query->where('rss_feed_id', (int) $this->feed))
            ->when($this->status !== '', fn (Builder $query): Builder => $query->where('success', $this->status === 'success'))
            ->when($this->dateFrom !== '', fn (Builder $query): Builder => $query->whereDate('started_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (Builder $query): Builder => $query->whereDate('started_at', '<=', $this->dateTo))
            ->latest('started_at')
            ->paginate(15);
    }
}
