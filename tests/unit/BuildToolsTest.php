<?php

namespace greenmeteor\native\tests\unit;

use greenmeteor\native\build\Doctor;
use greenmeteor\native\build\ManifestBuilder;
use greenmeteor\native\build\PluginScaffolder;
use greenmeteor\native\build\ProjectInstaller;
use greenmeteor\native\plugin\PluginManager;
use greenmeteor\native\tests\Support\NativeTestCase;
use yii\base\InvalidArgumentException;

class BuildToolsTest extends NativeTestCase
{
    public function testManifestContents(): void
    {
        $native = $this->native([
            'appId' => 'com.acme.app', 'name' => 'Acme', 'version' => '2.0.1', 'versionCode' => 7,
            'orientation' => 'auto', 'deepLinkScheme' => 'acme', 'deepLinkHosts' => ['acme.test'],
            'permissions' => ['camera' => 'Scan <receipts> & "more"'],
            'plugins' => [['id' => 'acme/p', 'version' => '1.0.0', 'bridge' => ['functions' => ['P.go' => []]]]],
        ]);
        $m = (new ManifestBuilder($native))->build();

        $this->assertSame(1, $m['schema']);
        $this->assertSame(['id' => 'com.acme.app', 'name' => 'Acme', 'version' => '2.0.1', 'versionCode' => 7, 'orientation' => 'auto'], $m['app']);
        $this->assertSame(['scheme' => 'acme', 'hosts' => ['acme.test']], $m['deepLinks']);
        $this->assertSame(['camera'], $m['permissions']);
        $this->assertContains('android.permission.CAMERA', $m['android']['permissions']);
        $this->assertArrayHasKey('acme/p', $m['plugins']);
        $this->assertContains('P.go', $m['bridgeFunctions']);
        $this->assertContains('Camera.getPhoto', $m['bridgeFunctions']);
    }

    public function testManifestIsDeterministic(): void
    {
        $native = $this->native(['permissions' => ['location', 'camera']]);
        $b = new ManifestBuilder($native);
        $this->assertSame($b->build(), $b->build());
        $this->assertSame(['camera', 'location'], $b->build()['permissions']);
    }

    public function testFragmentsAreXmlEscaped(): void
    {
        $native = $this->native(['permissions' => ['camera' => 'Scan <receipts> & "more"']]);
        $plist = (new ManifestBuilder($native))->iosPlistFragment();
        $this->assertStringContainsString('<string>Scan &lt;receipts&gt; &amp; &quot;more&quot;</string>', $plist);
        $this->assertStringContainsString('<uses-permission android:name="android.permission.INTERNET" />', (new ManifestBuilder($native))->androidManifestFragment());

        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML('<dict>' . $plist . '</dict>'), 'plist fragment must be well-formed XML');
    }

    public function testWriteCreatesThreeFiles(): void
    {
        $dir = $this->tempDir() . '/build';
        $files = (new ManifestBuilder($this->native()))->write($dir);
        $this->assertCount(3, $files);
        foreach ($files as $f) {
            $this->assertFileExists($f);
        }
        $this->assertSame('com.example.test', json_decode(file_get_contents("$dir/manifest.json"), true)['app']['id']);
    }

    public function testInstallerCreatesLayoutAndIsIdempotent(): void
    {
        $this->native();
        $root = $this->tempDir() . '/native';
        $installer = new ProjectInstaller($root);

        $first = $installer->install();
        $this->assertContains('config/native.php', $first['created']);
        $this->assertDirectoryExists("$root/android");

        file_put_contents("$root/config/native.php", '<?php // customised');
        $second = $installer->install();
        $this->assertSame([], $second['created']);
        $this->assertSame('<?php // customised', file_get_contents("$root/config/native.php"));

        $installer->install(true);
        $this->assertStringContainsString('com.example.app', file_get_contents("$root/config/native.php"));
    }

    public function testGeneratedConfigStubIsValidPhpThatBootsAComponent(): void
    {
        $this->native();
        $root = $this->tempDir() . '/native';
        (new ProjectInstaller($root))->install();
        $config = require "$root/config/native.php";
        $this->assertSame(\greenmeteor\native\Native::class, $config['class']);

        $native = $this->native(array_diff_key($config, ['class' => 1]));
        $this->assertSame([], $native->validate(), 'the stub must be a valid configuration');
    }

    public function testScaffolderCreatesLoadablePlugin(): void
    {
        $dir = $this->tempDir() . '/maps';
        $files = (new PluginScaffolder())->create('acme/maps-kit', $dir);
        $this->assertCount(5, $files);

        $manager = new PluginManager();
        $found = $manager->discover($dir);
        $this->assertSame(['acme/maps-kit'], array_keys($found));
        $this->assertSame([], \greenmeteor\native\plugin\ManifestValidator::validate($found['acme/maps-kit']));
        $this->assertStringContainsString('class MapsKitPlugin', file_get_contents("$dir/MapsKitPlugin.php"));
        $this->assertSame(0, $this->lint("$dir/MapsKitPlugin.php"));
    }

    public function testScaffolderRejectsBadIdsAndExistingPlugins(): void
    {
        $s = new PluginScaffolder();
        $dir = $this->tempDir() . '/p';
        $s->create('ok', $dir);
        try {
            $s->create('ok', $dir);
            $this->fail('Overwrote an existing plugin');
        } catch (InvalidArgumentException $e) {
            $this->addToAssertionCount(1);
        }
        $this->expectException(InvalidArgumentException::class);
        $s->create('../evil', $this->tempDir() . '/q');
    }

    public function testDoctorReportsSecurityRiskyHttpConfig(): void
    {
        $find = fn (array $checks, string $name) => array_values(array_filter($checks, fn ($c) => $c['name'] === $name))[0];

        $risky = (new Doctor($this->native(['http' => ['requireRuntime' => false]])))->run();
        $this->assertSame(Doctor::FAIL, $find($risky, 'HTTP bridge')['status']);
        $this->assertTrue(Doctor::hasFailures($risky));

        $default = (new Doctor($this->native()))->run();
        $this->assertSame(Doctor::WARN, $find($default, 'HTTP bridge')['status']);

        $safe = (new Doctor($this->native(['http' => ['secret' => str_repeat('s', 32)]])))->run();
        $this->assertSame(Doctor::OK, $find($safe, 'HTTP bridge')['status']);

        $off = (new Doctor($this->native(['http' => ['enabled' => false]])))->run();
        $this->assertSame(Doctor::OK, $find($off, 'HTTP bridge')['status']);
    }

    public function testDoctorFailsOnInvalidConfiguration(): void
    {
        $checks = (new Doctor($this->native(['appId' => 'nope'])))->run();
        $this->assertTrue(Doctor::hasFailures($checks));
    }

    public function testDoctorSurfacesPluginErrors(): void
    {
        $checks = (new Doctor($this->native(['plugins' => ['/missing/dir']])))->run();
        $names = array_column($checks, 'name');
        $this->assertContains('Plugin: /missing/dir', $names);
    }

    private function lint(string $file): int
    {
        exec('php -l ' . escapeshellarg($file) . ' 2>&1', $out, $code);

        return $code;
    }
}
