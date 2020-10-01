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

namespace Minishlink\WebPush\VAPID;

use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Ecdsa\Sha256;
use Lcobucci\JWT\Signer\Key;
use Minishlink\WebPush\Base64Url;
use Minishlink\WebPush\Utils;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class LcobucciProvider implements JWSProvider
{
    private string $publicKey;
    private LoggerInterface $logger;
    private Key $key;

    public function __construct(string $publicKey, string $privateKey)
    {
        $this->publicKey = $publicKey;
        $pem = Utils::privateKeyToPEM(
            Base64Url::decode($privateKey),
            Base64Url::decode($publicKey)
        );
        $this->key = new Key($pem);
        $this->logger = new NullLogger();
    }

    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    public function computeHeader(array $claims): Header
    {
        $this->logger->debug('Computing the JWS');
        $signer = new Sha256();
        $builder = (new Builder())
            ->withHeader('typ', 'JWT')
            ->withHeader('alg', 'ES256')
        ;
        foreach ($claims as $k => $v) {
            $builder->withClaim($k, $v);
        }
        $token = $builder
            ->getToken($signer, $this->key)
            ->__toString()
        ;

        $this->logger->debug('JWS computed', ['token' => $token, 'key' => $this->publicKey]);

        return new Header(
            $token,
            $this->publicKey
        );
    }
}
