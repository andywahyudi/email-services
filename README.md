# Email Services Library

A robust PHP library for handling multiple email service providers with failover support. This library currently supports Mailgun, SendGrid, and Postmark with both API and SMTP implementations.

## Features

- Multiple email service provider support
- Automatic failover handling
- Support for both API and SMTP sending methods
- Attachment handling
- CC and BCC support
- HTML email support
- Easy configuration

## Requirements

- PHP 8.1 or higher
- Composer

## Installation

```bash
composer require email-services/library
```
## Configuration
Create a .env file in your project root (copy from .env.example ):

```bash
# Default Provider Configuration
DEFAULT_EMAIL_PROVIDER=mailgun  # Options: mailgun, sendgrid, postmark

# SendGrid Configuration
SENDGRID_API_KEY=your_sendgrid_api_key_here
SENDGRID_USE_SMTP=false
SENDGRID_FROM_ADDRESS=your_verified_sender@domain.com

# Mailgun Configuration
MAILGUN_API_KEY=your_mailgun_api_key_here
MAILGUN_DOMAIN=your_domain_here
MAILGUN_FROM_ADDRESS=your_sender@your_domain.com
MAILGUN_USE_SMTP=false

# Postmark Configuration
POSTMARK_SERVER_TOKEN=your_postmark_server_token_here
POSTMARK_FROM_ADDRESS=your_sender@your_domain.com
POSTMARK_USE_SMTP=false
```

## Usage
### Basic Usage with Default Provider
```php
use EmailServices\Library\EmailServiceManager;
use EmailServices\Library\Providers\MailgunService;
use EmailServices\Library\Providers\SendGridService;
use EmailServices\Library\Providers\PostmarkService;

// Initialize the Email Service Manager (will use DEFAULT_EMAIL_PROVIDER from .env)
$emailManager = new EmailServiceManager();

// Configure and add providers
$mailgunConfig = [
    'api_key' => getenv('MAILGUN_API_KEY'),
    'domain' => getenv('MAILGUN_DOMAIN'),
    'from_address' => getenv('MAILGUN_FROM_ADDRESS')
];

$sendGridConfig = [
    'api_key' => getenv('SENDGRID_API_KEY'),
    'from_address' => getenv('SENDGRID_FROM_ADDRESS')
];

$postmarkConfig = [
    'api_key' => getenv('POSTMARK_SERVER_TOKEN'),
    'from_address' => getenv('POSTMARK_FROM_ADDRESS')
];

// Add providers to the manager
$emailManager
    ->addProvider(new MailgunService($mailgunConfig))
    ->addProvider(new SendGridService($sendGridConfig))
    ->addProvider(new PostmarkService($postmarkConfig));

// Optionally change default provider at runtime
$emailManager->setDefaultProvider('sendgrid');

// Get current default provider
$currentProvider = $emailManager->getDefaultProvider();

// Get list of available providers
$availableProviders = $emailManager->getAvailableProviders();

// Send email (will use default provider first)
try {
    $emailManager->send(
        'recipient@example.com',
        'Test Subject',
        '<h1>Hello World!</h1>',
        ['from' => 'sender@yourdomain.com']
    );
} catch (\Exception $e) {
    echo "Failed to send email: " . $e->getMessage();
}
```
### Using SMTP
To use SMTP instead of API, update your configuration:

```php
$mailgunConfig = [
    'use_smtp' => true,
    'smtp_host' => 'smtp.mailgun.org',
    'smtp_port' => 587,
    'smtp_username' => 'your_smtp_username',
    'smtp_password' => 'your_smtp_password',
    'from_address' => 'your_sender@your_domain.com'
];
```
### Provider Failover
The library will:
1. Attempt to send using the default provider first (if configured).
2. If the default provider fails, it will automatically try other available providers.
3. If all providers fail, it will throw an exception with detailed error messages.

## Available Providers
1. Mailgun
   - API and SMTP support.
   - Domain verification required.
   - Supports attachments, CC, and BCC.

2. SendGrid
   - API and SMTP support.
   - Sender verification required.
   - Supports attachments, CC, and BCC.
   
3. Postmark
   - API and SMTP support.
   - Server token required.
   - Supports attachments, CC, and BCC.

## Error Handling
The library implements a failover system. If one provider fails, it automatically tries the next available provider. If all providers fail, an exception is thrown with detailed error messages from each provider.

## Contributing
Contributions are welcome! Please feel free to submit a Pull Request.

## License
This project is licensed under the MIT License.