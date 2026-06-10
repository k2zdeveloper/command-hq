<?php

namespace App\Commands;

use App\Models\SupabaseModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Strips "Zengit " prefix from existing Zengit agent names.
 * Run once:  php spark zengit:rename
 */
class ZengitRename extends BaseCommand
{
    protected $group       = 'Zengit';
    protected $name        = 'zengit:rename';
    protected $description = 'Remove "Zengit " prefix from Zengit agent names';

    public function run(array $params): void
    {
        $sb     = new SupabaseModel();
        $agents = $sb->getCompanyAgents('zengit');

        if (empty($agents)) {
            CLI::error('No Zengit agents found.');
            return;
        }

        foreach ($agents as $a) {
            if (str_starts_with($a['name'], 'Zengit ')) {
                $newName = substr($a['name'], 7); // strip "Zengit "
                $ok = $sb->updateAgent($a['id'], ['name' => $newName]);
                if ($ok) {
                    CLI::write("  RENAMED  \"{$a['name']}\" → \"{$newName}\"", 'green');
                } else {
                    CLI::error("  FAILED   {$a['name']}");
                }
            } else {
                CLI::write("  SKIP     {$a['name']} (no prefix)", 'yellow');
            }
        }

        CLI::write("\nDone. Refresh the Zengit page to see updated names.", 'green');
    }
}
