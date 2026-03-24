<?php

namespace Database\Seeders;

use App\Services\SourcePreviews\SourcePreviewDomainManager;
use Illuminate\Database\Seeder;

class SourcePreviewDomainSeeder extends Seeder
{
    public function run(): void
    {
        app(SourcePreviewDomainManager::class)->syncDefaults();
    }
}
