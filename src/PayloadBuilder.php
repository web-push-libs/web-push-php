<?php

namespace WebPush;

use ErrorException;

class PayloadBuilder
{
    /**
     * @param Contracts\SubscriptionInterface $subscription
     * @param string $payload
     * @param int $padding
     *
     * @return Payload
     * @throws ErrorException
     */
    public function build(Contracts\SubscriptionInterface $subscription, string $payload, int $padding): Payload
    {
        if (empty($payload)) {
            return new Payload('', '', '');
        }

        $this->validate($payload);
        $payload = $this->pad($payload, $subscription->getEncoding(), $padding);

        [$cipher, $salt, $localPublicKey] = $this->encrypt($payload, $subscription);

        $prefix = Encryption::getContentCodingHeader($salt, $localPublicKey, $subscription->getEncoding());
        $payload = $prefix . $cipher;

        return new Payload($payload, $salt, $localPublicKey);
    }

    /**
     * @param string $payload
     * @param Contracts\SubscriptionInterface $subscription
     *
     * @return array
     * @throws ErrorException
     */
    private function encrypt(string $payload, Contracts\SubscriptionInterface $subscription): array
    {
        $encrypted = Encryption::encrypt(
            $payload,
            $subscription->getPublicKey(),
            $subscription->getAuthToken(),
            $subscription->getEncoding()
        );

        return [
            $encrypted['cipherText'], $encrypted['salt'], $encrypted['localPublicKey']
        ];
    }

    /**
     * @param string $payload
     *
     * @throws ErrorException
     */
    private function validate(string $payload): void
    {
        if (mb_strlen($payload, '8bit') > Encryption::MAX_PAYLOAD_LENGTH) {
            throw new ErrorException(
                'Size of payload must not be greater than ' . Encryption::MAX_PAYLOAD_LENGTH . ' octets.'
            );
        }
    }

    /**
     * @param string $payload
     * @param string $encoding
     * @param int $padding
     *
     * @return string
     * @throws ErrorException
     */
    private function pad(string $payload, string $encoding, int $padding): string
    {
        return $padding === 0 ? $payload : Encryption::padPayload($payload, $padding, $encoding);
    }
}
