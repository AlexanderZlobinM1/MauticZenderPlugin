<?php

namespace MauticPlugin\MauticZenderBundle\Command;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\PluginBundle\Helper\IntegrationHelper;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SyncMessagesCommand extends Command
{
    private const COMMAND_NAME = 'mautic:zender:sync-messages';

    private const DEFAULT_FETCH_QUANTITY = 7;
    private const DEFAULT_FETCH_UNIT     = 'days';
    private const DEFAULT_BATCH_SIZE     = 50;

    private const TYPE_PENDING  = 'wa.pending';
    private const TYPE_RECEIVED = 'wa.received';
    private const TYPE_SENT     = 'wa.sent';

    protected static $defaultName = self::COMMAND_NAME;

    /** @var EntityManagerInterface */
    private $entityManager;

    /** @var IntegrationHelper */
    private $integrationHelper;

    /** @var LeadModel */
    private $leadModel;

    /** @var LoggerInterface */
    private $logger;

    /** @var Client */
    private $client;

    /** @var CoreParametersHelper */
    private $coreParametersHelper;

    /** @var Connection */
    private $connection;

    /** @var string */
    private $prefix;

    /** @var array<string, int> */
    private $leadColumnLengths = [];

    public function __construct(
        EntityManagerInterface $entityManager,
        IntegrationHelper $integrationHelper,
        LeadModel $leadModel,
        LoggerInterface $logger,
        Client $client,
        CoreParametersHelper $coreParametersHelper
    ) {
        parent::__construct(self::COMMAND_NAME);

        $this->entityManager       = $entityManager;
        $this->integrationHelper   = $integrationHelper;
        $this->leadModel           = $leadModel;
        $this->logger              = $logger;
        $this->client              = $client;
        $this->coreParametersHelper = $coreParametersHelper;
        $this->connection          = $entityManager->getConnection();
        $this->prefix              = (string) $coreParametersHelper->get('db_table_prefix');
    }

