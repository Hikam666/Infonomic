<?php
declare(strict_types=1);

class DashboardController extends Controller
{
    public function index(): void
    {
        Middleware::requireLogin();

        $role = Auth::role();
        $idUser = Auth::id();

        $where = '';
        $params = [];

        if ($role === 'reporter' && $idUser !== null) {
            $where = 'WHERE id_penulis = :id_user';
            $params['id_user'] = $idUser;
        }

        $total = (int) Database::query("SELECT COUNT(*) AS c FROM berita {$where}", $params)->fetch()['c'];

        $statusRows = Database::query(
            "SELECT status, COUNT(*) AS jumlah
             FROM berita {$where}
             GROUP BY status",
            $params
        )->fetchAll();

        $totalViews = (int) Database::query("SELECT COALESCE(SUM(jumlah_view),0) AS s FROM berita {$where}", $params)->fetch()['s'];

        $topPopuler = Database::query(
            "SELECT id_berita, judul, jumlah_view, waktu_publish
             FROM berita
             WHERE status = 'dipublikasikan'
             ORDER BY jumlah_view DESC
             LIMIT 5"
        )->fetchAll();

        if ($role === 'reporter' && $idUser !== null) {
            $logs = Database::query(
                "SELECT la.*, u.nama
                 FROM log_aktivitas la
                 LEFT JOIN users u ON u.id_user = la.id_user
                 WHERE la.id_user = :id_user
                 ORDER BY la.dibuat_pada DESC
                 LIMIT 10",
                ['id_user' => $idUser]
            )->fetchAll();
        } else {
            $logs = Database::query(
                "SELECT la.*, u.nama
                 FROM log_aktivitas la
                 LEFT JOIN users u ON u.id_user = la.id_user
                 ORDER BY la.dibuat_pada DESC
                 LIMIT 10"
            )->fetchAll();
        }

        $this->view('admin/dashboard/index', [
            'title' => 'Dashboard',
            'total' => $total,
            'statusRows' => $statusRows,
            'totalViews' => $totalViews,
            'topPopuler' => $topPopuler,
            'logs' => $logs,
        ]);
    }
}
