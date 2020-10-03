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

use function chr;
use Psr\Http\Message\RequestInterface;

final class AES128GCM extends AbstractAESGCM implements ContentEncoding
{
    private const ENCODING = 'aes128gcm';
    private const PADDING_MAX = 3993; // as per RFC8291: 4096 -tag(16) -salt(16) -rs(4) -idlen(1) -keyid(65) -AEAD_AES_128_GCM expension(16) and 1 byte in case of

    public function maxPadding(): self
    {
        $this->padding = self::PADDING_MAX;

        return $this;
    }

    public function name(): string
    {
        return self::ENCODING;
    }

    protected function getKeyInfo(string $userAgentPublicKey): string
    {
        return 'WebPush: info'.chr(0).$userAgentPublicKey.$this->serverPublicKey;
    }

    protected function getContext(string $userAgentPublicKey): string
    {
        return '';
    }

    protected function addPadding(string $payload): string
    {
        return str_pad($payload.chr(2), $this->padding, chr(0), STR_PAD_RIGHT);
    }

    protected function prepareRequest(RequestInterface $request, string $salt): RequestInterface
    {
        return $request;
    }

    protected function prepareBody(string $encryptedText, string $tag, string $salt): string
    {
        $body = $salt.pack('N*', 4096).pack('C*', mb_strlen($this->serverPublicKey, '8bit')).$this->serverPublicKey;
        $body .= $encryptedText.$tag;

        return $body;
    }
}
