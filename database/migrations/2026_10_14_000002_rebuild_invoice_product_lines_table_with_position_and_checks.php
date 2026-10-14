<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        $this->rebuild(
            static fn () => DB::statement(<<<'SQL'
                CREATE TABLE invoice_product_lines_new (
                    id TEXT NOT NULL PRIMARY KEY,
                    invoice_id TEXT NOT NULL REFERENCES invoices (id) ON DELETE CASCADE,
                    position INTEGER NOT NULL CHECK (position >= 0),
                    name TEXT NOT NULL,
                    quantity INTEGER NOT NULL CHECK (quantity BETWEEN 1 AND 2147483647),
                    unit_price INTEGER NOT NULL CHECK (unit_price BETWEEN 1 AND 2147483647),
                    created_at TEXT,
                    updated_at TEXT
                ) STRICT
                SQL),
            <<<'SQL'
                INSERT INTO invoice_product_lines_new
                    (id, invoice_id, position, name, quantity, unit_price, created_at, updated_at)
                SELECT id, invoice_id,
                       ROW_NUMBER() OVER (PARTITION BY invoice_id ORDER BY created_at, id) - 1,
                       name, quantity, unit_price, created_at, updated_at
                FROM invoice_product_lines
                SQL,
            static fn () => DB::statement(
                'CREATE UNIQUE INDEX invoice_product_lines_invoice_id_position_unique '
                .'ON invoice_product_lines (invoice_id, position)'
            ),
        );
    }

    public function down(): void
    {
        $this->rebuild(
            static fn () => Schema::create('invoice_product_lines_new', static function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('invoice_id');
                $table->string('name');
                $table->integer('unit_price');
                $table->integer('quantity');
                $table->timestamps();

                $table->foreign('invoice_id')->references('id')->on('invoices');
            }),
            <<<'SQL'
                INSERT INTO invoice_product_lines_new
                    (id, invoice_id, name, unit_price, quantity, created_at, updated_at)
                SELECT id, invoice_id, name, unit_price, quantity, created_at, updated_at
                FROM invoice_product_lines
                SQL,
            static fn () => null,
        );
    }

    private function rebuild(Closure $createNew, string $copy, Closure $createIndexes): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            DB::transaction(static function () use ($createNew, $copy, $createIndexes): void {
                $createNew();
                DB::statement($copy);
                Schema::drop('invoice_product_lines');
                Schema::rename('invoice_product_lines_new', 'invoice_product_lines');
                $createIndexes();

                if (DB::select('PRAGMA foreign_key_check') !== []) {
                    throw new RuntimeException('Foreign key violations found after rebuilding the invoice_product_lines table.');
                }
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
};
