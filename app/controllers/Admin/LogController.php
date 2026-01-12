<?php
declare(strict_types=1);

class LogController extends Controller
{
    public function index(): void
    {
        Middleware::requireRole(['editor','admin']);

        $rows = Database::query(
            "SELECT la.*, u.nama
             FROM log_aktivitas la
             LEFT JOIN users u ON u.id_user = la.id_user
             ORDER BY la.dibuat_pada DESC
             LIMIT 200"
        )->fetchAll();

        $this->view('admin/logs/index', [
            'title' => 'Log Aktivitas',
            'rows' => $rows,
        ]);
    }
}
