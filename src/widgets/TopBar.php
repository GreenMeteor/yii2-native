<?php

namespace greenmeteor\native\widgets;

use yii\base\InvalidArgumentException;

class TopBar extends Widget
{
    public string $title = '';
    public ?string $subtitle = null;
    public ?bool $showBack = null;
    /** @var array<int,array{icon?:string,label?:string,action:string}> */
    public array $actions = [];

    public function getElementType(): string
    {
        return 'topbar';
    }

    protected function elementProperties(): array
    {
        foreach ($this->actions as $action) {
            if (!is_array($action) || empty($action['action'])) {
                throw new InvalidArgumentException("Each TopBar action needs an 'action' name.");
            }
        }

        return [
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'showBack' => $this->showBack,
            'actions' => $this->actions ?: null,
        ];
    }
}
