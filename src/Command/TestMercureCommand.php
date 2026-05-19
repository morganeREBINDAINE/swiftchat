<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

#[AsCommand(
    name: 'test:mercure',
    description: 'Publish a message on "test" topic to test Mercure',
)]
class TestMercureCommand extends Command
{
    public function __construct(private readonly HubInterface $hub)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('topic', InputArgument::OPTIONAL, 'Topic to publish to', 'test')
            ->addArgument('message', InputArgument::OPTIONAL, 'Message to send', 'Hello from SwiftChat')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $topic = $input->getArgument('topic');
        $message = $input->getArgument('message');

        $data = json_encode([
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        $update = new Update(
            $topic,
            $data,
        );

        try {
            $id = $this->hub->publish($update);

            $io->success(['Event published on Mercure !',
                "Topic: {$topic}",
                "Message: {$message}",
                "Event ID: {$id}", ]);
        } catch (\Exception $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
