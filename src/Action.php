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
use function Safe\ksort;

/**
 * @see https://notifications.spec.whatwg.org/#actions
 */
class Action implements JsonSerializable
{
    private string $action;
    private string $title;
    private ?string $icon = null;

    public function __construct(string $action, string $title)
    {
        $this->action = $action;
        $this->title = $title;
    }

    public function __toString(): string
    {
        return json_encode($this, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function create(string $action, string $title): self
    {
        return new self($action, $title);
    }

    public function withIcon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
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
        $r = array_filter(get_object_vars($this), static function ($v): bool {
            return null !== $v;
        });

        ksort($r);

        return $r;
    }
}
