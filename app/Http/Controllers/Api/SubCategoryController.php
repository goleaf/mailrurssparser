<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreSubCategoryRequest;
use App\Http\Requests\Api\SubCategoryIndexRequest;
use App\Http\Requests\Api\UpdateSubCategoryRequest;
use App\Http\Resources\SubCategoryResource as SubCategoryApiResource;
use App\Models\SubCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class SubCategoryController extends Controller
{
    public function index(SubCategoryIndexRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();
        $query = $this->baseQuery()
            ->when(
                $validated['category'] ?? null,
                fn (Builder $query, string $slug): Builder => $query->whereHas(
                    'category',
                    fn (Builder $query): Builder => $query->where('slug', $slug),
                ),
            )
            ->when(
                $validated['category_id'] ?? null,
                fn (Builder $query, int $categoryId): Builder => $query->where('category_id', $categoryId),
            )
            ->orderBy('order')
            ->orderBy('name');

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $subCategories = $query
            ->paginate((int) ($validated['per_page'] ?? 20))
            ->appends($request->except('page'));

        return SubCategoryApiResource::collection($subCategories);
    }

    public function store(StoreSubCategoryRequest $request): Response
    {
        $this->authorize('create', SubCategory::class);

        $subCategory = SubCategory::query()->create($request->validated());
        $subCategory->load('category')->loadCount([
            'articles' => fn (Builder $query): Builder => $query->published(),
        ]);

        return SubCategoryApiResource::make($subCategory)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(string $identifier): Response
    {
        return SubCategoryApiResource::make(
            $this->resolveSubCategory($identifier),
        )->response();
    }

    public function update(UpdateSubCategoryRequest $request, string $identifier): Response
    {
        $subCategory = $this->resolveSubCategory($identifier);

        $this->authorize('update', $subCategory);

        $subCategory->update($request->validated());
        $subCategory->load('category')->loadCount([
            'articles' => fn (Builder $query): Builder => $query->published(),
        ]);

        return SubCategoryApiResource::make($subCategory)->response();
    }

    public function destroy(string $identifier): Response
    {
        $subCategory = $this->resolveSubCategory($identifier);

        $this->authorize('delete', $subCategory);

        $subCategory->delete();

        return response()->noContent();
    }

    private function baseQuery(): Builder
    {
        return SubCategory::query()
            ->with('category')
            ->withCount([
                'articles' => fn (Builder $query): Builder => $query->published(),
            ]);
    }

    private function resolveSubCategory(string $identifier): SubCategory
    {
        return $this->baseQuery()
            ->where(function (Builder $query) use ($identifier): void {
                $query->where('slug', $identifier);

                if (ctype_digit($identifier)) {
                    $query->orWhere(
                        (new SubCategory)->getQualifiedKeyName(),
                        (int) $identifier,
                    );
                }
            })
            ->firstOrFail();
    }
}
