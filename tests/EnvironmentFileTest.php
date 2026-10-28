<?php

declare(strict_types=1);

namespace Dock\AltGenerator\Laravel\Tests;

use Dock\AltGenerator\Laravel\Support\EnvironmentFile;

final class EnvironmentFileTest extends TestCase
{
    public function test_it_replaces_and_appends_without_touching_other_lines(): void
    {
        $path = $this->directory.'/.env';
        file_put_contents($path, "APP_NAME=Shop\nDOCKACCESS_ALT_KEY=old\n# comment\n");

        (new EnvironmentFile($path))->set(['DOCKACCESS_ALT_KEY' => 'new-key_1', 'DOCKACCESS_ALT_SECRET' => 'with space $x']);

        $this->assertSame("APP_NAME=Shop\nDOCKACCESS_ALT_KEY=new-key_1\n# comment\nDOCKACCESS_ALT_SECRET=\"with space \\\$x\"\n", file_get_contents($path));
    }

    public function test_it_removes_variables(): void
    {
        $path = $this->directory.'/.env';
        file_put_contents($path, "APP_NAME=Shop\nDOCKACCESS_ALT_KEY=k\nDOCKACCESS_ALT_SECRET=s\nAPP_ENV=local\n");

        (new EnvironmentFile($path))->remove(['DOCKACCESS_ALT_KEY', 'DOCKACCESS_ALT_SECRET']);

        $this->assertSame("APP_NAME=Shop\nAPP_ENV=local\n", file_get_contents($path));
    }
}
