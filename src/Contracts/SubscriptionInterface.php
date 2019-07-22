<?php

namespace WebPush\Contracts;

interface SubscriptionInterface
{
    /**
     * Returns a URI endpoint.
     *
     * @return string
     */
    public function getEndpoint(): string;

    /**
     * Returns an encoded (Base64Url) public key.
     *
     * @return string
     */
    public function getPublicKey(): string;

    /**
     * Returns an encoded (Base64Url) auth token.
     *
     * @return string
     */
    public function getAuthToken(): string;

    /**
     * Returns an encoding type.
     *
     * @return string
     */
    public function getEncoding(): string;
}
