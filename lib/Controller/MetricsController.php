<?php

/**
 * MetricsController
 *
 * Controller for exposing Prometheus metrics in text exposition format.
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TextPlainResponse;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * Controller for exposing Prometheus metrics.
 */
class MetricsController extends Controller
{
    /**
     * Constructor
     *
     * @param IRequest        $request   The request.
     * @param IAppConfig      $appConfig The app config service.
     * @param IConfig         $config    The system config service.
     * @param IDBConnection   $db        The database connection.
     * @param LoggerInterface $logger    Logger for error reporting.
     */
    public function __construct(
        IRequest $request,
        private readonly IAppConfig $appConfig,
        private readonly IConfig $config,
        private readonly IDBConnection $db,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct(
            appName: Application::APP_ID,
            request: $request
        );
    }//end __construct()

    /**
     * Expose Prometheus metrics.
     *
     * @return TextPlainResponse Plain text response with Prometheus metrics.
     *
     * @NoCSRFRequired
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-25
     */
    public function index(): TextPlainResponse
    {
        $lines = [];

        $appVersion = $this->appConfig->getValueString(Application::APP_ID, 'installed_version', '0.0.0');
        $phpVersion = PHP_VERSION;
        $ncVersion  = $this->config->getSystemValueString('version', '0.0.0');

        // Info gauge.
        $lines[] = '# HELP launchpad_info Application information';
        $lines[] = '# TYPE launchpad_info gauge';

        $labels  = sprintf(
            'version="%s",php_version="%s",nextcloud_version="%s"',
            $appVersion,
            $phpVersion,
            $ncVersion
        );
        $lines[] = 'launchpad_info{'.$labels.'} 1';

        // Up gauge.
        $lines[] = '# HELP launchpad_up Whether the application is up';
        $lines[] = '# TYPE launchpad_up gauge';
        $lines[] = 'launchpad_up 1';

        // Dashboards total by type.
        $this->collectDashboardMetrics(lines: $lines);

        // Widgets total.
        $widgetsTotal = $this->countTable(tableName: 'launchpad_widget_placements');
        $lines[]      = '# HELP launchpad_widgets_total Total number of widget placements';
        $lines[]      = '# TYPE launchpad_widgets_total gauge';
        $lines[]      = 'launchpad_widgets_total '.$widgetsTotal;

        // Tiles total.
        $tilesTotal = $this->countTable(tableName: 'launchpad_tiles');
        $lines[]    = '# HELP launchpad_tiles_total Total number of tiles';
        $lines[]    = '# TYPE launchpad_tiles_total gauge';
        $lines[]    = 'launchpad_tiles_total '.$tilesTotal;

        $body     = implode("\n", $lines)."\n";
        $response = new TextPlainResponse($body);
        $response->addHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');

        return $response;
    }//end index()

    /**
     * Collect dashboard count metrics grouped by type (personal/template).
     *
     * @param array $lines Reference to the metrics output lines.
     *
     * @return void
     */
    private function collectDashboardMetrics(array &$lines): void
    {
        $lines[] = '# HELP launchpad_dashboards_total Total dashboards by type';
        $lines[] = '# TYPE launchpad_dashboards_total gauge';

        try {
            $qb = $this->db->getQueryBuilder();
            $qb->select('type', $qb->createFunction('COUNT(*) AS cnt'))
                ->from('launchpad_dashboards')
                ->groupBy('type');

            $result = $qb->executeQuery();
            $rows   = $result->fetchAll();
            $result->closeCursor();

            $counts = [];
            foreach ($rows as $row) {
                if ($row['type'] !== null && $row['type'] !== '') {
                    $type = $row['type'];
                } else {
                    $type = 'personal';
                }

                if (isset($counts[$type]) === true) {
                    $counts[$type] = $counts[$type] + (int) $row['cnt'];
                } else {
                    $counts[$type] = (int) $row['cnt'];
                }
            }

            // Ensure both types are reported.
            if (isset($counts['personal']) === false) {
                $counts['personal'] = 0;
            }

            if (isset($counts['template']) === false) {
                $counts['template'] = 0;
            }

            foreach ($counts as $type => $count) {
                $lines[] = 'launchpad_dashboards_total{type="'.$type.'"} '.$count;
            }
        } catch (\Exception $e) {
            $this->logger->warning('Could not count dashboards for metrics', ['exception' => $e->getMessage()]);
            $lines[] = 'launchpad_dashboards_total{type="personal"} 0';
            $lines[] = 'launchpad_dashboards_total{type="template"} 0';
        }//end try
    }//end collectDashboardMetrics()

    /**
     * Count rows in a given table.
     *
     * @param string $tableName The table name.
     *
     * @return int The row count.
     */
    private function countTable(string $tableName): int
    {
        try {
            $qb = $this->db->getQueryBuilder();
            $qb->select($qb->createFunction('COUNT(*) AS cnt'))
                ->from($tableName);

            $result = $qb->executeQuery();
            $count  = (int) $result->fetchOne();
            $result->closeCursor();

            return $count;
        } catch (\Exception $e) {
            $this->logger->warning('Could not count '.$tableName.' for metrics', ['exception' => $e->getMessage()]);
            return 0;
        }
    }//end countTable()
}//end class
