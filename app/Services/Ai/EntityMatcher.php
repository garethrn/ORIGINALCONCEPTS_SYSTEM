<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\Client;
use App\Models\Product;
use Illuminate\Support\Collection;

final class MatchResult
{
    /**
     * @param  Collection<int, Client|Product>  $matches
     */
    public function __construct(private readonly Collection $matches) {}

    public function isEmpty(): bool
    {
        return $this->matches->isEmpty();
    }

    public function isOne(): bool
    {
        return $this->matches->count() === 1;
    }

    public function isAmbiguous(): bool
    {
        return $this->matches->count() > 1;
    }

    public function one(): Client|Product
    {
        return $this->matches->first();
    }

    public function choices(): string
    {
        return $this->matches->map(function (Client|Product $record): string {
            if ($record instanceof Client) {
                $extra = $record->city ? ' — '.$record->city : '';

                return '- '.$record->displayName().$extra;
            }

            return '- '.$record->name.' ('.$record->sku.')';
        })->implode("\n");
    }
}

final class EntityMatcher
{
    public function clients(string $query): MatchResult
    {
        return new MatchResult($this->rank(
            Client::query()->where('is_active', true)->get(),
            $query,
            fn (Client $client) => [$client->name, (string) $client->trading_name],
        ));
    }

    public function products(string $query): MatchResult
    {
        return new MatchResult($this->rank(
            Product::query()->where('is_active', true)->get(),
            $query,
            fn (Product $product) => [$product->name, $product->sku],
        ));
    }

    /**
     * @template T of Client|Product
     *
     * @param  Collection<int, T>  $records
     * @param  callable(T): array<int, string>  $names
     * @return Collection<int, T>
     */
    private function rank(Collection $records, string $query, callable $names): Collection
    {
        $needle = mb_strtolower(trim($query));
        if ($needle === '') {
            return collect();
        }

        $exact = $records->filter(function ($record) use ($names, $needle) {
            foreach ($names($record) as $name) {
                if ($name !== '' && mb_strtolower($name) === $needle) {
                    return true;
                }
            }

            return false;
        })->values();

        if ($exact->count() === 1) {
            return $exact;
        }
        if ($exact->count() > 1) {
            return $exact->take(8);
        }

        $contains = $records->filter(function ($record) use ($names, $needle) {
            foreach ($names($record) as $name) {
                if ($name !== '' && str_contains(mb_strtolower($name), $needle)) {
                    return true;
                }
            }

            return false;
        })->values();

        return $contains->take(8);
    }
}
