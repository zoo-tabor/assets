<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    /**
     * Vykresli sablonu app/Views/{name}.php uvnitr layoutu.
     * $data['title'] = titulek stranky, $data['layout'] = false pro stranky bez layoutu (login).
     */
    public static function render(string $name, array $data = []): void
    {
        $file = BASE_PATH . '/app/Views/' . $name . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Šablona {$name} neexistuje.");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $file;
        $content = ob_get_clean();

        if (($data['layout'] ?? true) === false) {
            echo $content;
            return;
        }

        require BASE_PATH . '/app/Views/layout.php';
    }
}
