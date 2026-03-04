<?php

declare(strict_types=1);

namespace MauticPlugin\MauticZenderBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\IntegrationsBundle\Migration\AbstractMigration;

class Version0001 extends AbstractMigration
{
    protected function isApplicable(Schema $schema): bool
    {
        $leadTable = $this->concatPrefix('leads');

        if (!$schema->hasTable($leadTable)) {
            return false;
        }

        return !$schema->getTable($leadTable)->hasColumn('id_whatsapp_in_zender');
    }

    protected function up(): void
    {
        $this->addSql(
            sprintf(
                'ALTER TABLE `%s` ADD `id_whatsapp_in_zender` VARCHAR(191) DEFAULT NULL',
                $this->concatPrefix('leads')
            )
        );
    }
}
