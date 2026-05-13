<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\PresenceStatus;
use App\Service\PresenceRedisService;
use App\Service\PresenceService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:presence:cleanup',
    description: 'Mark offline any users whose presence TTL has expired; run every 5 minutes via cron.',
)]
class PresenceCleanupCommand extends Command
{
    public function __construct(
        private readonly PresenceRedisService $presenceRedis,
        private readonly PresenceService $presenceService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io      = new SymfonyStyle($input, $output);
        $stale   = $this->presenceRedis->getStaleMemberIds();
        $cleaned = 0;

        foreach ($stale as $id) {
            $this->presenceRedis->evictFromSet($id);
            $this->presenceService->updateStatus($id, PresenceStatus::Offline);
            ++$cleaned;
        }

        $io->success(sprintf('Cleaned up %d stale presence entr%s.', $cleaned, $cleaned === 1 ? 'y' : 'ies'));

        return Command::SUCCESS;
    }
}
