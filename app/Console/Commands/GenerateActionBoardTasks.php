<?php

namespace App\Console\Commands;

use App\Services\ActionBoardTaskGenerator;
use Illuminate\Console\Command;

class GenerateActionBoardTasks extends Command
{
    protected $signature = 'action-board:generate';

    protected $description = "Génère et met à jour les tâches du tableau d'actions";

    public function handle(ActionBoardTaskGenerator $generator): int
    {
        $generator->generate();
        $this->info("Tâches du tableau d'actions mises à jour.");

        return self::SUCCESS;
    }
}
