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

namespace Minishlink\Tests\Benchmark;

use Minishlink\WebPush\VAPID\JWSProvider;
use Minishlink\WebPush\VAPID\WebTokenProvider;

class WebTokenBench extends AbstractBench
{
    protected function jwtProvider(): JWSProvider
    {
        $publicKey = 'BNFEvAnv7SfVGz42xFvdcu-z-W_3FVm_yRSGbEVtxVRRXqCBYJtvngQ8ZN-9bzzamxLjpbw7vuHcHTT2H98LwLM';
        $privateKey = 'TcP5-SlbNbThgntDB7TQHXLslhaxav8Qqdd_Ar7VuNo';

        return WebTokenProvider::create($publicKey, $privateKey);
    }
}
