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
use Minishlink\WebPush\Extension;
use Minishlink\WebPush\Notification;
use Minishlink\WebPush\Subscription;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use function Safe\sprintf;

class PayloadExtension implements Extension
{
    /**
     * @var ContentEncoding[]
     */
    private array $contentEncodings = [];
    private LoggerInterface $logger;

    public function __construct()
    {
        $this->logger = new NullLogger();
    }

    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    public function addContentEncoding(ContentEncoding $contentEncoding): self
    {
        $this->contentEncodings[$contentEncoding->name()] = $contentEncoding;

        return $this;
    }

    public function process(RequestInterface $request, Notification $notification, Subscription $subscription): RequestInterface
    {
        $this->logger->debug('Processing with payload');
        $payload = $notification->getPayload();
        if (null === $payload) {
            $this->logger->debug('No payload');

            return $request
                ->withHeader('Content-Length', '0')
            ;
        }

        $contentEncoding = $subscription->getContentEncoding();
        Assertion::keyExists($this->contentEncodings, $contentEncoding, sprintf('The content encoding "%s" is not supported', $contentEncoding));
        $encoder = $this->contentEncodings[$contentEncoding];
        $this->logger->debug(sprintf('Encoder found: %s. Processing with the encoder.', $contentEncoding));

        return $encoder
            ->encode($payload, $request, $subscription)
            ->withHeader('Content-Type', 'application/octet-stream')
            ->withHeader('Content-Encoding', $contentEncoding)
        ;
    }
}
