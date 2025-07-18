<?php

namespace MultiEmailServices\Library\Providers;

use MultiEmailServices\Library\AbstractEmailService;
use SendGrid;
use SendGrid\Mail\Mail;
use SendGrid\Mail\Attachment;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class SendGridService extends AbstractEmailService
{
    private SendGrid $client;
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
            $this->client = new SendGrid($this->getConfig('api_key'));
        }
    }

    private function setupSmtp(): void
    {
        try {
            $this->mailer->isSMTP();
            $this->mailer->Host = $this->getConfig('smtp_host', 'smtp.sendgrid.net');
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = 'apikey'; // SendGrid uses 'apikey' as username
            $this->mailer->Password = $this->getConfig('api_key');
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
                    $this->mailer->addAttachment(
                        $attachment['path'],
                        $attachment['name'],
                        'base64',
                        $attachment['type'] ?? 'application/octet-stream'
                    );
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
            $email = new Mail();
            $from = $options['from'] ?? $this->getConfig('from_address');
            $fromName = $options['from_name'] ?? $this->getConfig('from_name');

            $email->setFrom($from, $fromName);
            $email->setSubject($subject);
            $email->addTo($to);
            $email->addContent('text/html', $body);

            // Add CC recipients if provided
            if (!empty($options['cc'])) {
                foreach ((array)$options['cc'] as $cc) {
                    $email->addCc($cc);
                }
            }

            // Add BCC recipients if provided
            if (!empty($options['bcc'])) {
                foreach ((array)$options['bcc'] as $bcc) {
                    $email->addBcc($bcc);
                }
            }

            // Add attachments if provided
            if (!empty($options['attachments'])) {
                foreach ($options['attachments'] as $attachment) {
                    $file = new Attachment();
                    $file->setContent(base64_encode(file_get_contents($attachment['path'])));
                    $file->setType($attachment['type'] ?? 'application/octet-stream');
                    $file->setFilename($attachment['name']);
                    $file->setDisposition('attachment');
                    $email->addAttachment($file);
                }
            }

            $response = $this->client->send($email);
            return $response->statusCode() === 202;
        } catch (\Exception $e) {
            throw new \Exception('Failed to send email via SendGrid: ' . $e->getMessage());
        }
    }

    public function isAvailable(): bool
    {
        try {
            $response = $this->client->client->user()->get();
            return $response->statusCode() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getProviderName(): string
    {
        return 'sendgrid';
    }
}