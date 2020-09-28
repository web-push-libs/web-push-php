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
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Minishlink\WebPush\Base64Url;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use function Safe\hex2bin;
use function Safe\json_encode;

final class WebTokenProvider implements JWSProvider
{
    private JWK $signatureKey;
    private CompactSerializer $serializer;
    private JWSBuilder $jwsBuilder;
    private string $audience;
    private string $subject;
    private LoggerInterface $logger;

    public function __construct(string $audience, string $subject, JWK $signatureKey)
    {
        $this->signatureKey = $signatureKey;
        $algorithmManager = new AlgorithmManager([new ES256()]);
        $this->serializer = new CompactSerializer();
        $this->jwsBuilder = new JWSBuilder($algorithmManager);
        $this->audience = $audience;
        $this->subject = $subject;
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
        $payload = json_encode([
            'aud' => $this->audience,
            'sub' => $this->subject,
            'exp' => $expiresAt->getTimestamp(),
        ]);
        $jws = $this->jwsBuilder->create()
            ->withPayload($payload)
            ->addSignature($this->signatureKey, ['typ' => 'JWT', 'alg' => 'ES256'])
            ->build()
        ;
        $token = $this->serializer->serialize($jws);
        $key = $this->serializePublicKey();
        $this->logger->debug('JWS computed', ['token' => $token, 'key' => $key]);

        return new Header(
            $token,
            $key
        );
    }

    private function serializePublicKey(): string
    {
        $hexString = '04';
        $hexString .= bin2hex(Base64Url::decode($this->signatureKey->get('x')));
        $hexString .= bin2hex(Base64Url::decode($this->signatureKey->get('y')));

        return Base64Url::encode(hex2bin($hexString));
    }
}