    protected function configure(): void
    {
        $this->setDescription('Synchronize message statuses and inbound/outbound messages from Zender.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $settings = $this->getIntegrationSettings();
        if (null === $settings) {
            $output->writeln('<comment>Zender integration is not published or missing required API settings.</comment>');

            return Command::SUCCESS;
        }

        $types = [self::TYPE_PENDING, self::TYPE_RECEIVED, self::TYPE_SENT];
        foreach ($types as $type) {
            $this->fetchAndStoreType($settings, $type, $output);
        }

        $processed = $this->processUnprocessedLogs($output);
        $output->writeln(sprintf('<info>Processed %d synchronized messages.</info>', $processed));

        return Command::SUCCESS;
    }

    private function fetchAndStoreType(array $settings, string $type, OutputInterface $output): void
    {
        $page   = 1;
        $limit  = max(1, (int) $settings['batch_size']);
        $since  = $this->getSyncStartTimestamp($type, (int) $settings['fetch_quantity'], (string) $settings['fetch_unit']);
        $base   = rtrim((string) $settings['zender_base_url'], '/');
        $secret = (string) $settings['zender_api_key'];

        while (true) {
            $url = $base.'/get/'.$type;
            try {
                $response = $this->client->request('GET', $url, [
                    'query'   => [
                        'secret' => $secret,
                        'limit'  => $limit,
                        'page'   => $page,
                    ],
                    'timeout' => 25,
                ]);
            } catch (\Throwable $e) {
                $this->logger->error('[ZENDER][SYNC] Failed to fetch messages.', [
                    'type'  => $type,
                    'page'  => $page,
                    'error' => $e->getMessage(),
                ]);
                $output->writeln(sprintf('<error>Failed fetching %s page %d: %s</error>', $type, $page, $e->getMessage()));

                return;
            }

            $body = (string) $response->getBody();
            $json = json_decode($body, true);
            if (!is_array($json) || !isset($json['status']) || 200 !== (int) $json['status'] || !isset($json['data']) || !is_array($json['data'])) {
                $this->logger->warning('[ZENDER][SYNC] Unexpected API response.', [
                    'type' => $type,
                    'page' => $page,
                    'body' => $body,
                ]);

                return;
            }

            $messages = $json['data'];
            if (0 === count($messages)) {
                return;
            }

            $messagesToStore = [];
            foreach ($messages as $message) {
                if (!is_array($message)) {
                    continue;
                }

                $created = isset($message['created']) ? (int) $message['created'] : 0;
                if ($created > 0 && $created > $since) {
                    $messagesToStore[] = $message;
                }
            }

            if (0 === count($messagesToStore)) {
                return;
            }

            $firstTs = $this->extractMessageTimestamp($messagesToStore[0]);
            $lastTs  = $this->extractMessageTimestamp($messagesToStore[count($messagesToStore) - 1]);

            $this->insertSyncLog($type, $messagesToStore, (int) $json['status'], $firstTs, $lastTs);

            if (count($messages) < $limit) {
                return;
            }

            ++$page;
        }
    }

    private function processUnprocessedLogs(OutputInterface $output): int
    {
        $table = $this->prefix.'zender_api_request_log';
        $sql = sprintf('SELECT id, message_type, response_data FROM %s WHERE processed_at IS NULL ORDER BY id ASC', $table);
        $logs = $this->connection->fetchAllAssociative($sql);
        $processedMessages = 0;

        foreach ($logs as $log) {
            $messages = json_decode((string) $log['response_data'], true);
            if (!is_array($messages)) {
                $this->markLogProcessed((int) $log['id']);
                continue;
            }

            $type = (string) $log['message_type'];
            foreach ($messages as $message) {
                if (!is_array($message)) {
                    continue;
                }

                $phone = $this->extractPhone($message);
                if ('' === $phone) {
                    continue;
                }

                $lead = $this->findLeadByPhone($phone);
                if (!$lead instanceof Lead) {
                    continue;
                }

                $this->updateLeadFromMessage($lead, $message, $type);
                ++$processedMessages;
            }

            $this->markLogProcessed((int) $log['id']);
        }

        $output->writeln(sprintf('<info>Processed %d log rows.</info>', count($logs)));

        return $processedMessages;
    }

    private function updateLeadFromMessage(Lead $lead, array $message, string $type): void
    {
        $leadTable = $this->prefix.'leads';
        $leadId = (int) $lead->getId();
        $createdAt = $this->extractMessageTimestamp($message);
        $content = isset($message['message']) ? $this->truncateForLeadColumn((string) $message['message'], 'last_sent_message_content', 255) : null;
        $status  = isset($message['status']) ? $this->truncateForLeadColumn((string) $message['status'], 'last_sent_message_status', 64) : 'success';

        $payload = [];
        if (self::TYPE_RECEIVED === $type) {
            $payload['last_received_message_date']    = $createdAt ? date('Y-m-d H:i:s', $createdAt) : null;
            $payload['last_received_message_status']  = $this->truncateForLeadColumn($status, 'last_received_message_status', 64);
            $payload['last_received_message_content'] = $this->truncateForLeadColumn((string) $content, 'last_received_message_content', 255);
        } else {
            $payload['last_sent_message_date']    = $createdAt ? date('Y-m-d H:i:s', $createdAt) : null;
            $payload['last_sent_message_status']  = $this->truncateForLeadColumn($status, 'last_sent_message_status', 64);
            $payload['last_sent_message_content'] = $this->truncateForLeadColumn((string) $content, 'last_sent_message_content', 255);
        }

        if (0 === count($payload)) {
            return;
        }

        $this->connection->update($leadTable, $payload, ['id' => $leadId]);
    }

    private function findLeadByPhone(string $incomingPhone): ?Lead
    {
        $repository = $this->leadModel->getRepository();
        $candidates = $this->buildPhoneCandidates($incomingPhone);

        foreach ($candidates as $candidate) {
            $lead = $repository->findOneBy(['phone' => $candidate]);
            if ($lead instanceof Lead) {
                return $lead;
            }

            $lead = $repository->findOneBy(['mobile' => $candidate]);
            if ($lead instanceof Lead) {
                return $lead;
            }
        }

        return null;
    }

    private function buildPhoneCandidates(string $phone): array
    {
        $values = [];
        $trimmed = trim($phone);
        $digits = preg_replace('/\D+/', '', $trimmed);

        if ('' !== $trimmed) {
            $values[] = $trimmed;
            $values[] = ltrim($trimmed, '+');
            $values[] = '+'.ltrim($trimmed, '+');
        }

        if (is_string($digits) && '' !== $digits) {
            $values[] = $digits;
            $values[] = '+'.$digits;
        }

        return array_values(array_unique(array_filter($values)));
    }

    private function extractPhone(array $message): string
    {
        $phone = $message['recipient'] ?? $message['phone'] ?? $message['from'] ?? '';

        return is_string($phone) ? trim($phone) : '';
    }

    private function extractMessageTimestamp(array $message): ?int
    {
        if (isset($message['created'])) {
            $ts = (int) $message['created'];

            return $ts > 0 ? $ts : null;
        }

        return null;
    }

    private function getSyncStartTimestamp(string $type, int $qty, string $unit): int
    {
        $table = $this->prefix.'zender_api_request_log';
        $sql = sprintf('SELECT MAX(COALESCE(last_message_at, first_message_at, requested_at)) AS last_sync FROM %s WHERE message_type = :type', $table);
        $last = $this->connection->fetchOne($sql, ['type' => $type]);

        if (is_string($last) && '' !== $last) {
            $ts = strtotime($last);
            if (false !== $ts) {
                return $ts;
            }
        }

        return time() - $this->convertToSeconds($qty, $unit);
    }

    private function insertSyncLog(string $type, array $messages, int $status, ?int $firstTs, ?int $lastTs): void
    {
        $table = $this->prefix.'zender_api_request_log';
        $this->connection->insert($table, [
            'requested_at'      => date('Y-m-d H:i:s'),
            'first_message_at'  => $firstTs ? date('Y-m-d H:i:s', $firstTs) : null,
            'last_message_at'   => $lastTs ? date('Y-m-d H:i:s', $lastTs) : null,
            'response_data'     => json_encode($messages),
            'status'            => (string) $status,
            'message_type'      => $type,
            'processed_at'      => null,
        ]);
    }

    private function markLogProcessed(int $id): void
    {
        $this->connection->update($this->prefix.'zender_api_request_log', [
            'processed_at' => date('Y-m-d H:i:s'),
        ], ['id' => $id]);
    }

    private function getIntegrationSettings(): ?array
    {
        $integration = $this->integrationHelper->getIntegrationObject('Zender');
        if (!$integration || !$integration->getIntegrationSettings()->getIsPublished()) {
            return null;
        }

        $keys = $integration->getDecryptedApiKeys();
        $apiKey = (string) ($keys['zender_api_key'] ?? '');
        $apiUrl = (string) ($keys['zender_api_url'] ?? '');
        if ('' === $apiKey || '' === $apiUrl) {
            return null;
        }

        return [
            'zender_api_key'   => $apiKey,
            'zender_base_url'  => $this->resolveZenderBaseUrl($apiUrl),
            'fetch_quantity'   => max(1, (int) ($keys['fetch_quantity'] ?? self::DEFAULT_FETCH_QUANTITY)),
            'fetch_unit'       => (string) ($keys['fetch_unit'] ?? self::DEFAULT_FETCH_UNIT),
            'batch_size'       => max(1, (int) ($keys['batch_size'] ?? self::DEFAULT_BATCH_SIZE)),
        ];
    }

    private function resolveZenderBaseUrl(string $configuredUrl): string
    {
        $url = rtrim($configuredUrl, '/');
        if (false !== strpos($url, '/send/whatsapp')) {
            return rtrim((string) strstr($url, '/send/whatsapp', true), '/');
        }

        $apiPos = strpos($url, '/api/');
        if (false !== $apiPos) {
            return substr($url, 0, $apiPos + 4);
        }

        return $url;
    }

    private function convertToSeconds(int $quantity, string $unit): int
    {
        switch ($unit) {
            case 'minutes':
                return $quantity * 60;
            case 'hours':
                return $quantity * 3600;
            case 'weeks':
                return $quantity * 604800;
            case 'months':
                return $quantity * 2592000;
            case 'years':
                return $quantity * 31536000;
            case 'days':
            default:
                return $quantity * 86400;
        }
    }

    private function truncateForLeadColumn(string $value, string $columnName, int $fallbackLength): string
    {
        if ('' === $value) {
            return $value;
        }

        $limit = $this->getLeadColumnLength($columnName, $fallbackLength);
        if ($limit <= 0) {
            return $value;
        }

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $limit);
        }

        return substr($value, 0, $limit);
    }

    private function getLeadColumnLength(string $columnName, int $fallbackLength): int
    {
        if (isset($this->leadColumnLengths[$columnName])) {
            return $this->leadColumnLengths[$columnName];
        }

        $sql = 'SELECT CHARACTER_MAXIMUM_LENGTH AS max_len FROM information_schema.COLUMNS '
            .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tableName AND COLUMN_NAME = :columnName';

        $maxLen = $this->connection->fetchOne($sql, [
            'tableName'  => $this->prefix.'leads',
            'columnName' => $columnName,
        ]);

        $resolved = (int) $maxLen;
        if ($resolved <= 0) {
            $resolved = $fallbackLength;
        }

        $this->leadColumnLengths[$columnName] = $resolved;

        return $resolved;
    }
}
