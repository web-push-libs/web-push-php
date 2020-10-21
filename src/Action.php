<?php

declare(strict_types=1);

/*
 * This file is part of the WebPush library.
 *
 * (c) Louis Lagrange <lagrange.louis@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Minishlink\WebPush;

use JsonSerializable;
use function Safe\json_encode;

/**
 * @see https://notifications.spec.whatwg.org/#actions
 */
class Action implements JsonSerializable
{
    private string $action;
    private string $title;
    private ?string $icon;

    public function __construct(string $action, string $title, ?string $icon)
    {
        $this->action = $action;
        $this->title = $title;
        $this->icon = $icon;
    }

    public function __toString(): string
    {
        return json_encode($this, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function jsonSerialize(): array
    {
        return array_filter(get_object_vars($this), static function ($v): bool {
            return null !== $v;
        });
    }
}
