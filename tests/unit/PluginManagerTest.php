<?php

namespace greenmeteor\native\tests\unit;

use Codeception\Test\Unit;
use greenmeteor\native\plugin\Plugin;
use greenmeteor\native\plugin\PluginManager;

class PluginManagerTest extends Unit
{
    public function testRegisterGetHasAndAll(): void
    {
        $manager = new PluginManager();
        $plugin = new Plugin([
            'id' => 'device',
            'manifest' => ['id' => 'device', 'version' => '1.0.0'],
        ]);

        $manager->register($plugin);

        $this->assertTrue($manager->has('device'));
        $this->assertSame($plugin, $manager->get('device'));
        $this->assertSame(['device' => $plugin], $manager->all());
        $this->assertNull($manager->get('missing'));
    }

    public function testDiscoverReturnsEmptyForMissingDirectory(): void
    {
        $this->assertSame([], (new PluginManager())->discover(sys_get_temp_dir() . '/yii2-native-missing-' . uniqid()));
    }

    public function testDiscoverReadsPluginManifests(): void
    {
        $directory = sys_get_temp_dir() . '/yii2-native-plugins-' . bin2hex(random_bytes(5));
        mkdir($directory . '/sample', 0777, true);

        try {
            file_put_contents($directory . '/sample/native-plugin.php', "<?php return ['id' => 'sample', 'version' => '1.0.0'];");
            file_put_contents($directory . '/ignored.php', "<?php return ['id' => 'ignored'];");

            $this->assertSame(
                ['sample' => ['id' => 'sample', 'version' => '1.0.0']],
                (new PluginManager())->discover($directory)
            );
        } finally {
            $this->removeDirectory($directory);
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path) && !is_link($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}
