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

namespace Minishlink\Tests\Unit;

use Minishlink\WebPush\Action;
use PHPUnit\Framework\TestCase;
use function Safe\json_encode;

/**
 * @internal
 */
final class ActionTest extends TestCase
{
    /**
     * @test
     */
    public function createAction(): void
    {
        $action = Action::create('ACTION', '---TITLE---');

        static::assertEquals('ACTION', $action->getAction());
        static::assertEquals('---TITLE---', $action->getTitle());
        static::assertNull($action->getIcon());

        $expectedJson = '{"action":"ACTION","title":"---TITLE---"}';
        static::assertEquals($expectedJson, (string) $action);
        static::assertEquals($expectedJson, json_encode($action, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @test
     */
    public function createActionWithIcon(): void
    {
        $action = Action::create('ACTION', '---TITLE---')
            ->withIcon('https://icon.ico')
        ;

        static::assertEquals('ACTION', $action->getAction());
        static::assertEquals('---TITLE---', $action->getTitle());
        static::assertEquals('https://icon.ico', $action->getIcon());

        $expectedJson = '{"action":"ACTION","icon":"https://icon.ico","title":"---TITLE---"}';
        static::assertEquals($expectedJson, (string) $action);
        static::assertEquals($expectedJson, json_encode($action, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
