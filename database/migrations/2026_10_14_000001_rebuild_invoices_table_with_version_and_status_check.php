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
            static function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('customer_name');
                $table->string('customer_email');
                $table->enum('status', ['draft', 'sending', 'sent-to-client']);
                $table->unsignedInteger('version')->default(0);
                $table->timestamps();
            },
            'id, customer_name, customer_email, status, version, created_at, updated_at',
            'id, customer_name, customer_email, status, 0, created_at, updated_at',
        );
    }

    public function down(): void
    {
        $this->rebuild(
            static function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('customer_name');
                $table->string('customer_email');
                $table->string('status');
                $table->timestamps();
            },
            'id, customer_name, customer_email, status, created_at, updated_at',
            'id, customer_name, customer_email, status, created_at, updated_at',
        );
    }

    private function rebuild(Closure $definition, string $targetColumns, string $sourceColumns): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            DB::transaction(static function () use ($definition, $targetColumns, $sourceColumns): void {
                Schema::create('invoices_new', $definition);
                DB::statement("INSERT INTO invoices_new ({$targetColumns}) SELECT {$sourceColumns} FROM invoices");
                Schema::drop('invoices');
                Schema::rename('invoices_new', 'invoices');

                if (DB::select('PRAGMA foreign_key_check') !== []) {
                    throw new RuntimeException('Foreign key violations found after rebuilding the invoices table.');
                }
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
};
