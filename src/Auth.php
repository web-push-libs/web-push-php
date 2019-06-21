<?php

namespace Minishlink\WebPush;

use InvalidArgumentException;

class Auth
{
    /**
     * @var string
     */
    private $privateKey;
    /**
     * @var string
     */
    private $publicKey;
    /**
     * @var string
     */
    private $subject;

    /**
     * @param string $private_key
     * @param string $public_key
     * @param string $subject
     *
     * @throws InvalidArgumentException
     */
    public function __construct(string $private_key, string $public_key, string $subject)
    {
        $this->setPrivateKey($private_key);
        $this->setPublicKey($public_key);
        $this->setSubject($subject);
    }

    /**
     * @param string $private_key
     *
     * @throws InvalidArgumentException
     */
    private function setPrivateKey(string $private_key): void
    {
        if (mb_strlen($private_key, '8bit') !== 32) {
            throw new InvalidArgumentException('Invalid private key provided');
        }

        $this->privateKey = $private_key;
    }

    /**
     * @param string $public_key
     *
     * @throws InvalidArgumentException
     */
    private function setPublicKey(string $public_key): void
    {
        if (mb_strlen($public_key, '8bit') !== 32) {
            throw new InvalidArgumentException('Invalid public key provided');
        }

        $this->publicKey = $public_key;
    }

    /**
     * @param string $subject
     *
     * @throws InvalidArgumentException
     */
    private function setSubject(string $subject): void
    {
        strpos($subject, 'mailto:') === 0 ? $this->validateMailto($subject) : $this->validateUrl($subject);
        $this->subject = $subject;
    }

    /**
     * @param string $subject
     *
     * @throws InvalidArgumentException
     */
    private function validateMailto(string $subject): void
    {
        $email = explode(':', $subject)[1];
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Invalid subject provided');
        }
    }

    /**
     * @param string $subject
     *
     * @throws InvalidArgumentException
     */
    private function validateUrl(string $subject): void
    {
        if (filter_var($subject, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('Invalid subject provided');
        }
    }

    public function toArray(): array
    {
        return [
            'subject' => $this->subject,
            'public_key' => $this->publicKey,
            'private_key' => $this->privateKey
        ];
    }
}
