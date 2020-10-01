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

use Jose\Component\Core\JWK;
use Minishlink\WebPush\VAPID\JWSProvider;
use Minishlink\WebPush\VAPID\WebTokenProvider;

class WebTokenBench extends AbstractBench
{
    protected function jwtProvider(): JWSProvider
    {
        $vapidKey = JWK::createFromJson('{"kty":"EC","crv":"P-256","d":"fiDSHFnef96_AX-BI5m6Ew2uiW-CIqoKtKnrIAeDRMI","x":"Xea1H6hwYhGqE4vBHcW8knbx9sNZsnXHwgikrpWyLQI","y":"Kl7gDKfzYe_TFJWHxDNDU1nhBB2nzx9OTlGcF4G7Z2w"}');

        return new WebTokenProvider($vapidKey);
    }
}
