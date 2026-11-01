<?php

namespace greenmeteor\native\tests\unit;

use greenmeteor\native\plugin\ManifestValidator;
use greenmeteor\native\plugin\Plugin;
use greenmeteor\native\plugin\PluginManager;
use greenmeteor\native\tests\Support\NativeTestCase;
use yii\base\InvalidConfigException;

class PluginLifecycleTest extends NativeTestCase
{
    private function manifest(array $extra = []): array
    {
        return $extra + [
            'id' => 'acme/maps',
            'version' => '1.2.0',
            'permissions' => ['location'],
            'bridge' => ['functions' => ['Maps.open' => ['description' => 'Open', 'permissions' => ['location']]]],
            'elements' => ['map' => ['description' => 'Map view']],
            'events' => ['mapsMarkerTapped' => ['description' => 'Tapped']],
        ];
    }

    public function testBootRegistersFunctionsNodesEventsAndPermissions(): void
    {
        $native = $this->native(['plugins' => [$this->manifest()]]);

        $this->assertTrue($native->pluginManager->has('acme/maps'));
        $this->assertTrue($native->protocol->hasFunction('Maps.open'));
        $this->assertTrue($native->protocol->hasNode('map'));
        $this->assertTrue($native->isKnownEvent('mapsMarkerTapped'));
        $this->assertTrue($native->permissionManager->has('location'));
        $this->assertSame([], $native->pluginManager->getErrors());

        // A second Native instance must not see them (protocol is per-instance).
        $this->assertFalse((new \greenmeteor\native\protocol\Protocol())->hasFunction('Maps.open'));
    }

    public function testPluginPhpHandlersWorkWithoutRuntime(): void
    {
        $plugin = new class(['id' => 'echo']) extends Plugin {
            public function handlers(): array
            {
                return ['Echo.say' => static fn (array $a) => strtoupper($a['m'])];
            }
        };
        $native = $this->native();
        $native->pluginManager->register($plugin);
        $native->pluginManager->boot();

        $this->assertSame('HI', $native->call('Echo.say', ['m' => 'hi']));
        $this->assertTrue($native->isHttpFunctionAllowed('Echo.say'));
    }

    public function testBootRunsOnlyOnce(): void
    {
        $plugin = new class(['id' => 'once']) extends Plugin {
            public int $boots = 0;
            public function boot(\greenmeteor\native\Native $native): void
            {
                $this->boots++;
            }
        };
        $native = $this->native();
        $native->pluginManager->register($plugin);
        $native->pluginManager->boot();
        $native->pluginManager->boot();
        $this->assertSame(1, $plugin->boots);
    }

    public function testManifestClassIsInstantiated(): void
    {
        $native = $this->native(['plugins' => [['id' => 'custom', 'class' => CustomPlugin::class]]]);
        $this->assertInstanceOf(CustomPlugin::class, $native->pluginManager->get('custom'));
    }

    public function testBadSourcesAreCollectedNotFatal(): void
    {
        $native = $this->native(['plugins' => [
            ['id' => 'Bad ID'],
            '/definitely/not/here',
            ['id' => 'good'],
            \stdClass::class,
        ]]);
        $this->assertTrue($native->pluginManager->has('good'));
        $errors = $native->pluginManager->getErrors();
        $this->assertCount(3, $errors);
        $this->assertStringContainsString('Invalid native plugin manifest', $errors['inline']);
        $this->assertStringContainsString('was not found', $errors['/definitely/not/here']);
    }

    public function testBootErrorsAreCollected(): void
    {
        $plugin = new class(['id' => 'boom']) extends Plugin {
            public function boot(\greenmeteor\native\Native $native): void
            {
                throw new \RuntimeException('nope');
            }
        };
        $native = $this->native();
        $native->pluginManager->register($plugin);
        $native->pluginManager->boot();
        $this->assertSame('boot failed: nope', $native->pluginManager->getErrors()['boom']);
    }

