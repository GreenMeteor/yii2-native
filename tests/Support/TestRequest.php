<?php

namespace greenmeteor\native\tests\Support;

use yii\web\Request;

/** Request whose body is injectable and which never reads php://input. */
class TestRequest extends Request
{
    public string $body = '';

    public function getRawBody(): string
    {
        return $this->body;
    }
}
