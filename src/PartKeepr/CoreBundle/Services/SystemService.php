<?php
namespace PartKeepr\CoreBundle\Services;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use PartKeepr\CoreBundle\System\OperatingSystem;
use PartKeepr\CoreBundle\System\SystemInformationRecord;
use PartKeepr\CronLogger\CronLoggerManager;
use PartKeepr\PartKeepr;
use PartKeepr\Util\Configuration;
use Doctrine\ORM\Version as ORMVersion;
use Doctrine\DBAL\Version as DBLAVersion;

class SystemService
{
    /**
     * @var EntityManager
     */
    private $entityManager;

    public function __construct(Registry $doctrine)
    {
        $this->entityManager = $doctrine->getManager();
    }

    /**
     * Returns a list of system information records.
     *
     * Please note that it is not defined which information is returned; the result
     * should be seen as "informational" to the system operator, not for automated purposes.
     *
     * @return SystemInformationRecord[] An array of SystemInformationRecords
     */
    public function getSystemInformation()
    {
        $aData = array();

        $aData[] = new SystemInformationRecord("Doctrine ORM", ORMVersion::VERSION, "Libraries");
        $aData[] = new SystemInformationRecord("Doctrine DBAL", DBLAVersion::VERSION, "Libraries");

        $aData[] = new SystemInformationRecord("PHP Version", phpversion(), "System");

        $os = new OperatingSystem();

        $aData[] = new SystemInformationRecord("Operating System Type", $os->getPlatform(), "System");
        $aData[] = new SystemInformationRecord("Operating System Release", $os->getRelease(), "System");

        $aData[] = new SystemInformationRecord("memory_limit", ini_get("memory_limit"), "PHP");
        $aData[] = new SystemInformationRecord("post_max_size", ini_get("post_max_size"), "PHP");
        $aData[] = new SystemInformationRecord("upload_max_filesize", ini_get("upload_max_filesize"), "PHP");
        $aData[] = new SystemInformationRecord("allow_url_fopen", ini_get("allow_url_fopen"), "PHP");
        $aData[] = new SystemInformationRecord("max_execution_time", ini_get("max_execution_time"), "PHP");

        $queryCache = get_class($this->entityManager->getConfiguration()->getQueryCacheImpl());
        $metadataCache = get_class($this->entityManager->getConfiguration()->getMetadataCacheImpl());

        $aData[] = new SystemInformationRecord("Query Cache Implementation", $queryCache, "PHP");
        $aData[] = new SystemInformationRecord("Metadata Cache Implementation", $metadataCache, "PHP");


        $aData[] = new SystemInformationRecord("PartKeepr Version", PartKeepr::getVersion(), "PartKeepr");


        foreach (Configuration::getOptions() as $key => $value) {
            // Hide passwords
            if ($key == "partkeepr.database.password" || $key == "partkeepr.migration.partdb.password") {
                $value = "<hidden>";
            }

            $aData[] = new SystemInformationRecord($key, $value, "PartKeepr Configuration Information");
        }

        return array("data" => $aData);
    }

    /**
     * Returns the database schema status.
     *
     * This method is usuall called once the user logs in, and alerts him if the schema is not up-to-date.
     *
     * Returns either status incomplete if the schema is not up-to-date, or complete if everything is OK.
     */
    public function getSystemStatus()
    {

        if (Configuration::getOption("partkeepr.cronjobs.disablecheck", false) === true) {
            // Skip cronjob tests
            $inactiveCronjobs = array();
        } else {
            $inactiveCronjobs = CronLoggerManager::getInstance()->getInactiveCronjobs();
        }


        return array(
            "inactiveCronjobCount" => count($inactiveCronjobs),
            "inactiveCronjobs" => $inactiveCronjobs,
            "schemaStatus" => $this->getSchemaStatus(),
        );
    }

    /**
     * Checks if the schema is up-to-date. If yes, it returns "complete", if not, it returns "incomplete".
     *
     * @param none
     *
     * @return string Either "complete" or "incomplete"
     */
    protected function getSchemaStatus()
    {
        $metadatas = $this->entityManager->getMetadataFactory()->getAllMetadata();

        $schemaTool = new SchemaTool($this->entityManager);

        $queries = $schemaTool->getUpdateSchemaSql($metadatas, true);

        if (count($queries) > 0) {
            return "incomplete";
        } else {
            return "complete";
        }
    }
}