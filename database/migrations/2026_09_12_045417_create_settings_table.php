<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->string('key', 150)->unique();

            $table->text('value')->nullable();

            $table->string('type', 20)
                ->default('string');

            $table->string('group', 50)
                ->default('system');

            $table->string('description', 255)
                ->nullable();

            $table->boolean('is_public')
                ->default(false);

            $table->boolean('is_encrypted')
                ->default(false);

            $table->timestamps();

            $table->index('group');
            $table->index('is_public');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
