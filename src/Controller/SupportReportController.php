<?php
namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse; // <-- ADD THIS
use Symfony\Component\Routing\Annotation\Route;

class SupportReportController extends AbstractController
{
    #[Route('/support/reports', name: 'support_reports')]
    public function index(Request $req, Connection $db): Response
    {
        // 1) Read filters from query string (GET)
        $start = $req->query->get('start');     // YYYY-MM-DD or null
        $end   = $req->query->get('end');       // YYYY-MM-DD or null
        $prod  = $req->query->get('product');   // string or null

        // Defaults: last 30 days
        if (!$start && !$end) {
            $start = (new \DateTime('-30 days'))->format('Y-m-d');
            $end   = (new \DateTime('now'))->format('Y-m-d');
        }

        // Build WHERE conditions + params
        $conds = [];
        $params = [];

        if ($start) { $conds[] = 'query_date >= :start'; $params['start'] = $start; }
        if ($end)   { $conds[] = 'query_date <= :end';   $params['end']   = $end; }
        if ($prod)  { $conds[] = 'product = :prod';      $params['prod']  = $prod; }

        $where = $conds ? ('WHERE ' . implode(' AND ', $conds)) : '';

        // 2) KPIs (ignore NULL/00:00:00 time_to_resolve for avg)
        $kpisSql = "
            SELECT
              COUNT(*) AS total_queries,
              ROUND(100 * SUM(first_contact_resolution='Yes') / NULLIF(COUNT(*),0), 1) AS fcr_pct,
              SEC_TO_TIME(AVG(NULLIF(TIME_TO_SEC(time_to_resolve),0))) AS avg_time_to_resolve
            FROM support_queries
            $where
        ";
        $kpis = $db->fetchAssociative($kpisSql, $params);

        // 3) Category split
        $categoriesSql = "
            SELECT category, COUNT(*) AS total
            FROM support_queries
            $where
            GROUP BY category
            ORDER BY total DESC
        ";
        $categories = $db->fetchAllAssociative($categoriesSql, $params);

        // 4) Top 10 controllable themes
        $themesSql = "
            SELECT complaint_theme, COUNT(*) AS total
            FROM support_queries
            $where
              AND category = 'Controllable'
            GROUP BY complaint_theme
            ORDER BY total DESC
            LIMIT 10
        ";
        $themes = $db->fetchAllAssociative($themesSql, $params);

        // 5) Daily trend
        $trendSql = "
            SELECT DATE_FORMAT(query_date, '%Y-%m-%d') AS d, COUNT(*) AS total
            FROM support_queries
            $where
            GROUP BY d
            ORDER BY d
        ";
        $trend = $db->fetchAllAssociative($trendSql, $params);

        // 6) Products list for dropdown
        $products = $db->fetchFirstColumn("
            SELECT DISTINCT product
            FROM support_queries
            WHERE product IS NOT NULL AND product <> ''
            ORDER BY product
        ");

        return $this->render('support/reports.html.twig', [
            'kpis'      => $kpis,
            'categories'=> $categories,
            'themes'    => $themes,
            'trend'     => $trend,
            'products'  => $products,
            'filters'   => ['start'=>$start, 'end'=>$end, 'product'=>$prod],
        ]);
    }

    // ---------------- CSV EXPORTS BELOW ----------------

    // helper to build WHERE for all exports
    private function buildWhere(array $q): array
    {
        $conds = [];
        $params = [];

        if (!empty($q['start']))   { $conds[] = 'query_date >= :start'; $params['start'] = $q['start']; }
        if (!empty($q['end']))     { $conds[] = 'query_date <= :end';   $params['end']   = $q['end']; }
        if (!empty($q['product'])) { $conds[] = 'product = :prod';      $params['prod']  = $q['product']; }

        $where = $conds ? ('WHERE ' . implode(' AND ', $conds)) : '';
        return [$where, $params];
    }

    #[Route('/support/reports/export/categories.csv', name: 'support_export_categories')]
    public function exportCategories(Request $req, Connection $db): StreamedResponse
    {
        [$where, $params] = $this->buildWhere($req->query->all());

        $sql = "
            SELECT category, COUNT(*) AS total
            FROM support_queries
            $where
            GROUP BY category
            ORDER BY total DESC
        ";
        $rows = $db->fetchAllAssociative($sql, $params);

        return $this->csvResponse('category_split.csv', ['Category','Total'], $rows, fn($r) => [
            $r['category'] ?? '', $r['total'] ?? 0
        ]);
    }

    #[Route('/support/reports/export/themes.csv', name: 'support_export_themes')]
    public function exportThemes(Request $req, Connection $db): StreamedResponse
    {
        [$where, $params] = $this->buildWhere($req->query->all());
        $where .= ($where ? ' AND ' : 'WHERE ') . "category = 'Controllable'";

        $sql = "
            SELECT complaint_theme, COUNT(*) AS total
            FROM support_queries
            $where
            GROUP BY complaint_theme
            ORDER BY total DESC
            LIMIT 1000
        ";
        $rows = $db->fetchAllAssociative($sql, $params);

        return $this->csvResponse('top_themes.csv', ['Theme','Total'], $rows, fn($r) => [
            $r['complaint_theme'] ?? '', $r['total'] ?? 0
        ]);
    }

    #[Route('/support/reports/export/trend.csv', name: 'support_export_trend')]
    public function exportTrend(Request $req, Connection $db): StreamedResponse
    {
        [$where, $params] = $this->buildWhere($req->query->all());

        $sql = "
            SELECT DATE_FORMAT(query_date, '%Y-%m-%d') AS day, COUNT(*) AS total
            FROM support_queries
            $where
            GROUP BY day
            ORDER BY day
        ";
        $rows = $db->fetchAllAssociative($sql, $params);

        return $this->csvResponse('daily_trend.csv', ['Date','Total'], $rows, fn($r) => [
            $r['day'] ?? '', $r['total'] ?? 0
        ]);
    }

    #[Route('/support/reports/export/raw.csv', name: 'support_export_raw')]
    public function exportRaw(Request $req, Connection $db): StreamedResponse
    {
        [$where, $params] = $this->buildWhere($req->query->all());

        $sql = "
          SELECT query_date, id_number, client_name, call_id, product, query_type,
                 ticket_id, reason_verbatim, reason_normalized, category, action_taken,
                 first_contact_resolution, time_to_resolve, complaint_theme, mojo_notes,
                 mojo_account, notes, in_period
          FROM support_queries
          $where
          ORDER BY query_date, client_name
          LIMIT 50000
        ";
        $rows = $db->fetchAllAssociative($sql, $params);

        $headers = ['query_date','id_number','client_name','call_id','product','query_type','ticket_id',
                    'reason_verbatim','reason_normalized','category','action_taken',
                    'first_contact_resolution','time_to_resolve','complaint_theme','mojo_notes',
                    'mojo_account','notes','in_period'];

        return $this->csvResponse('support_queries_filtered.csv', $headers, $rows, function($r) use ($headers){
            return array_map(fn($h) => $r[$h] ?? '', $headers);
        });
    }

    // Tiny CSV helper (UTF-8 BOM so Excel opens cleanly)
    private function csvResponse(string $filename, array $header, array $rows, callable $rowMapper): StreamedResponse
    {
        $response = new StreamedResponse(function() use ($header, $rows, $rowMapper) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM
            fputcsv($out, $header);
            foreach ($rows as $r) {
                fputcsv($out, $rowMapper($r));
            }
            fclose($out);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');
        return $response;
    }
}
