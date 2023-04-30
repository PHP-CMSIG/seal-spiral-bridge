<?php

declare(strict_types=1);

/*
 * This file is part of the Schranz Search package.
 *
 * (c) Alexander Schranz <alexander@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Schranz\Search\Integration\Spiral\Console;

use Schranz\Search\SEAL\EngineRegistry;
use Spiral\Boot\Environment\AppEnvironment;
use Spiral\Console\Attribute\AsCommand;
use Spiral\Console\Attribute\Option;
use Spiral\Console\Command;
use Symfony\Component\Console\Input\InputOption;

/**
 * @experimental
 */
#[AsCommand(
    name: 'schranz:search:index-drop',
    description: 'Drop configured search indexes.',
)]
final class IndexDropCommand extends Command
{
    #[Option(name: 'engine', mode: InputOption::VALUE_REQUIRED, description: 'The name of the engine')]
    private string|null $engineName = null;

    #[Option(name: 'index', mode: InputOption::VALUE_REQUIRED, description: 'The name of the index')]
    private string|null $indexName = null;

    #[Option(shortcut: 'f', description: 'Force to drop the indexes')]
    private bool $force = false;

    public function __invoke(EngineRegistry $engineRegistry, AppEnvironment $env): int
    {
        if ($env->isProduction() && !$this->force) {
            $this->error('You need to use the --force option to drop the search indexes in production mode.');

            return self::FAILURE;
        }

        foreach ($engineRegistry->getEngines() as $name => $engine) {
            if ($this->engineName && $this->engineName !== $name) {
                continue;
            }

            if ($this->indexName) {
                $this->line('Drop search index "' . $this->indexName . '" of "' . $name . '" ...');
                $engine->dropIndex($this->indexName);

                continue;
            }

            $this->line('Drop search indexes of "' . $name . '" ...');
            $engine->dropSchema();
        }

        $this->info('Search indexes dropped.');

        return self::SUCCESS;
    }
}
