<?php

declare(strict_types=1);

namespace MauticPlugin\MauticZenderBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\IntegrationsBundle\Migration\AbstractMigration;

class Version0002 extends AbstractMigration
{
    /** @var string[] */
    private array $queries = [];

    protected function isApplicable(Schema $schema): bool
    {
        $this->queries = [];

        $this->prepareLogTableQueries($schema);
        $this->prepareLeadTableQueries($schema);

        return !empty($this->queries);
    }

    protected function up(): void
    {
        foreach ($this->queries as $query) {
            $this->addSql($query);
        }
    }

    private function prepareLogTableQueries(Schema $schema): void
    {
        $logTable = $this->concatPrefix('zender_api_request_log');

        if (!$schema->hasTable($logTable)) {
            $this->queries[] = sprintf(
                'CREATE TABLE `%s` (
                    `id` INT AUTO_INCREMENT NOT NULL,
                    `requested_at` DATETIME NOT NULL,
                    `first_message_at` DATETIME DEFAULT NULL,
                    `last_message_at` DATETIME DEFAULT NULL,
                    `response_data` LONGTEXT NOT NULL,
                    `status` VARCHAR(255) NOT NULL,
                    `message_type` VARCHAR(50) NOT NULL,
                    `processed_at` DATETIME DEFAULT NULL,
                    PRIMARY KEY(`id`)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB',
                $logTable
            );

            return;
        }

        $table = $schema->getTable($logTable);

        if (!$table->hasColumn('message_type')) {
            $this->queries[] = sprintf(
                'ALTER TABLE `%s` ADD `message_type` VARCHAR(50) NOT NULL',
                $logTable
            );
        }

        if (!$table->hasColumn('processed_at')) {
            $this->queries[] = sprintf(
                'ALTER TABLE `%s` ADD `processed_at` DATETIME DEFAULT NULL',
                $logTable
            );
        }
    }

    private function prepareLeadTableQueries(Schema $schema): void
    {
        $leadTable = $this->concatPrefix('leads');
        if (!$schema->hasTable($leadTable)) {
            return;
        }

        $table = $schema->getTable($leadTable);
        $this->addLeadColumnIfMissing($table, $leadTable, 'last_sent_message_date', 'VARCHAR(64) DEFAULT NULL');
        $this->addLeadColumnIfMissing($table, $leadTable, 'last_sent_message_status', 'VARCHAR(64) DEFAULT NULL');
        $this->addLeadColumnIfMissing($table, $leadTable, 'last_sent_message_content', 'VARCHAR(255) DEFAULT NULL');
        $this->addLeadColumnIfMissing($table, $leadTable, 'last_received_message_date', 'VARCHAR(64) DEFAULT NULL');
        $this->addLeadColumnIfMissing($table, $leadTable, 'last_received_message_status', 'VARCHAR(64) DEFAULT NULL');
        $this->addLeadColumnIfMissing($table, $leadTable, 'last_received_message_content', 'VARCHAR(255) DEFAULT NULL');

        if ($table->hasColumn('last_sent_message_content')) {
            $length = (int) $table->getColumn('last_sent_message_content')->getLength();
            if ($length > 0 && $length < 255) {
                $this->queries[] = sprintf(
                    'ALTER TABLE `%s` MODIFY `last_sent_message_content` VARCHAR(255) DEFAULT NULL',
                    $leadTable
                );
            }
        }

        if ($table->hasColumn('last_received_message_content')) {
            $length = (int) $table->getColumn('last_received_message_content')->getLength();
            if ($length > 0 && $length < 255) {
                $this->queries[] = sprintf(
                    'ALTER TABLE `%s` MODIFY `last_received_message_content` VARCHAR(255) DEFAULT NULL',
                    $leadTable
                );
            }
        }
    }

    private function addLeadColumnIfMissing(\Doctrine\DBAL\Schema\Table $table, string $tableName, string $columnName, string $definition): void
    {
        if ($table->hasColumn($columnName)) {
            return;
        }

        $this->queries[] = sprintf(
            'ALTER TABLE `%s` ADD `%s` %s',
            $tableName,
            $columnName,
            $definition
        );
    }
}
