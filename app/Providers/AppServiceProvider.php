<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Tanggal & hari ditampilkan dalam Bahasa Indonesia
        \Carbon\Carbon::setLocale('id');

        // Pencarian teks tidak peka huruf besar/kecil di semua database (SQLite, MySQL, Postgres)
        $ci = function (string $column, string $pattern, string $boolean) {
            /** @var \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $this */
            $wrapped = $this->getGrammar()->wrap($column);
            return $this->whereRaw("LOWER({$wrapped}) LIKE ? ESCAPE '\\'", [mb_strtolower($pattern)], $boolean);
        };
        foreach ([\Illuminate\Database\Query\Builder::class, \Illuminate\Database\Eloquent\Builder::class] as $builder) {
            $builder::macro('whereLikeCi', function (string $column, string $pattern) use ($ci) {
                return $ci->call($this, $column, $pattern, 'and');
            });
            $builder::macro('orWhereLikeCi', function (string $column, string $pattern) use ($ci) {
                return $ci->call($this, $column, $pattern, 'or');
            });
        }
    }
}
