<?php

namespace EmailServices\Library\Contracts;

interface EmailServiceInterface
{
    /**
     * Send an email
     *
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $body Email body content
     * @param array $options Additional options (from, cc, bcc, attachments, etc)
     * @return bool Returns true if email was sent successfully
     * @throws \Exception If sending fails
     */
    public function send(string $to, string $subject, string $body, array $options = []): bool;

    /**
     * Check if the service is currently available
     *
     * @return bool Returns true if service is available
     */
    public function isAvailable(): bool;

    /**
     * Get the name of the email service provider
     *
     * @return string
     */
    public function getProviderName(): string;
}