<?php

namespace EmailServices\Library;

use EmailServices\Library\Contracts\EmailServiceInterface;

class EmailServiceManager
{
    private array $providers = [];
    private ?string $defaultProvider;

    public function __construct(?string $defaultProvider = null)
    {
        $this->defaultProvider = strtolower($defaultProvider ?? getenv('DEFAULT_EMAIL_PROVIDER') ?: '');
    }

    public function addProvider(EmailServiceInterface $provider): self
    {
        $name = strtolower($provider->getProviderName());
        $this->providers[$name] = $provider;
        return $this;
    }

    public function setDefaultProvider(string $providerName): self
    {
        $providerName = strtolower($providerName);
        if (!isset($this->providers[$providerName])) {
            throw new \Exception("Provider '{$providerName}' not found");
        }
        $this->defaultProvider = $providerName;
        return $this;
    }

    public function getDefaultProvider(): ?string
    {
        return $this->defaultProvider;
    }

    public function send(string $to, string $subject, string $body, array $options = []): bool
    {
        if (empty($this->providers)) {
            throw new \Exception('No email service providers configured');
        }

        $exceptions = [];
        
        // Try default provider first if set
        if ($this->defaultProvider && isset($this->providers[$this->defaultProvider])) {
            try {
                $provider = $this->providers[$this->defaultProvider];
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

        // If default provider fails or not set, try others as fallback
        foreach ($this->providers as $name => $provider) {
            // Skip if this is the default provider we already tried
            if ($name === $this->defaultProvider) {
                continue;
            }

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

    public function getProviders(): array
    {
        return $this->providers;
    }

    public function clearProviders(): self
    {
        $this->providers = [];
        $this->defaultProvider = null;
        return $this;
    }

    public function getAvailableProviders(): array
    {
        $available = [];
        foreach ($this->providers as $name => $provider) {
            if ($provider->isAvailable()) {
                $available[$name] = $provider;
            }
        }
        return $available;
    }
}