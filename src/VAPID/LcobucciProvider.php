<?php

declare(strict_types=1);

/*
 * The MIT License (MIT)
 *
 * Copyright (c) 2020 Spomky-Labs
 *
 * This software may be modified and distributed under the terms
 * of the MIT license.  See the LICENSE file for details.
 */

namespace Minishlink\WebPush\VAPID;

use DateTimeInterface;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Ecdsa\Sha256;
use Lcobucci\JWT\Signer\Key;
use Minishlink\WebPush\Utils;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class LcobucciProvider implements JWSProvider
{
    private string $audience;
    private string $subject;
    private string $serializedPublicKey;
    private LoggerInterface $logger;

    public function __construct(string $audience, string $subject, Key $signatureKey)
    {
        $this->audience = $audience;
        $this->subject = $subject;
        $this->serializedPublicKey = Utils::serializePublicKey(
            $signatureKey->getContent(),
            $signatureKey->getPassphrase() ?? ''
        );
        $this->logger = new NullLogger();
    }

    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    public function computeHeader(DateTimeInterface $expiresAt): Header
    {
        $this->logger->debug('Computing the JWS');
        $signer = new Sha256();
        $token = (new Builder())
            ->withClaim('aud', $this->audience)
            ->withClaim('sub', $this->subject)
            ->expiresAt($expiresAt->getTimestamp())
            ->withHeader('typ', 'JWT')
            ->withHeader('alg', 'ES256')
            ->getToken($signer)
            ->__toString()
        ;

        $this->logger->debug('JWS computed', ['token' => $token, 'key' => $this->serializedPublicKey]);

        return new Header(
            $token,
            $this->serializedPublicKey
        );
    }
}
