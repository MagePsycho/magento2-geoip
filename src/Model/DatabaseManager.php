<?php
/**
 * This file is part of the MagePsycho_GeoIp package.
 *
 * @author   Raj KB <magepsycho@gmail.com>
 * @license  Open Software License (OSL 3.0)
 */
namespace MagePsycho\GeoIp\Model;

use DateTime;
use DateTimeInterface;
use MagePsycho\GeoIp\Api\DatabaseManagerInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Io\File;
use RuntimeException;
use splitbrain\PHPArchive\Tar;

/**
 * Downloads and stores MaxMind GeoLite2 .mmdb databases under var/maxmind/.
 *
 * Flow per edition: fetch tar.gz over HTTPS from MaxMind authenticated via the
 * License Key, stream to a temp file under var/maxmind/_extract/, untar via
 * splitbrain\\PHPArchive\\Tar, walk the extracted version-stamped subfolder to
 * find the .mmdb, then atomically move it to the well-known final path.
 */
class DatabaseManager implements DatabaseManagerInterface
{
    private const DB_DIRECTORY    = 'maxmind';
    private const EXTRACT_DIRECTORY = 'maxmind/_extract';
    private const CITY_DB_FILE    = 'GeoLite2-City.mmdb';
    private const ASN_DB_FILE     = 'GeoLite2-ASN.mmdb';
    private const DOWNLOAD_URL    = 'https://download.maxmind.com/app/geoip_download';

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var DirectoryList
     */
    private $directoryList;

    /**
     * @var File
     */
    private $file;

    /**
     * @var Tar
     */
    private $tar;

    public function __construct(
        ConfigInterface $config,
        DirectoryList $directoryList,
        File $file,
        Tar $tar
    ) {
        $this->config = $config;
        $this->directoryList = $directoryList;
        $this->file = $file;
        $this->tar = $tar;
    }

    /**
     * @inheritDoc
     */
    public function isInstalled(): bool
    {
        return file_exists($this->getCityDatabasePath()) && file_exists($this->getAsnDatabasePath());
    }

    /**
     * @inheritDoc
     */
    public function getInstalledAt(): ?DateTimeInterface
    {
        $newest = 0;
        foreach ([$this->getCityDatabasePath(), $this->getAsnDatabasePath()] as $path) {
            if (file_exists($path)) {
                $newest = max($newest, (int) filemtime($path));
            }
        }
        if ($newest === 0) {
            return null;
        }
        return (new DateTime())->setTimestamp($newest);
    }

    /**
     * @inheritDoc
     */
    public function getCityDatabasePath(): string
    {
        return $this->dbDirectory() . DIRECTORY_SEPARATOR . self::CITY_DB_FILE;
    }

    /**
     * @inheritDoc
     */
    public function getAsnDatabasePath(): string
    {
        return $this->dbDirectory() . DIRECTORY_SEPARATOR . self::ASN_DB_FILE;
    }

    /**
     * @inheritDoc
     */
    public function download(): void
    {
        $licenseKey = $this->config->getLicenseKey();
        if ($licenseKey === '') {
            throw new RuntimeException(
                'MaxMind License Key is required for auto-download. '
                . 'Set it at Stores → Configuration → MagePsycho → GeoIP, or drop the '
                . 'GeoLite2-City.mmdb / GeoLite2-ASN.mmdb files into var/maxmind/ manually.'
            );
        }

        $this->downloadEdition('GeoLite2-City', $licenseKey, self::CITY_DB_FILE);
        $this->downloadEdition('GeoLite2-ASN',  $licenseKey, self::ASN_DB_FILE);
    }

