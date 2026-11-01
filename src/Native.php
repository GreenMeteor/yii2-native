<?php

namespace greenmeteor\native;

use greenmeteor\native\api\Biometrics;
use greenmeteor\native\api\BaseApi;
use greenmeteor\native\api\Browser;
use greenmeteor\native\api\Camera;
use greenmeteor\native\api\Clipboard;
use greenmeteor\native\api\Device;
use greenmeteor\native\api\Dialog;
use greenmeteor\native\api\Filesystem;
use greenmeteor\native\api\Geolocation;
use greenmeteor\native\api\Haptics;
use greenmeteor\native\api\Microphone;
use greenmeteor\native\api\Network;
use greenmeteor\native\api\Notifications;
use greenmeteor\native\api\Scanner;
use greenmeteor\native\api\SecureStorage;
use greenmeteor\native\api\Share;
use greenmeteor\native\api\System;
use greenmeteor\native\bridge\FakeTransport;
use greenmeteor\native\bridge\NativeBridge;
use greenmeteor\native\events\NativeEvent;
use greenmeteor\native\events\NativeEvents;
use greenmeteor\native\permission\PermissionManager;
use greenmeteor\native\plugin\PluginManager;
use greenmeteor\native\protocol\Protocol;
use greenmeteor\native\renderer\Renderer;
use greenmeteor\native\runtime\Runtime;
use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;

/**
 * The `Yii::$app->native` application component.
 *
 * @property-read Runtime $runtime
 * @property-read NativeBridge $bridge
 * @property-read Renderer $renderer
 * @property-read PluginManager $pluginManager
 * @property-read PermissionManager $permissionManager
 * @property-read Protocol $protocol
 * @property-read Biometrics $biometrics
 * @property-read Browser $browser
 * @property-read Camera $camera
 * @property-read Clipboard $clipboard
 * @property-read Device $device
 * @property-read Dialog $dialog
 * @property-read Filesystem $filesystem
 * @property-read Geolocation $geolocation
 * @property-read Haptics $haptics
 * @property-read Microphone $microphone
 * @property-read Network $network
 * @property-read Notifications $notifications
 * @property-read Scanner $scanner
 * @property-read SecureStorage $secureStorage
 * @property-read Share $share
 * @property-read System $system
 */
class Native extends Component
{
    public const VERSION = '0.2.0';

    private const ORIENTATIONS = ['portrait', 'landscape', 'auto'];

    private const DEFAULT_HTTP = [
        'enabled' => true,
        // Routes registered by the bootstrap when pretty URLs are enabled.
        'bridgeRoute' => '_native/bridge',
        'eventsRoute' => '_native/events',
        // Optional shared secret the shell sends as the X-Native-Secret header.
        'secret' => null,
        // Reject HTTP bridge requests unless the native runtime is present.
        'requireRuntime' => true,
        // Function patterns (fnmatch) callable over HTTP. Empty = every known function.
        'allow' => [],
        'deny' => [],
        // Accept events that are not in protocol.json / plugin manifests.
        'allowUnknownEvents' => false,
    ];

    /** Component definitions: a class name or a Yii config array with a `class` key. */
    public $runtimeClass = Runtime::class;
    public $bridgeClass = NativeBridge::class;
    public $rendererClass = Renderer::class;
    public $pluginManagerClass = PluginManager::class;
    public $permissionManagerClass = PermissionManager::class;

    /** Reverse-DNS application id, e.g. `com.example.app`. */
    public string $appId = '';
    public string $name = '';
    public string $version = '1.0.0';
    public int $versionCode = 1;
    /** portrait | landscape | auto */
    public string $orientation = 'portrait';
    public ?string $deepLinkScheme = null;
    /** @var string[] hosts for universal / app links */
    public array $deepLinkHosts = [];
    /** @var array<int|string,string|null> see {@see PermissionManager} */
    public array $permissions = [];
    /**
     * Plugins to load: manifest arrays, manifest/directory paths (aliases allowed),
     * or class names implementing {@see plugin\PluginInterface}.
     */
    public array $plugins = [];
    /** Throw {@see exceptions\NativePermissionException} for undeclared permissions. */
    public bool $enforcePermissions = true;
    /** Overrides for API classes: `['camera' => MyCamera::class]`. */
    public array $apis = [];
    /** HTTP bridge options, see {@see DEFAULT_HTTP}. */
    public array $http = [];
    /**
     * Shell build commands: `['android' => ['build' => '...', 'run' => '...'], 'ios' => [...]]`.
     * Executed by `native/build --exec` and `native/run --exec`.
     */
    public array $build = [];
    public string $projectPath = '@app/native';

