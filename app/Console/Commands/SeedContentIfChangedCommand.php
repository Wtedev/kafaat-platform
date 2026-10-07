<?php

namespace App\Console\Commands;

use App\Services\Deploy\ContentSeedFingerprint;
use Database\Seeders\DataForumProgramSeeder;
use Database\Seeders\FaeqoonProgramArchiveSeeder;
use Database\Seeders\GovernanceContentSeeder;
use Database\Seeders\MediaPhotoSeeder;
use Database\Seeders\NewsCoverAssetsSeeder;
use Database\Seeders\PartnerSeeder;
use Database\Seeders\PrivacyPolicyGenderUpdateSeeder;
use Database\Seeders\PrivacyPolicySeeder;
use Database\Seeders\RegulationsSeeder;
use Database\Seeders\VolunteerLeadersProgramCoverSeeder;
use Database\Seeders\VolunteerLeadersProgramDatesSeeder;
use Database\Seeders\VolunteerLeadersProgramDeliverySeeder;
use Database\Seeders\VolunteerLeadersProgramDescriptionSeeder;
use Database\Seeders\VolunteerLeadersProgramFemaleCapacitySeeder;
use Database\Seeders\VolunteerLeadersProgramPresentersSeeder;
use Database\Seeders\VolunteerLeadersProgramWhatsappSeeder;
use Database\Seeders\VolunteerOpportunitySeeder;
use Illuminate\Console\Command;

class SeedContentIfChangedCommand extends Command
{
    protected $signature = 'deploy:seed-content
                            {--force : Run the content seeders even when sources are unchanged}';

    protected $description = 'Seed public content only when database/seeders changed since the last run';

    /**
     * @var list<class-string>
     */
    private const SEEDERS = [
        PrivacyPolicySeeder::class,
        PrivacyPolicyGenderUpdateSeeder::class,
        GovernanceContentSeeder::class,
        RegulationsSeeder::class,
        VolunteerOpportunitySeeder::class,
        PartnerSeeder::class,
        MediaPhotoSeeder::class,
        VolunteerLeadersProgramCoverSeeder::class,
        VolunteerLeadersProgramDatesSeeder::class,
        VolunteerLeadersProgramDescriptionSeeder::class,
        VolunteerLeadersProgramDeliverySeeder::class,
        VolunteerLeadersProgramPresentersSeeder::class,
        VolunteerLeadersProgramWhatsappSeeder::class,
        VolunteerLeadersProgramFemaleCapacitySeeder::class,
        FaeqoonProgramArchiveSeeder::class,
        DataForumProgramSeeder::class,
        NewsCoverAssetsSeeder::class,
    ];

    public function handle(): int
    {
        $fingerprint = new ContentSeedFingerprint(
            database_path('seeders'),
            storage_path('app/public/.deploy/content-seeders.sha256'),
        );

        if (! $this->option('force') && $fingerprint->matches()) {
            $this->info('Content seeders unchanged; skipping.');

            return self::SUCCESS;
        }

        foreach (self::SEEDERS as $seeder) {
            $this->call('db:seed', [
                '--class' => $seeder,
                '--force' => true,
            ]);
        }

        $fingerprint->store();
        $this->info('Content seeders finished.');

        return self::SUCCESS;
    }
}
