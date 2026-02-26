<?php

namespace MauticPlugin\MauticZenderBundle\Migrations\Schema;

use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Doctrine\AbstractMauticMigration;

class Version0002 extends AbstractMauticMigration
{
    public function up(Schema $schema)
    {
        $this->ensureLogTable($schema);
        $this->ensureLeadColumns($schema);
    }

    private function ensureLogTable(Schema $schema): void
    {
        $tableName = $this->prefix.'zender_api_request_log';

        if ($schema->hasTable($tableName)) {
            $table = $schema->getTable($tableName);

            if (!$table->hasColumn('message_type')) {
                $table->addColumn('message_type', 'string', ['length' => 50, 'notnull' => true]);
            }

            if (!$table->hasColumn('processed_at')) {
                $table->addColumn('processed_at', 'datetime', ['notnull' => false, 'default' => null]);
            }

            return;
        }

        $table = $schema->createTable($tableName);
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('requested_at', 'datetime', ['notnull' => true]);
        $table->addColumn('first_message_at', 'datetime', ['notnull' => false]);
        $table->addColumn('last_message_at', 'datetime', ['notnull' => false]);
        $table->addColumn('response_data', 'text', ['notnull' => true]);
        $table->addColumn('status', 'string', ['length' => 255, 'notnull' => true]);
        $table->addColumn('message_type', 'string', ['length' => 50, 'notnull' => true]);
        $table->addColumn('processed_at', 'datetime', ['notnull' => false, 'default' => null]);
        $table->setPrimaryKey(['id']);
    }

    private function ensureLeadColumns(Schema $schema): void
    {
        $leadTableName = $this->prefix.'leads';
        if (!$schema->hasTable($leadTableName)) {
            return;
        }

        $table = $schema->getTable($leadTableName);

        if (!$table->hasColumn('last_sent_message_date')) {
            $table->addColumn('last_sent_message_date', 'string', ['length' => 64, 'notnull' => false, 'default' => null]);
        }
        if (!$table->hasColumn('last_sent_message_status')) {
            $table->addColumn('last_sent_message_status', 'string', ['length' => 64, 'notnull' => false, 'default' => null]);
        }
        if (!$table->hasColumn('last_sent_message_content')) {
            $table->addColumn('last_sent_message_content', 'string', ['length' => 255, 'notnull' => false, 'default' => null]);
        } elseif ($table->getColumn('last_sent_message_content')->getLength() < 255) {
            $table->changeColumn('last_sent_message_content', ['length' => 255, 'notnull' => false, 'default' => null]);
        }
        if (!$table->hasColumn('last_received_message_date')) {
            $table->addColumn('last_received_message_date', 'string', ['length' => 64, 'notnull' => false, 'default' => null]);
        }
        if (!$table->hasColumn('last_received_message_status')) {
            $table->addColumn('last_received_message_status', 'string', ['length' => 64, 'notnull' => false, 'default' => null]);
        }
        if (!$table->hasColumn('last_received_message_content')) {
            $table->addColumn('last_received_message_content', 'string', ['length' => 255, 'notnull' => false, 'default' => null]);
        } elseif ($table->getColumn('last_received_message_content')->getLength() < 255) {
            $table->changeColumn('last_received_message_content', ['length' => 255, 'notnull' => false, 'default' => null]);
        }
    }
}
