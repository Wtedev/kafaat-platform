<?php

namespace Tests\Unit\Deploy;

use App\Services\Deploy\ContentSeedFingerprint;
use PHPUnit\Framework\TestCase;

class ContentSeedFingerprintTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/kafaat-seed-fingerprint-'.uniqid();
        mkdir($this->directory.'/seeders/nested', 0775, true);
        file_put_contents($this->directory.'/seeders/A.php', "<?php\n");
        file_put_contents($this->directory.'/seeders/nested/B.php', "<?php\n");
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->directory);
        parent::tearDown();
    }

    public function test_same_sources_match_after_the_stamp_is_stored(): void
    {
        $stamp = $this->directory.'/stamp';
        $fingerprint = new ContentSeedFingerprint($this->directory.'/seeders', $stamp);

        $this->assertFalse($fingerprint->matches());
        $fingerprint->store();
        $this->assertTrue((new ContentSeedFingerprint($this->directory.'/seeders', $stamp))->matches());
    }

    public function test_a_source_change_no_longer_matches(): void
    {
        $stamp = $this->directory.'/stamp';
        $fingerprint = new ContentSeedFingerprint($this->directory.'/seeders', $stamp);
        $fingerprint->store();

        file_put_contents($this->directory.'/seeders/A.php', "<?php\n// changed\n");

        $this->assertFalse($fingerprint->matches());
    }

    private function deleteTree(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = scandir($directory) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $directory.'/'.$item;
            if (is_dir($path)) {
                $this->deleteTree($path);
            } else {
                unlink($path);
            }
        }
        rmdir($directory);
    }
}
