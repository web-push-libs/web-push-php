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

namespace Minishlink\WebPush\Payload;

use Assert\Assertion;
use Psr\Http\Message\RequestInterface;
use function Safe\pack;

final class AES128GCM extends AbstractAESGCM
{
    private const ENCODING = 'aes128gcm';
    private const PADDING_MAX = 3993; // as per RFC8291: 4096 -tag(16) -salt(16) -rs(4) -idlen(1) -keyid(65) -AEAD_AES_128_GCM expension(16) and 1 byte in case of

    public static function create(): self
    {
        return new self();
    }

    public function customPadding(int $padding): self
    {
        Assertion::range($padding, self::PADDING_NONE, self::PADDING_MAX, 'Invalid padding size');
        $this->padding = $padding;

        return $this;
    }

    public function maxPadding(): self
    {
        $this->padding = self::PADDING_MAX;

        return $this;
    }

    public function name(): string
    {
        return self::ENCODING;
    }

    protected function getKeyInfo(string $userAgentPublicKey, ServerKey $serverKey): string
    {
        return 'WebPush: info'."\0".$userAgentPublicKey.$serverKey->getPublicKey();
    }

    protected function getContext(string $userAgentPublicKey, ServerKey $serverKey): string
    {
        return '';
    }

    protected function addPadding(string $payload): string
    {
        return str_pad($payload."\2", $this->padding, "\0", STR_PAD_RIGHT);
    }

    protected function prepareRequest(RequestInterface $request, string $salt): RequestInterface
    {
        return $request;
    }

    protected function prepareBody(string $encryptedText, ServerKey $serverKey, string $tag, string $salt): string
    {
        $body = $salt.pack('N*', 4096).pack('C*', mb_strlen($serverKey->getPublicKey(), '8bit')).$serverKey->getPublicKey();
        $body .= $encryptedText.$tag;

        return $body;
    }
}
