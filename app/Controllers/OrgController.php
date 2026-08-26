<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Org;

final class OrgController
{
    public function switch(): void
    {
        Auth::requireLogin();
        Org::switch((string)($_POST['organization'] ?? ''));
        $back = (string)($_POST['back'] ?? '/');
        if ($back === '' || $back[0] !== '/' || str_starts_with($back, '//')) {
            $back = '/';
        }
        redirect($back);
    }
}
