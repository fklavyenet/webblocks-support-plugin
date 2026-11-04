<?php

use Illuminate\Database\Migrations\Migration;
use WebBlocks\Support\Database\SupportConnectionSchema;

return new class extends Migration
{
  public function up(): void
  {
    app(SupportConnectionSchema::class)->ensure();
  }

  public function down(): void
  {
    // Operator-managed support credentials and tracking links are preserved.
  }
};
