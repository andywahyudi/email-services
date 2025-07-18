<?php

namespace MultiEmailServices\Library;

use MultiEmailServices\Library\Contracts\EmailServiceInterface;

abstract class AbstractEmailService implements EmailServiceInterface
{
    /**
     * Configuration options for the email service
     *
     * @var array
     */
    protected array $config;

    /**
     * Constructor
     *
     * @param array $config Configuration options for the email service
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Validate basic email parameters
     *
     * @param string $to
     * @param string $subject
     * @param string $body
     * @throws \InvalidArgumentException
     */
    protected function validateEmailParams(string $to, string $subject, string $body): void
    {
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid recipient email address');
        }

        if (empty($subject)) {
            throw new \InvalidArgumentException('Email subject cannot be empty');
        }

        if (empty($body)) {
            throw new \InvalidArgumentException('Email body cannot be empty');
        }
    }

    /**
     * Format email addresses from array or string
     *
     * @param string|array $emails
     * @return array
     */
    protected function formatEmailAddresses($emails): array
    {
        if (is_string($emails)) {
            return [['email' => $emails]];
        }

        return array_map(function($email) {
            return is_array($email) ? $email : ['email' => $email];
        }, (array) $emails);
    }

    /**
     * Get configuration value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function getConfig(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }
}