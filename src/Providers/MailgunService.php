<?php

namespace EmailServices\Library\Providers;

use EmailServices\Library\AbstractEmailService;
use Mailgun\Mailgun;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class MailgunService extends AbstractEmailService
{
    private Mailgun $client;
    private ?PHPMailer $mailer = null;
    private bool $useSmtp = false;

    public function __construct(array $config)
    {
        parent::__construct($config);
        
        $this->useSmtp = $this->getConfig('use_smtp', false);
        
        if ($this->useSmtp) {
            $this->mailer = new PHPMailer(true);
            $this->setupSmtp();
        } else {
            $this->client = Mailgun::create($this->getConfig('api_key'));
        }
    }

    private function setupSmtp(): void
    {
        try {
            $this->mailer->isSMTP();
            $this->mailer->Host = $this->getConfig('smtp_host', 'smtp.mailgun.org');
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $this->getConfig('smtp_username');
            $this->mailer->Password = $this->getConfig('smtp_password');
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mailer->Port = $this->getConfig('smtp_port', 587);
        } catch (PHPMailerException $e) {
            throw new \Exception('SMTP setup failed: ' . $e->getMessage());
        }
    }

    public function send(string $to, string $subject, string $body, array $options = []): bool
    {
        $this->validateEmailParams($to, $subject, $body);

        if ($this->useSmtp) {
            return $this->sendViaSmtp($to, $subject, $body, $options);
        }

        return $this->sendViaApi($to, $subject, $body, $options);
    }

    private function sendViaSmtp(string $to, string $subject, string $body, array $options = []): bool
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->clearCCs();
            $this->mailer->clearBCCs();
            $this->mailer->clearAttachments();

            $from = $options['from'] ?? $this->getConfig('from_address');
            $fromName = $options['from_name'] ?? $this->getConfig('from_name', '');
            
            $this->mailer->setFrom($from, $fromName);
            $this->mailer->addAddress($to);
            $this->mailer->Subject = $subject;
            $this->mailer->isHTML(true);
            $this->mailer->Body = $body;

            if (!empty($options['cc'])) {
                foreach ((array)$options['cc'] as $cc) {
                    $this->mailer->addCC($cc);
                }
            }

            if (!empty($options['bcc'])) {
                foreach ((array)$options['bcc'] as $bcc) {
                    $this->mailer->addBCC($bcc);
                }
            }

            if (!empty($options['attachments'])) {
                foreach ($options['attachments'] as $attachment) {
                    $this->mailer->addAttachment($attachment);
                }
            }

            return $this->mailer->send();
        } catch (PHPMailerException $e) {
            throw new \Exception('Failed to send email via SMTP: ' . $e->getMessage());
        }
    }

    private function sendViaApi(string $to, string $subject, string $body, array $options = []): bool
    {
        try {
            $domain = $this->getConfig('domain');
            $from = $options['from'] ?? $this->getConfig('from_address');

            $params = [
                'from' => $from,
                'to' => $to,
                'subject' => $subject,
                'html' => $body
            ];

            if (!empty($options['cc'])) {
                $params['cc'] = $options['cc'];
            }

            if (!empty($options['bcc'])) {
                $params['bcc'] = $options['bcc'];
            }

            if (!empty($options['attachments'])) {
                $params['attachment'] = $options['attachments'];
            }

            $this->client->messages()->send($domain, $params);
            return true;
        } catch (\Exception $e) {
            throw new \Exception('Failed to send email via Mailgun API: ' . $e->getMessage());
        }
    }

    public function isAvailable(): bool
    {
        try {
            $domain = $this->getConfig('domain');
            $this->client->domains()->show($domain);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getProviderName(): string
    {
        return 'mailgun';
    }
}