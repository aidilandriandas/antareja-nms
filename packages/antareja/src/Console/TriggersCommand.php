<?php

namespace Antareja\Console;

use Antareja\Models\Trigger;
use Antareja\Services\TriggerEngine;
use Illuminate\Console\Command;

class TriggersCommand extends Command
{
    protected $signature = 'antareja:triggers {--id=* : Hanya trigger tertentu}';

    protected $description = 'Evaluasi trigger expression dan tampilkan nilainya (normalnya dijalankan oleh antareja:problems)';

    public function handle(TriggerEngine $engine): int
    {
        $q = Trigger::where('enabled', true);
        if ($this->option('id')) {
            $q->whereIn('id', $this->option('id'));
        }
        $triggers = $q->get();
        $engine->evaluate($triggers);

        $this->table(['ID', 'Trigger', 'Ekspresi', 'Nilai', 'Status'], $triggers->map(fn (Trigger $t) => [
            $t->id, $t->name, $t->expression(TriggerEngine::itemLabel($t)), Trigger::fmt($t->last_value, TriggerEngine::sensorUnit($t)), $t->state,
        ]));

        return self::SUCCESS;
    }
}
