<?php

namespace greenmeteor\native\bridge;

use greenmeteor\native\events\NativeCallEvent;
use greenmeteor\native\exceptions\NativeUnavailableException;
use greenmeteor\native\permission\PermissionManager;
use greenmeteor\native\protocol\ArgumentValidator;
use greenmeteor\native\protocol\Protocol;
use greenmeteor\native\runtime\Runtime;
use Yii;
use yii\base\Component;
use yii\base\InvalidArgumentException;

/**
 * Named-function bridge between PHP and the native shell.
 *
 * Call order for `call()`:
 *
 * 1. validate the function name
 * 2. `beforeCall` (a listener may set `handled` + `result` to short-circuit)
 * 3. permission check and argument validation against protocol.json
 * 4. PHP-side handler registered with {@see register()}, otherwise the transport
 * 5. `afterCall`, or `error` followed by a re-throw
 *
 * Arguments are never logged because they may contain secrets.
 */
class NativeBridge extends Component
{
    public const EVENT_BEFORE_CALL = 'beforeCall';
    public const EVENT_AFTER_CALL = 'afterCall';
    public const EVENT_ERROR = 'error';

    private const NAME_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)*\z/';
    private const MAX_NAME_LENGTH = 128;

    /** When the native runtime is missing, return null instead of throwing. */
    public bool $allowUnavailable = false;
    /** Validate arguments of functions declared in protocol.json (types, enums, URLs, unknown keys). */
    public bool $validateArguments = true;
    public ?Runtime $runtime = null;
    public ?Protocol $protocol = null;
    public ?PermissionManager $permissions = null;

    private ?BridgeTransportInterface $_transport = null;
    /** @var array<string,callable> */
    private array $handlers = [];

    /** @param BridgeTransportInterface|array|string $transport object, Yii config array or class name */
    public function setTransport($transport): void
    {
        if (!$transport instanceof BridgeTransportInterface) {
            $transport = Yii::createObject($transport);
        }
        if (!$transport instanceof BridgeTransportInterface) {
            throw new InvalidArgumentException('Bridge transport must implement ' . BridgeTransportInterface::class . '.');
        }
        $this->_transport = $transport;
    }

    public function getTransport(): BridgeTransportInterface
    {
        return $this->_transport ??= new FunctionTransport();
    }

    /**
     * Register a PHP-side implementation of a bridge function, e.g. for a
     * plugin. It runs instead of the transport and works without a runtime.
     *
     * @param callable $handler `function (array $arguments, string $function): mixed`
     */
    public function register(string $function, callable $handler): void
    {
        $this->assertValidName($function);
        $this->handlers[$function] = $handler;
    }

    public function unregister(string $function): void
    {
        unset($this->handlers[$function]);
    }

    public function hasHandler(string $function): bool
    {
        return isset($this->handlers[$function]);
    }

    /** Whether calls can currently reach the native side. */
    public function isAvailable(): bool
    {
        return ($this->runtime !== null && $this->runtime->isAvailable()) || $this->getTransport()->isAvailable();
    }

    public function call(string $function, array $arguments = [])
    {
        $this->assertValidName($function);

        $event = new NativeCallEvent([
            'function' => $function,
            'arguments' => $arguments,
        ]);

        $start = microtime(true);
        $this->trigger(self::EVENT_BEFORE_CALL, $event);

        try {
            if ($event->handled) {
                $result = $event->result;
            } else {
                $this->permissions?->assertAllowed($function);
                $this->validate($function, $arguments);
                $result = $this->dispatch($function, $arguments);
            }

            $event->result = $result;
            $event->duration = (microtime(true) - $start) * 1000;
            $this->trigger(self::EVENT_AFTER_CALL, $event);
            Yii::debug(sprintf('native call %s (%.1f ms)', $function, $event->duration), 'greenmeteor.native.bridge');

            return $result;
        } catch (\Throwable $e) {
            $event->error = $e;
            $event->duration = (microtime(true) - $start) * 1000;
            $this->trigger(self::EVENT_ERROR, $event);

            throw $e;
        }
    }

    protected function dispatch(string $function, array $arguments)
    {
        if (isset($this->handlers[$function])) {
            return ($this->handlers[$function])($arguments, $function);
        }

        if ($this->isAvailable()) {
            return $this->getTransport()->send($function, $arguments);
        }

        if (!$this->allowUnavailable) {
            throw new NativeUnavailableException(
                'The Yii2 Native runtime is not available. '
                . 'This bridge call requires the native mobile runtime.'
            );
        }

        return null;
    }

    private function validate(string $function, array $arguments): void
    {
        if (!$this->validateArguments || $this->protocol === null) {
            return;
        }
        $spec = $this->protocol->getFunction($function);
        if ($spec !== null && isset($spec['params'])) {
            ArgumentValidator::validate($function, $spec, $arguments);
        }
    }

    private function assertValidName(string $function): void
    {
        if ($function === '') {
            throw new InvalidArgumentException('Native function name cannot be empty.');
        }
        if (strlen($function) > self::MAX_NAME_LENGTH || !preg_match(self::NAME_PATTERN, $function)) {
            throw new InvalidArgumentException("Invalid native function name '{$function}'. Expected Namespace.method.");
        }
    }
}
