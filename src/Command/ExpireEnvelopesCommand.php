<?php

namespace Wexample\SymfonySignature\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Wexample\SymfonySignature\Service\EnvelopeService;

#[AsCommand(name: 'signature:expire', description: 'Closes the envelopes waiting for signatures past their expiry; for a scheduled task.')]
class ExpireEnvelopesCommand extends Command
{
    public function __construct(
        private readonly EnvelopeService $envelopes,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(sprintf('%d envelope(s) expired.', $this->envelopes->expireDue()));

        return Command::SUCCESS;
    }
}
