<?php

namespace MultiEmailServices\Library\Providers;

use MultiEmailServices\Library\AbstractEmailService;
use Postmark\PostmarkClient;
use Postmark\Models\PostmarkAttachment;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class PostmarkService extends AbstractEmailService
{
    private PostmarkClient $client;
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
            $this->client = new PostmarkClient($this->getConfig('api_key'));
        }
    }

    private function setupSmtp(): void
    {
        try {
            $this->mailer->isSMTP();
            $this->mailer->Host = $this->getConfig('smtp_host', 'smtp.postmarkapp.com');
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $this->getConfig('smtp_username');
            $this->mailer->Password = $this->getConfig('api_key'); // Postmark uses API key as SMTP password
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
            $from = $options['from'] ?? $this->getConfig('from_address');
            $params = [
                'From' => $from,
                'To' => $to,
                'Subject' => $subject,
                'HtmlBody' => $body
            ];

            // Add CC recipients if provided
            if (!empty($options['cc'])) {
                $params['Cc'] = is_array($options['cc']) ? implode(',', $options['cc']) : $options['cc'];
            }

            // Add BCC recipients if provided
            if (!empty($options['bcc'])) {
                $params['Bcc'] = is_array($options['bcc']) ? implode(',', $options['bcc']) : $options['bcc'];
            }

            // Add attachments if provided
            if (!empty($options['attachments'])) {
                $params['Attachments'] = [];
                foreach ($options['attachments'] as $attachment) {
                    $file = new PostmarkAttachment(
                        base64_encode(file_get_contents($attachment['path'])),
                        $attachment['name'],
                        $attachment['type'] ?? 'application/octet-stream'
                    );
                    $params['Attachments'][] = $file;
                }
            }

            $response = $this->client->sendEmail(
                $params['From'],
                $params['To'],
                $params['Subject'],
                $params['HtmlBody'],
                null,
                null,
                true,
                $params['Cc'] ?? null,
                $params['Bcc'] ?? null,
                null,
                $params['Attachments'] ?? null
            );

            return !empty($response) && !empty($response->MessageID);
        } catch (\Exception $e) {
            throw new \Exception('Failed to send email via Postmark: ' . $e->getMessage());
        }
    }

    public function isAvailable(): bool
    {
        try {
            $response = $this->client->getServer();
            return !empty($response) && !empty($response->ServerID);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getProviderName(): string
    {
        return 'postmark';
    }
}