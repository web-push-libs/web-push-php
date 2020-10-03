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
use Minishlink\WebPush\Base64Url;
use Psr\Http\Message\RequestInterface;

final class AESGCM extends AbstractAESGCM implements ContentEncoding
{
    private const ENCODING = 'aesgcm';
    private const PADDING_MAX = 4078;

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
        return 'Content-Encoding: auth'.chr(0);
    }

    protected function getContext(string $userAgentPublicKey, ServerKey $serverKey): string
    {
        $context = 'P-256';
        $context .= chr(0);
        $context .= chr(0);
        $context .= chr(65);
        $context .= $userAgentPublicKey;
        $context .= chr(0);
        $context .= chr(65);
        $context .= $serverKey->getPublicKey();

        return $context;
    }

    protected function addPadding(string $payload): string
    {
        $payloadLength = mb_strlen($payload, '8bit');
        $paddingLength = $this->padding - $payloadLength;

        return pack('n*', $paddingLength).str_pad($payload, $this->padding, chr(0), STR_PAD_LEFT);
    }

    protected function prepareRequest(RequestInterface $request, string $salt): RequestInterface
    {
        return $request
            ->withHeader('Encryption', 'salt='.Base64Url::encode($salt))
        ;
    }

    protected function prepareBody(string $encryptedText, ServerKey $serverKey, string $tag, string $salt): string
    {
        return $encryptedText.$tag;
    }
}
