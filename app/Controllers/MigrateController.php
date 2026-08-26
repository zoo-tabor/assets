<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Migrator;
use App\Core\View;

final class MigrateController
{
    public function index(): void
    {
        $setupMode = Migrator::isSetupMode();
        if (!$setupMode) {
            Auth::requireLogin();
        }

        $result = null;
        $seedLog = [];

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $result = Migrator::run();
            if ($result['error'] === null) {
                $seedLog = Migrator::seedDefaults();
            }
        }

        View::render('migrate', [
            'title' => 'Databázové migrace',
            'layout' => !$setupMode && Auth::check(),
            'setupMode' => $setupMode,
            'pending' => Migrator::pending(),
            'applied' => Migrator::allFiles(),
            'result' => $result,
            'seedLog' => $seedLog,
        ]);
    }
}
