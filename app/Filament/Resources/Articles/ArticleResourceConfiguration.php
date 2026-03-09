<?php

namespace App\Filament\Resources\Articles;

use Filament\Resources\ResourceConfiguration;
use UnitEnum;

class ArticleResourceConfiguration extends ResourceConfiguration
{
    protected ?string $status = null;

    protected ?string $navigationLabel = null;

    protected string|UnitEnum|null $navigationGroup = null;

    protected ?int $navigationSort = null;

    protected ?string $pluralModelLabel = null;

    public function status(?string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function navigationLabel(?string $label): static
    {
        $this->navigationLabel = $label;

        return $this;
    }

    public function getNavigationLabel(): ?string
    {
        return $this->navigationLabel;
    }

    public function navigationGroup(string|UnitEnum|null $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function getNavigationGroup(): string|UnitEnum|null
    {
        return $this->navigationGroup;
    }

    public function navigationSort(?int $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function getNavigationSort(): ?int
    {
        return $this->navigationSort;
    }

    public function pluralModelLabel(?string $label): static
    {
        $this->pluralModelLabel = $label;

        return $this;
    }

    public function getPluralModelLabel(): ?string
    {
        return $this->pluralModelLabel;
    }
}
