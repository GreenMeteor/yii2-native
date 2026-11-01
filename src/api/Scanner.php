<?php

namespace greenmeteor\native\api;

use yii\base\InvalidArgumentException;

class Scanner extends BaseApi
{
    public const FORMATS = ['qr', 'ean13', 'ean8', 'code128', 'code39', 'upca', 'upce', 'pdf417', 'aztec', 'datamatrix'];

    public static function apiName(): string
    {
        return 'Scanner';
    }

    /**
     * Open the scanner. Result: `NativeEvents::SCANNER_SCANNED`.
     *
     * @param string[] $formats see {@see FORMATS}
     * @return string request id
     */
    public function scan(array $formats = ['qr'], ?string $prompt = null, ?string $id = null): string
    {
        if ($formats === []) {
            throw new InvalidArgumentException('At least one barcode format is required.');
        }
        foreach ($formats as $format) {
            $this->assertOneOf((string) $format, self::FORMATS, 'barcode format');
        }
        $id = $this->requestId($id);
        $this->invoke('scan', $this->args(['formats' => array_values($formats), 'prompt' => $prompt, 'id' => $id]));

        return $id;
    }

    public function cancel(): void
    {
        $this->invoke('cancel');
    }
}