    private ?Runtime $_runtime = null;
    private ?NativeBridge $_bridge = null;
    private ?Renderer $_renderer = null;
    private ?PluginManager $_pluginManager = null;
    private ?PermissionManager $_permissionManager = null;
    private ?Protocol $_protocol = null;
    /** @var array<string,BaseApi> */
    private array $_apis = [];

    private const API_CLASSES = [
        'biometrics' => Biometrics::class,
        'browser' => Browser::class,
        'camera' => Camera::class,
        'clipboard' => Clipboard::class,
        'device' => Device::class,
        'dialog' => Dialog::class,
        'filesystem' => Filesystem::class,
        'geolocation' => Geolocation::class,
        'haptics' => Haptics::class,
        'microphone' => Microphone::class,
        'network' => Network::class,
        'notifications' => Notifications::class,
        'scanner' => Scanner::class,
        'secureStorage' => SecureStorage::class,
        'share' => Share::class,
        'system' => System::class,
    ];

    public function init()
    {
        parent::init();

        $this->http = array_replace(self::DEFAULT_HTTP, $this->http);
        if ($this->name === '' && Yii::$app !== null) {
            $this->name = (string) Yii::$app->name;
        }

        $this->_protocol = new Protocol();
        $this->_runtime = $this->make($this->runtimeClass);

        $this->_permissionManager = $this->make($this->permissionManagerClass, [
            'protocol' => $this->_protocol,
            'enforce' => $this->enforcePermissions,
        ]);
        $this->_permissionManager->setPermissions($this->permissions);

        $this->_bridge = $this->make($this->bridgeClass, [
            'runtime' => $this->_runtime,
            'protocol' => $this->_protocol,
            'permissions' => $this->_permissionManager,
        ]);

        $this->_renderer = $this->make($this->rendererClass, ['protocol' => $this->_protocol]);

        $this->_pluginManager = $this->make($this->pluginManagerClass, ['native' => $this]);
        if ($this->plugins) {
            $this->_pluginManager->load($this->plugins);
        }
    }

    public function getRuntime(): Runtime
    {
        return $this->_runtime;
    }

    public function getBridge(): NativeBridge
    {
        return $this->_bridge;
    }

    public function getRenderer(): Renderer
    {
        return $this->_renderer;
    }

    public function getPluginManager(): PluginManager
    {
        return $this->_pluginManager;
    }

    public function getPermissionManager(): PermissionManager
    {
        return $this->_permissionManager;
    }

    public function getProtocol(): Protocol
    {
        return $this->_protocol;
    }

    public function isAvailable(): bool
    {
        return $this->_runtime->isAvailable();
    }

    public function call(string $function, array $arguments = [])
    {
        return $this->_bridge->call($function, $arguments);
    }

    // ---- device APIs --------------------------------------------------

    public function getBiometrics(): Biometrics
    {
        return $this->api('biometrics');
    }

    public function getBrowser(): Browser
    {
        return $this->api('browser');
    }

    public function getCamera(): Camera
    {
        return $this->api('camera');
    }

    public function getClipboard(): Clipboard
    {
        return $this->api('clipboard');
    }

    public function getDevice(): Device
    {
        return $this->api('device');
    }

    public function getDialog(): Dialog
    {
        return $this->api('dialog');
    }

    public function getFilesystem(): Filesystem
    {
        return $this->api('filesystem');
    }

    public function getGeolocation(): Geolocation
    {
        return $this->api('geolocation');
    }

    public function getHaptics(): Haptics
    {
        return $this->api('haptics');
    }

    public function getMicrophone(): Microphone
    {
        return $this->api('microphone');
    }

    public function getNetwork(): Network
    {
        return $this->api('network');
    }

    public function getNotifications(): Notifications
    {
        return $this->api('notifications');
    }

    public function getScanner(): Scanner
    {
        return $this->api('scanner');
    }

    public function getSecureStorage(): SecureStorage
    {
        return $this->api('secureStorage');
    }

    public function getShare(): Share
    {
        return $this->api('share');
    }

    public function getSystem(): System
    {
        return $this->api('system');
    }

    /**
     * Resolve an API by its property name, honouring {@see $apis} overrides.
     */
    public function api(string $name): BaseApi
    {
        if (!isset($this->_apis[$name])) {
            $class = $this->apis[$name] ?? self::API_CLASSES[$name] ?? null;
            if ($class === null) {
                throw new InvalidConfigException("Unknown native API '{$name}'.");
            }
            $extra = ['bridge' => $this->_bridge];
            if ($name === 'system') {
                $extra['runtime'] = $this->_runtime;
            }
            $this->_apis[$name] = $this->make($class, $extra);
        }

        return $this->_apis[$name];
    }

