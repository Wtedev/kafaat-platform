<?php

namespace Database\Seeders;

use App\Models\BoardMember;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Seeds current board members and attaches durable portrait paths under
 * public/images/board/ (git-backed) so Railway predeploy keeps photos without
 * relying on the ephemeral public-disk volume.
 */
class BoardMembersSeeder extends Seeder
{
    /** @var list<array{name: string, role: string, photo: string}> */
    private const MEMBERS = [
        [
            'name' => 'د. عبد الله بن يوسف الضحيان',
            'role' => 'رئيس المجلس',
            'photo' => 'images/board/abdullah-aldhuhayan.jpg',
        ],
        [
            'name' => 'د. عماد بن عبد الرحمن الوشمي',
            'role' => 'نائب الرئيس',
            'photo' => 'images/board/imad-alwashmi.jpg',
        ],
        [
            'name' => 'أ. عبد الإله بن عبد الرحمن الطويان',
            'role' => 'المشرف المالي',
            'photo' => 'images/board/abdulelah-altuwaiyan.jpg',
        ],
        [
            'name' => 'أ. عبد العزيز بن إبراهيم الربدي',
            'role' => 'عضو المجلس',
            'photo' => 'images/board/abdulaziz-alrabdi.jpg',
        ],
        [
            'name' => 'د. إبراهيم بن صالح الراشد',
            'role' => 'عضو المجلس',
            'photo' => 'images/board/ibrahim-alrashid.jpg',
        ],
        [
            'name' => 'د. سليمان بن صالح المسيطير',
            'role' => 'عضو المجلس',
            'photo' => 'images/board/sulaiman-almusetir.jpg',
        ],
        [
            'name' => 'أ. وليد بن فهد المرزوق',
            'role' => 'عضو المجلس',
            'photo' => 'images/board/waleed-almarzouq.jpg',
        ],
    ];

    public function run(): void
    {
        if (! Schema::hasTable('board_members')) {
            $this->command?->warn('BoardMembersSeeder: table `board_members` is missing. Run migrations first.');

            return;
        }

        BoardMember::query()
            ->where('group', BoardMember::GROUP_BOARD)
            ->whereNotIn('name', array_column(self::MEMBERS, 'name'))
            ->delete();

        $updated = 0;

        foreach (self::MEMBERS as $index => $member) {
            $photo = $this->resolvePhotoPath($member['photo']);

            BoardMember::query()->updateOrCreate(
                [
                    'group' => BoardMember::GROUP_BOARD,
                    'name' => $member['name'],
                ],
                [
                    'role' => $member['role'],
                    'photo' => $photo,
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ],
            );

            $updated++;
        }

        $this->command?->info(sprintf(
            'BoardMembersSeeder: upserted %d board member(s) with portrait paths under public/images/board/.',
            $updated,
        ));
    }

    private function resolvePhotoPath(string $relativePublicPath): ?string
    {
        $local = public_path($relativePublicPath);

        if (! File::exists($local) || File::size($local) <= 0) {
            $this->command?->warn('BoardMembersSeeder: missing portrait at '.$relativePublicPath);

            return null;
        }

        $this->mirrorToPublicDisk($local, $relativePublicPath);

        return $relativePublicPath;
    }

    /**
     * Best-effort mirror onto the Laravel public disk (volume/S3) without failing the seeder.
     */
    private function mirrorToPublicDisk(string $localPath, string $relativePublicPath): void
    {
        try {
            Storage::disk('public')->put($relativePublicPath, File::get($localPath));
        } catch (\Throwable $e) {
            $this->command?->warn(sprintf(
                'BoardMembersSeeder: could not mirror %s to public disk (%s). Git path still used.',
                $relativePublicPath,
                $e->getMessage(),
            ));
        }
    }
}
