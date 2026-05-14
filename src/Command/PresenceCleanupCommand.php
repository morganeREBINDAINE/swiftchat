<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\PresenceStatus;
use App\Service\PresenceRedisService;
use App\Service\PresenceService;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Monolog\Attribute\WithMonologChannel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:presence:cleanup',
    description: 'Mark offline any users whose presence TTL has expired.',
)]
#[WithMonologChannel('presence')]
class PresenceCleanupCommand extends Command
{
    public function __construct(
        private readonly PresenceRedisService $presenceRedis,
        private readonly PresenceService $presenceService,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io      = new SymfonyStyle($input, $output);
        $cleaned = 0;
        $failed  = 0;

        try {
            $stale = $this->presenceRedis->getStaleMemberIds();
        } catch (\Throwable $e) {
            $this->logger->error('Presence cleanup aborted: could not fetch stale members', [
                'error' => $e->getMessage(),
            ]);
            $io->error('Could not fetch stale members: '.$e->getMessage());

            return Command::FAILURE;
        }

        foreach ($stale as $id) {
            try {
                $this->presenceRedis->evictFromSet($id);
                $this->presenceService->updateStatus($id, PresenceStatus::Offline);
                ++$cleaned;
            } catch (\Throwable $e) {
                $this->logger->error('Failed to clean up presence entry', [
                    'user_id' => $id,
                    'error'   => $e->getMessage(),
                ]);
                $io->warning(sprintf('Failed for user %s: %s', $id, $e->getMessage()));
                ++$failed;
            }
        }

        $this->logger->info('Presence cleanup completed', [
            'cleaned' => $cleaned,
            'failed'  => $failed,
        ]);

        if ($failed > 0) {
            $io->warning(sprintf('Cleaned %d entr%s, %d failed.', $cleaned, $cleaned === 1 ? 'y' : 'ies', $failed));

            return Command::FAILURE;
        }

        $io->success(sprintf('Cleaned up %d stale presence entr%s.', $cleaned, $cleaned === 1 ? 'y' : 'ies'));

        return Command::SUCCESS;
    }
}
