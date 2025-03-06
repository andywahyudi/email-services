<?php

namespace EmailServices\Library;

use EmailServices\Library\Contracts\EmailServiceInterface;

class EmailServiceManager
{
    /**
     * List of email service providers
     *
     * @var EmailServiceInterface[]
     */
    private array $providers = [];

    /**
     * Add an email service provider
     *
     * @param EmailServiceInterface $provider
     * @return self
     */
    public function addProvider(EmailServiceInterface $provider): self
    {
        $this->providers[] = $provider;
        return $this;
    }

    /**
     * Send an email using available providers with failover support
     *
     * @param string $to
     * @param string $subject
     * @param string $body
     * @param array $options
     * @return bool
     * @throws \Exception When all providers fail
     */
    public function send(string $to, string $subject, string $body, array $options = []): bool
    {
        if (empty($this->providers)) {
            throw new \Exception('No email service providers configured');
        }

        $exceptions = [];

        foreach ($this->providers as $provider) {
            try {
                if ($provider->isAvailable()) {
                    $result = $provider->send($to, $subject, $body, $options);
                    if ($result) {
                        return true;
                    }
                }
            } catch (\Exception $e) {
                $exceptions[] = sprintf('[%s] %s', $provider->getProviderName(), $e->getMessage());
            }
        }

        throw new \Exception(
            'All email service providers failed. Errors: ' . implode('; ', $exceptions)
        );
    }

    /**
     * Get all configured providers
     *
     * @return EmailServiceInterface[]
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    /**
     * Remove all providers
     *
     * @return self
     */
    public function clearProviders(): self
    {
        $this->providers = [];
        return $this;
    }
}