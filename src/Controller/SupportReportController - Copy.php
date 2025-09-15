<?php
namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
}
