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

use Assert\Assertion;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Minishlink\WebPush\Base64Url;
use Minishlink\WebPush\Loggable;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use function Safe\hex2bin;
use function Safe\json_encode;

final class WebTokenProvider implements JWSProvider, Loggable
{
    private const PUBLIC_KEY_SIZE = 65;
    private const COMPONENT_SIZE = 32;

    private JWK $signatureKey;
    private CompactSerializer $serializer;
    private JWSBuilder $jwsBuilder;
    private LoggerInterface $logger;

    public function __construct(string $publicKey, string $privateKey)
    {
        $privateKeyBin = Base64Url::decode($privateKey);
        Assertion::eq(mb_strlen($privateKeyBin, '8bit'), self::COMPONENT_SIZE, 'Invalid private key size');

        $publicKeyBin = Base64Url::decode($publicKey);
        Assertion::eq(mb_strlen($publicKeyBin, '8bit'), self::PUBLIC_KEY_SIZE, 'Invalid public key size', );
        Assertion::startsWith($publicKeyBin, "\4", 'Invalid public key', null, '8bit');
        $x = mb_substr($publicKeyBin, 1, self::COMPONENT_SIZE, '8bit');
        $y = mb_substr($publicKeyBin, -self::COMPONENT_SIZE, null, '8bit');

        $jwk = new JWK([
            'kty' => 'EC',
            'crv' => 'P-256',
            'd' => $privateKey,
            'x' => Base64Url::encode($x),
            'y' => Base64Url::encode($y),
        ]);

        $this->signatureKey = $jwk;
        $algorithmManager = new AlgorithmManager([new ES256()]);
        $this->serializer = new CompactSerializer();
        $this->jwsBuilder = new JWSBuilder($algorithmManager);
        $this->logger = new NullLogger();
    }

    public static function create(string $publicKey, string $privateKey): self
    {
        return new self($publicKey, $privateKey);
    }

    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    public function computeHeader(array $claims): Header
    {
        $this->logger->debug('Computing the JWS');
        $payload = json_encode($claims);
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