    /**
     * Download a single edition, extract its .mmdb, replace the existing file.
     *
     * Auth: license_key passed as a query-string parameter. MaxMind's
     * download.maxmind.com endpoint accepted HTTP Basic Auth historically
     * but currently returns 401 for that scheme — only the query-string
     * pattern works. Verified against the live API.
     */
    private function downloadEdition(string $editionId, string $licenseKey, string $targetFile): void
    {
        $dbDir = $this->dbDirectory();
        $extractDir = $this->varDirectory() . DIRECTORY_SEPARATOR . self::EXTRACT_DIRECTORY;
        $this->file->checkAndCreateFolder($dbDir);
        $this->file->checkAndCreateFolder($extractDir);

        $tarGzPath = $extractDir . DIRECTORY_SEPARATOR . $editionId . '.tar.gz';

        // --- Download tar.gz over HTTPS, license key as query-string param ---
        //
        // We use vanilla curl_* directly instead of Magento\Framework\HTTP\Client\Curl
        // because the Magento wrapper reports the FIRST status of a redirect chain
        // (the 302) even when CURLOPT_FOLLOWLOCATION is set, which makes accurate
        // success/failure detection impossible. MaxMind's download endpoint always
        // redirects to a regional CDN URL, so this matters.
        $url = self::DOWNLOAD_URL
            . '?edition_id=' . urlencode($editionId)
            . '&suffix=tar.gz'
            . '&license_key=' . urlencode($licenseKey);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 180);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $err = $errno === 0 ? '' : curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0 || $body === false) {
            throw new RuntimeException(sprintf(
                'MaxMind download for %s failed: %s',
                $editionId,
                $err !== '' ? $err : 'unknown curl error'
            ));
        }
        if ($status !== 200) {
            throw new RuntimeException(sprintf(
                'MaxMind download for %s returned HTTP %d. Check your License Key has GeoLite2 download access.',
                $editionId,
                $status
            ));
        }
        if (!is_string($body) || strlen($body) < 1024) {
            throw new RuntimeException(sprintf(
                'MaxMind download for %s returned a suspiciously small body — credentials may be wrong.',
                $editionId
            ));
        }

        $this->file->write($tarGzPath, $body);

        // --- Extract tar.gz, find the .mmdb, move it to the target path ---
        $this->tar->open($tarGzPath);
        $this->tar->extract($extractDir);

        $extractedDbPath = $this->findMmdb($extractDir, $targetFile);
        if ($extractedDbPath === null) {
            $this->cleanupExtract($extractDir, $tarGzPath);
            throw new RuntimeException(sprintf(
                'Could not locate %s in the extracted archive for %s.',
                $targetFile,
                $editionId
            ));
        }

        $finalPath = $dbDir . DIRECTORY_SEPARATOR . $targetFile;
        if (file_exists($finalPath)) {
            unlink($finalPath);
        }
        if (!rename($extractedDbPath, $finalPath)) {
            $this->cleanupExtract($extractDir, $tarGzPath);
            throw new RuntimeException(sprintf(
                'Could not move %s into place at %s.',
                $editionId,
                $finalPath
            ));
        }

        $this->cleanupExtract($extractDir, $tarGzPath);
    }

    /**
     * Walk the extracted directory to find the named .mmdb file.
     * Archives put it under a YYYYMMDD-versioned subfolder.
     */
    private function findMmdb(string $root, string $filename): ?string
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $entry) {
            if ($entry->isFile() && $entry->getFilename() === $filename) {
                return $entry->getPathname();
            }
        }
        return null;
    }

    /**
     * Best-effort cleanup of the extract dir + tar.gz.
     *
     * Skips entries that no longer exist (e.g. moved into place by the caller).
     * Cleanup failures are tolerated silently — the calling code already
     * reported the real outcome of the download.
     *
     * @param string $extractDir
     * @param string $tarGzPath
     * @return void
     */
    private function cleanupExtract(string $extractDir, string $tarGzPath): void
    {
        if (file_exists($tarGzPath) && is_writable(dirname($tarGzPath))) {
            unlink($tarGzPath);
        }
        if (!is_dir($extractDir)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($extractDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $entry) {
            $path = $entry->getPathname();
            if (!file_exists($path)) {
                continue;
            }
            if ($entry->isDir()) {
                if (is_writable(dirname($path))) {
                    rmdir($path);
                }
            } elseif (is_writable($path)) {
                unlink($path);
            }
        }
    }

    /**
     * Absolute path to var/maxmind/ (created on first use elsewhere).
     */
    private function dbDirectory(): string
    {
        return $this->varDirectory() . DIRECTORY_SEPARATOR . self::DB_DIRECTORY;
    }

    private function varDirectory(): string
    {
        return $this->directoryList->getPath(DirectoryList::VAR_DIR);
    }
}
