<?php
declare(strict_types=1);

class Controller
{
    protected function view(string $path, array $data = []): void
    {
        extract($data);
        $viewFile = BASE_PATH . '/app/views/' . $path . '.php';

        if (!file_exists($viewFile)) {
            http_response_code(500);
            echo "View tidak ditemukan: {$path}";
            exit;
        }

        require $viewFile;
    }
}
