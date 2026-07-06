<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class GovernanceContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            BoardMembersSeeder::class,
            GeneralAssemblyMembersSeeder::class,
            StandingCommitteesSeeder::class,
            InvestmentDecisionsSeeder::class,
            GeneralAssemblyMinutesSeeder::class,
            SurveysSeeder::class,
            ExecutiveReportsSeeder::class,
        ]);
    }
}
