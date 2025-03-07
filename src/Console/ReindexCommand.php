<?php

declare(strict_types=1);

/*
 * This file is part of the CMS-IG SEAL project.
 *
 * (c) Alexander Schranz <alexander@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CmsIg\Seal\Integration\Spiral\Console;

use CmsIg\Seal\EngineRegistry;
use CmsIg\Seal\Reindex\ReindexConfig;
use CmsIg\Seal\Reindex\ReindexProviderInterface;
use Spiral\Console\Attribute\AsCommand;
use Spiral\Console\Attribute\Option;
use Spiral\Console\Command;
use Symfony\Component\Console\Input\InputOption;

/**
 * @experimental
 */
#[AsCommand(
    name: 'cmsig:seal:reindex',
    description: 'Reindex configured search indexes.',
)]
final class ReindexCommand extends Command
{
    #[Option(name: 'engine', mode: InputOption::VALUE_REQUIRED, description: 'The name of the engine')]
    private string|null $engineName = null;

    #[Option(name: 'index', mode: InputOption::VALUE_REQUIRED, description: 'The name of the index')]
    private string|null $indexName = null;

    #[Option(name: 'drop', description: 'Drop the index before reindexing.')]
    private bool $drop = false;

    #[Option(name: 'bulk-size', description: 'The bulk size for reindexing, defaults to 100.')]
    private int $bulkSize = 100;

    #[Option(name: 'datetime-boundary', description: 'Do a partial update and limit to only documents that have been changed since a given datetime object.')]
    private string|null $datetimeBoundary = null;

    #[Option(name: 'identifiers', description: 'Do a partial update and limit to only a comma-separated list of identifiers.')]
    private string|null $identifiers = null;

    /**
     * @param iterable<ReindexProviderInterface> $reindexProviders
     */
    public function __construct(
        private readonly iterable $reindexProviders, // TODO move to __invoke method
    ) {
        parent::__construct();
    }

    public function __invoke(
        EngineRegistry $engineRegistry,
    ): int {
        /** @var \DateTimeImmutable|null $dateTimeBoundary */
        $dateTimeBoundary = $this->datetimeBoundary ? new \DateTimeImmutable((string) $this->datetimeBoundary) : null;
        /** @var array<string> $identifiers */
        $identifiers = \array_filter(\explode(',', (string) $this->identifiers));

        $reindexConfig = ReindexConfig::create()
            ->withIndex($this->indexName)
            ->withBulkSize($this->bulkSize)
            ->withDropIndex($this->drop)
            ->withDateTimeBoundary($dateTimeBoundary)
            ->withIdentifiers($identifiers);

        foreach ($engineRegistry->getEngines() as $name => $engine) {
            if ($this->engineName && $this->engineName !== $name) {
                continue;
            }

            $this->line('Engine: ' . $name);

            $progressBar = $this->output->createProgressBar();

            $engine->reindex(
                $this->reindexProviders,
                $reindexConfig,
                function (string $index, int $count, int|null $total) use ($progressBar) {
                    if (null !== $total) {
                        $progressBar->setMaxSteps($total);
                    }

                    $progressBar->setMessage($index);
                    $progressBar->setProgress($count);
                },
            );

            $progressBar->finish();

            $this->line('');
            $this->line('');
        }

        $this->info('Search indexes reindexed.');

        return self::SUCCESS;
    }
}
