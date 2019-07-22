<?php

namespace WebPush\Contracts;

use Serializable;

interface AuthorizationInterface extends Serializable
{
    /**
     * Returns a plaintext private key.
     *
     * @return string
     */
    public function getPrivateKey(): string;

    /**
     * Returns a plaintext public key.
     *
     * @return string
     */
    public function getPublicKey(): string;

    /**
     * Returns a mailto: email address or URI.
     *
     * @return string
     */
    public function getSubject(): string;
}