    // ---- events -------------------------------------------------------

    /**
     * Deliver an event from the native shell to PHP handlers.
     *
     * Triggers `$type` and then {@see NativeEvents::ANY}.
     *
     * @return NativeEvent the event, so callers can read {@see NativeEvent::$response}
     */
    public function dispatch(string $type, array $payload = [], ?string $id = null): NativeEvent
    {
        $id ??= isset($payload['id']) && is_scalar($payload['id']) ? (string) $payload['id'] : null;
        $event = new NativeEvent(['type' => $type, 'payload' => $payload, 'id' => $id]);

        $this->trigger($type, $event);
        if (!$event->handled) {
            $this->trigger(NativeEvents::ANY, $event);
        }

        return $event;
    }

    /** Whether `$type` may be dispatched through the HTTP events endpoint. */
    public function isKnownEvent(string $type): bool
    {
        return $this->_protocol->hasEvent($type);
    }

    // ---- HTTP bridge policy -------------------------------------------

    /** Whether `$function` may be called through the HTTP bridge endpoint. */
    public function isHttpFunctionAllowed(string $function): bool
    {
        if (!$this->_protocol->hasFunction($function) && !$this->_bridge->hasHandler($function)) {
            return false;
        }
        foreach ((array) $this->http['deny'] as $pattern) {
            if (fnmatch($pattern, $function)) {
                return false;
            }
        }
        $allow = (array) $this->http['allow'];
        if ($allow === []) {
            return true;
        }
        foreach ($allow as $pattern) {
            if (fnmatch($pattern, $function)) {
                return true;
            }
        }

        return false;
    }

    // ---- testing ------------------------------------------------------

    /**
     * Replace the transport with an in-memory fake and mark the runtime as
     * available. Intended for tests and local development without a device.
     *
     * @param array<string,mixed> $responses function => value | callable | Throwable
     */
    public function fake(array $responses = []): FakeTransport
    {
        $fake = new FakeTransport(['responses' => $responses]);
        $this->_bridge->setTransport($fake);
        $this->_runtime->available = true;

        return $fake;
    }

    // ---- configuration ------------------------------------------------

    /** @return string[] human-readable configuration problems (empty when valid). */
    public function validate(): array
    {
        $errors = [];

        if (!preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+\z/', $this->appId)) {
            $errors[] = "appId '{$this->appId}' must be a reverse-DNS identifier such as com.example.app (lowercase).";
        }
        if (trim($this->name) === '') {
            $errors[] = 'name must not be empty.';
        }
        if (!preg_match('/^\d+\.\d+\.\d+([\-+][0-9A-Za-z.\-]+)?\z/', $this->version)) {
            $errors[] = "version '{$this->version}' must look like 1.2.3.";
        }
        if ($this->versionCode < 1) {
            $errors[] = 'versionCode must be a positive integer.';
        }
        if (!in_array($this->orientation, self::ORIENTATIONS, true)) {
            $errors[] = "orientation '{$this->orientation}' must be one of: " . implode(', ', self::ORIENTATIONS) . '.';
        }
        if ($this->deepLinkScheme !== null && !preg_match('/^[a-z][a-z0-9+.\-]*\z/', $this->deepLinkScheme)) {
            $errors[] = "deepLinkScheme '{$this->deepLinkScheme}' is not a valid URL scheme.";
        }
        foreach ($this->deepLinkHosts as $host) {
            if (!is_string($host) || !preg_match('/^[A-Za-z0-9.\-*]+\z/', $host)) {
                $errors[] = 'deepLinkHosts must contain plain host names.';
                break;
            }
        }
        foreach ($this->_permissionManager->unknown() as $unknown) {
            $errors[] = "Unknown permission '{$unknown}'. Known: " . implode(', ', array_keys(PermissionManager::DEFINITIONS)) . '.';
        }
        if (($this->http['secret'] ?? null) !== null && strlen((string) $this->http['secret']) < 16) {
            $errors[] = 'http.secret should be at least 16 characters.';
        }

        return $errors;
    }

    /**
     * @param string|array $definition class name or config array
     */
    private function make($definition, array $extra = [])
    {
        $config = is_array($definition) ? $definition : ['class' => $definition];

        return Yii::createObject(array_merge($config, $extra));
    }
}