    public function testLoadFromDirectoryAndJsonManifest(): void
    {
        $dir = $this->tempDir();
        mkdir("$dir/a", 0777, true);
        mkdir("$dir/b/node_modules/x", 0777, true);
        file_put_contents("$dir/a/native-plugin.json", json_encode(['id' => 'json-plugin', 'version' => '2.0.0']));
        file_put_contents("$dir/b/node_modules/x/native-plugin.php", "<?php return ['id' => 'should-be-skipped'];");

        $native = $this->native(['plugins' => [$dir]]);
        $this->assertSame(['json-plugin'], array_keys($native->pluginManager->all()));
    }

    public function testInvalidJsonManifestIsReportedAndSkipped(): void
    {
        $dir = $this->tempDir();
        file_put_contents("$dir/native-plugin.json", '{not json');
        $manager = new PluginManager();
        $this->assertSame([], $manager->discover($dir));
        $this->assertCount(1, $manager->getErrors());
    }

    public function testDiscoverComposerFindsTypedPackagesAndStaysInsidePackage(): void
    {
        $vendor = $this->tempDir();
        mkdir("$vendor/composer", 0777, true);
        mkdir("$vendor/acme/typed", 0777, true);
        mkdir("$vendor/acme/extra", 0777, true);
        mkdir("$vendor/acme/evil", 0777, true);
        mkdir("$vendor/acme/plain", 0777, true);
        file_put_contents("$vendor/acme/typed/native-plugin.php", "<?php return ['id' => 'acme/typed', 'version' => '1.0.0'];");
        file_put_contents("$vendor/acme/extra/custom.json", json_encode(['id' => 'acme/extra']));
        file_put_contents("$vendor/acme/plain/native-plugin.php", "<?php return ['id' => 'acme/plain'];");
        file_put_contents("$vendor/outside.json", json_encode(['id' => 'escaped']));
        file_put_contents("$vendor/composer/installed.json", json_encode(['packages' => [
            ['name' => 'acme/typed', 'type' => 'yii2-native-plugin', 'install-path' => '../acme/typed'],
            ['name' => 'acme/extra', 'type' => 'library', 'install-path' => '../acme/extra', 'extra' => ['yii2-native' => ['manifest' => 'custom.json']]],
            ['name' => 'acme/evil', 'type' => 'library', 'install-path' => '../acme/evil', 'extra' => ['yii2-native' => ['manifest' => '../../outside.json']]],
            ['name' => 'acme/plain', 'type' => 'library', 'install-path' => '../acme/plain'],
        ]]));

        $found = (new PluginManager())->discoverComposer($vendor);
        $this->assertSame(['acme/typed', 'acme/extra'], array_keys($found));
        $this->assertSame('acme/typed', $found['acme/typed']['_package']);
        $this->assertArrayNotHasKey('escaped', $found);
    }

    public function testDiscoverComposerWithoutInstalledJson(): void
    {
        $this->assertSame([], (new PluginManager())->discoverComposer('/nonexistent'));
    }

    public function testCreateFromManifestRejectsNonPluginClass(): void
    {
        $this->expectException(InvalidConfigException::class);
        (new PluginManager())->createFromManifest(['id' => 'x', 'class' => \stdClass::class]);
    }

    public function testValidator(): void
    {
        $this->assertSame([], ManifestValidator::validate($this->manifest()));

        $bad = ManifestValidator::validate([
            'id' => 'Has Space',
            'version' => 'one',
            'platforms' => ['windows'],
            'permissions' => ['teleport'],
            'bridge' => ['functions' => ['nodot' => [], 'A.b' => 'notarray']],
            'elements' => ['Bad_Type' => []],
            'events' => ['9bad'],
        ]);
        $this->assertCount(8, $bad, implode("\n", $bad));
        $this->assertNotEmpty(ManifestValidator::validate([]));
    }
}

class CustomPlugin extends Plugin
{
}
