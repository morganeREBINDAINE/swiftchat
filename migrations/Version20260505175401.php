<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260505175401 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE app_user (id UUID NOT NULL, username VARCHAR(50) NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, avatar VARCHAR(255) DEFAULT NULL, roles JSON NOT NULL, is_verified BOOLEAN DEFAULT false NOT NULL, email_notifications_enabled BOOLEAN DEFAULT true NOT NULL, presence_status VARCHAR(255) NOT NULL, last_seen_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_88BDF3E9F85E0677 ON app_user (username)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_88BDF3E9E7927C74 ON app_user (email)');
        $this->addSql('CREATE TABLE conversation (id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, participant1_id UUID NOT NULL, participant2_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_8A8E26E9B29A9963 ON conversation (participant1_id)');
        $this->addSql('CREATE INDEX IDX_8A8E26E9A02F368D ON conversation (participant2_id)');
        $this->addSql('CREATE UNIQUE INDEX uq_conversation_participants ON conversation (participant1_id, participant2_id)');
        $this->addSql('CREATE TABLE email_verification_token (id UUID NOT NULL, token VARCHAR(255) NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C4995C675F37A13B ON email_verification_token (token)');
        $this->addSql('CREATE INDEX IDX_C4995C67A76ED395 ON email_verification_token (user_id)');
        $this->addSql('CREATE TABLE message (id UUID NOT NULL, content TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, read_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, conversation_id UUID NOT NULL, sender_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_B6BD307F9AC0396 ON message (conversation_id)');
        $this->addSql('CREATE INDEX IDX_B6BD307FF624B39D ON message (sender_id)');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_8A8E26E9B29A9963 FOREIGN KEY (participant1_id) REFERENCES app_user (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_8A8E26E9A02F368D FOREIGN KEY (participant2_id) REFERENCES app_user (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE email_verification_token ADD CONSTRAINT FK_C4995C67A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307F9AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FF624B39D FOREIGN KEY (sender_id) REFERENCES app_user (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE conversation DROP CONSTRAINT FK_8A8E26E9B29A9963');
        $this->addSql('ALTER TABLE conversation DROP CONSTRAINT FK_8A8E26E9A02F368D');
        $this->addSql('ALTER TABLE email_verification_token DROP CONSTRAINT FK_C4995C67A76ED395');
        $this->addSql('ALTER TABLE message DROP CONSTRAINT FK_B6BD307F9AC0396');
        $this->addSql('ALTER TABLE message DROP CONSTRAINT FK_B6BD307FF624B39D');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('DROP TABLE conversation');
        $this->addSql('DROP TABLE email_verification_token');
        $this->addSql('DROP TABLE message');
    }
}
