<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products')
            || ! Schema::hasColumn('products', 'category')
            || ! Schema::hasColumn('products', 'sort_order')
            || ! Schema::hasColumn('products', 'is_active')
            || ! Schema::hasColumn('products', 'is_default')) {
            return;
        }

        $defaults = [
            'SSAS' => ['category' => 'Social security administration software', 'sort_order' => 10],
            'HRMS' => ['category' => 'Human resources management software', 'sort_order' => 20],
            'ERMS' => ['category' => 'Enterprise risk management software', 'sort_order' => 30],
            'EBPC' => ['category' => 'Budget planning and control software', 'sort_order' => 40],
        ];

        foreach ($defaults as $code => $values) {
            DB::table('products')
                ->whereRaw('UPPER(code) = ?', [$code])
                ->update([
                    'category' => $values['category'],
                    'sort_order' => $values['sort_order'],
                    'is_active' => true,
                ]);
        }

        $defaultCode = strtoupper((string) config('sls.products.default_code', 'SSAS'));
        if (! DB::table('products')->where('is_default', true)->exists()) {
            DB::table('products')
                ->whereRaw('UPPER(code) = ?', [$defaultCode])
                ->update(['is_default' => true]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('products')
            || ! Schema::hasColumn('products', 'category')
            || ! Schema::hasColumn('products', 'sort_order')
            || ! Schema::hasColumn('products', 'is_default')) {
            return;
        }

        DB::table('products')
            ->whereRaw('UPPER(code) IN (?, ?, ?, ?)', ['SSAS', 'HRMS', 'ERMS', 'EBPC'])
            ->update([
                'category' => null,
                'sort_order' => 0,
                'is_default' => false,
            ]);
    }
};
