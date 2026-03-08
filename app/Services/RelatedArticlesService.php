<?php

namespace App\Services;

use App\Models\Article;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RelatedArticlesService
{
    /**
     * @var list<string>
     */
    private const STOP_WORDS = [
        'a',
        'an',
        'and',
        'are',
        'as',
        'at',
        'be',
        'but',
        'by',
        'for',
        'from',
        'has',
        'have',
        'in',
        'is',
        'it',
        'its',
        'of',
        'on',
        'or',
        'that',
        'the',
        'their',
        'this',
        'to',
        'was',
        'were',
        'will',
        'with',
        'без',
        'был',
        'была',
        'были',
        'быть',
        'для',
        'его',
        'ее',
        'или',
        'как',
        'когда',
        'кто',
        'на',
        'над',
        'не',
        'но',
        'о',
        'об',
        'от',
        'по',
        'под',
        'при',
        'про',
        'что',
        'это',
        'этот',
        'эта',
        'эти',
        'из',
        'за',
        'до',
        'после',
        'у',
    ];

    private const INDEX_LIMIT = 18;

    private const CANDIDATE_LIMIT = 80;

    public function getRelated(Article $article, int $limit = 6): Collection
    {
        if (! $this->isEligible($article)) {
            return collect();
        }

        if ($this->needsRefresh($article)) {
            $this->rebuildForArticle($article);
        }

        return $article->relatedArticles()
            ->published()
            ->with(['category', 'subCategory', 'tags'])
            ->orderByDesc('article_related_articles.score')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  list<int>  $excludeIds
     */
    public function getSimilar(Article $article, int $limit = 5, array $excludeIds = []): Collection
    {
        if (! $this->isEligible($article)) {
            return collect();
        }

        if ($this->needsRefresh($article)) {
            $this->rebuildForArticle($article);
        }

        return $article->relatedArticles()
            ->published()
            ->with(['category', 'subCategory', 'tags'])
            ->when($excludeIds !== [], fn (Builder $query) => $query->whereNotIn('articles.id', $excludeIds))
            ->where(function (Builder $query): void {
                $query
                    ->where('article_related_articles.shared_tags_count', '>', 0)
                    ->orWhere('article_related_articles.shared_terms_count', '>', 1)
                    ->orWhere('article_related_articles.same_author', true)
                    ->orWhere('article_related_articles.same_source', true);
            })
            ->orderByDesc('article_related_articles.score')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  list<int>  $excludeIds
     */
    public function getMoreFromCategory(Article $article, int $limit = 3, array $excludeIds = []): Collection
    {
        if ($article->category_id === null) {
            return collect();
        }

        return Article::query()
            ->published()
            ->with(['category', 'tags'])
            ->where('category_id', $article->category_id)
            ->whereKeyNot($article->getKey())
            ->when($excludeIds !== [], fn (Builder $query) => $query->whereNotIn('id', $excludeIds))
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();
    }

    public function forgetForArticle(Article $article): void
    {
        DB::table('article_related_articles')
            ->where('article_id', $article->getKey())
            ->orWhere('related_article_id', $article->getKey())
            ->delete();
    }

    public function rebuildForArticle(Article $article, int $limit = self::INDEX_LIMIT): void
    {
        $this->forgetForArticle($article);

        if (! $this->isEligible($article)) {
            return;
        }

        $sourceTagIds = $article->tags()->pluck('tags.id');
        $sourceTerms = $this->extractTerms(
            trim(
                implode(' ', array_filter([
                    $article->title,
                    $article->short_description,
                    $article->full_description,
                    $article->source_name,
                    $article->author,
                    $article->tags()->pluck('name')->implode(' '),
                ])),
            ),
        );

        $rows = $this->buildCandidateQuery($article, $sourceTagIds)
            ->get()
            ->map(function (Article $candidate) use ($article, $sourceTagIds, $sourceTerms): ?array {
                return $this->scoreCandidate($article, $candidate, $sourceTagIds, $sourceTerms);
            })
            ->filter()
            ->sortByDesc('score')
            ->take($limit)
            ->values()
            ->map(function (array $row): array {
                return [
                    ...$row,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })
            ->all();

        if ($rows === []) {
            return;
        }

        DB::table('article_related_articles')->insert($rows);
    }

    private function buildCandidateQuery(Article $article, Collection $sourceTagIds): Builder
    {
        $query = Article::query()
            ->published()
            ->whereKeyNot($article->getKey())
            ->with('tags:id,name')
            ->select([
                'articles.id',
                'articles.category_id',
                'articles.sub_category_id',
                'articles.title',
                'articles.short_description',
                'articles.full_description',
                'articles.author',
                'articles.source_name',
                'articles.content_type',
                'articles.importance',
                'articles.views_count',
                'articles.shares_count',
                'articles.bookmarks_count',
                'articles.published_at',
            ])
            ->orderByDesc('articles.published_at')
            ->limit(self::CANDIDATE_LIMIT);

        $hasSignal = false;

        $query->where(function (Builder $query) use ($article, $sourceTagIds, &$hasSignal): void {
            if ($article->category_id !== null) {
                $query->where('articles.category_id', $article->category_id);
                $hasSignal = true;
            }

            if ($article->sub_category_id !== null) {
                $query->orWhere('articles.sub_category_id', $article->sub_category_id);
                $hasSignal = true;
            }

            if ($article->content_type instanceof ArticleContentType) {
                $query->orWhere('articles.content_type', $article->content_type->value);
                $hasSignal = true;
            }

            if ($article->author !== null && $article->author !== '') {
                $query->orWhere('articles.author', $article->author);
                $hasSignal = true;
            }

            if ($article->source_name !== null && $article->source_name !== '') {
                $query->orWhere('articles.source_name', $article->source_name);
                $hasSignal = true;
            }

            if ($sourceTagIds->isNotEmpty()) {
                $query->orWhereHas('tags', function (Builder $query) use ($sourceTagIds): void {
                    $query->whereIn('tags.id', $sourceTagIds);
                });
                $hasSignal = true;
            }
        });

        return $hasSignal
            ? $query
            : $query->where('articles.category_id', $article->category_id);
    }

    /**
     * @param  array<int, string>  $sourceTerms
     * @return array<string, int|bool>|null
     */
    private function scoreCandidate(
        Article $article,
        Article $candidate,
        Collection $sourceTagIds,
        array $sourceTerms,
    ): ?array {
        $candidateTagIds = $candidate->tags->modelKeys();
        $candidateTerms = $this->extractTerms(
            trim(
                implode(' ', array_filter([
                    $candidate->title,
                    $candidate->short_description,
                    $candidate->full_description,
                    $candidate->source_name,
                    $candidate->author,
                    $candidate->tags->pluck('name')->implode(' '),
                ])),
            ),
        );

        $sharedTagsCount = count(array_intersect($sourceTagIds->all(), $candidateTagIds));
        $sharedTermsCount = count(array_intersect($sourceTerms, $candidateTerms));
        $sameCategory = $article->category_id !== null && $candidate->category_id === $article->category_id;
        $sameSubCategory = $article->sub_category_id !== null && $candidate->sub_category_id === $article->sub_category_id;
        $sameContentType = $article->content_type instanceof ArticleContentType
            && $candidate->content_type === $article->content_type;
        $sameAuthor = $article->author !== null
            && $article->author !== ''
            && $candidate->author === $article->author;
        $sameSource = $article->source_name !== null
            && $article->source_name !== ''
            && $candidate->source_name === $article->source_name;

        $score = 0;
        $score += $sameCategory ? 34 : 0;
        $score += $sameSubCategory ? 12 : 0;
        $score += $sameContentType ? 6 : 0;
        $score += $sameAuthor ? 5 : 0;
        $score += $sameSource ? 4 : 0;
        $score += $sharedTagsCount * 16;
        $score += min(5, $sharedTermsCount) * 7;
        $score += $this->recencyBonus($candidate);
        $score += $this->engagementBonus($candidate);
        $score += $this->importanceBonus($candidate);

        if ($score < 18) {
            return null;
        }

        return [
            'article_id' => $article->getKey(),
            'related_article_id' => $candidate->getKey(),
            'score' => $score,
            'shared_tags_count' => $sharedTagsCount,
            'shared_terms_count' => $sharedTermsCount,
            'same_category' => $sameCategory,
            'same_sub_category' => $sameSubCategory,
            'same_content_type' => $sameContentType,
            'same_author' => $sameAuthor,
            'same_source' => $sameSource,
        ];
    }

    private function recencyBonus(Article $candidate): int
    {
        if ($candidate->published_at === null) {
            return 0;
        }

        if ($candidate->published_at->greaterThanOrEqualTo(now()->minus(days: 1))) {
            return 10;
        }

        if ($candidate->published_at->greaterThanOrEqualTo(now()->minus(days: 3))) {
            return 8;
        }

        if ($candidate->published_at->greaterThanOrEqualTo(now()->minus(days: 7))) {
            return 6;
        }

        if ($candidate->published_at->greaterThanOrEqualTo(now()->minus(days: 30))) {
            return 4;
        }

        return 2;
    }

    private function engagementBonus(Article $candidate): int
    {
        $engagement = max(
            0,
            (int) ($candidate->views_count ?? 0)
            + ((int) ($candidate->shares_count ?? 0) * 6)
            + ((int) ($candidate->bookmarks_count ?? 0) * 8),
        );

        if ($engagement === 0) {
            return 0;
        }

        return min(8, (int) floor(log($engagement + 1, 10) * 3));
    }

    private function importanceBonus(Article $candidate): int
    {
        return min(5, max(0, (int) floor(((int) ($candidate->importance ?? 0)) / 2)));
    }

    /**
     * @return array<int, string>
     */
    private function extractTerms(string $content): array
    {
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($content)) ?: [];

        return collect($tokens)
            ->filter(fn (string $token): bool => mb_strlen($token) >= 3)
            ->reject(fn (string $token): bool => in_array($token, self::STOP_WORDS, true))
            ->unique()
            ->values()
            ->all();
    }

    private function needsRefresh(Article $article): bool
    {
        $indexedAt = DB::table('article_related_articles')
            ->where('article_id', $article->getKey())
            ->max('updated_at');

        if ($indexedAt === null) {
            return true;
        }

        $latestIndexedAt = CarbonImmutable::parse($indexedAt);

        if ($article->updated_at !== null && $article->updated_at->greaterThan($latestIndexedAt)) {
            return true;
        }

        $latestTagSyncAt = DB::table('article_tag')
            ->where('article_id', $article->getKey())
            ->max('updated_at');

        return $latestTagSyncAt !== null
            && CarbonImmutable::parse($latestTagSyncAt)->greaterThan($latestIndexedAt);
    }

    private function isEligible(Article $article): bool
    {
        return $article->status === ArticleStatus::Published
            && $article->published_at !== null
            && $article->published_at->isPast();
    }
}
