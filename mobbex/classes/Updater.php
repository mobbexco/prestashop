<?php

namespace Mobbex;

class Updater
{
    /** @var ZipArchive */
    public $zip;

    /** Github API URL */
    public $githubApi = 'https://api.github.com';

    /** Repository URI */
    public $repo;

    public $latestRelease;

    /**
     * Constructor.
     * 
     * Set repository URI and initialize ZIP manager.
     * 
     * @param string $repo Repository URI to get updates.
     */
    public function __construct($repo = 'mobbexco/prestashop')
    {
        $this->zip  = new \ZipArchive();
        $this->repo = $repo;
    }

    /**
     * Update the module to latest version.
     * 
     * @param \Module $module
     * 
     * @throws \PrestaShopException
     */
    public function updateVersion($module, $cleanUpdate = false)
    {
        $module->disable(true);

        $release   = $this->getLatestRelease();
        $assetPath = $this->downloadAsset($release);

        if ($this->zip->open($assetPath) !== true)
            throw new \PrestaShopException('Error extracting Mobbex release');

        // if it is a clean update, remove the module directory first
        if ($cleanUpdate)
            $this->removeDirectory(_PS_MODULE_DIR_ . $module->name);

        // Extract asset file
        $this->zip->extractTo(_PS_MODULE_DIR_);
        $this->zip->close();

        // Delete asset file and enable plugin
        unlink($assetPath);
        $module->enable();
    }

    /**
     * Check if there are updates available.
     * 
     * @param string $version
     * 
     * @return bool
     */
    public function hasUpdates($version)
    {
        $latestRelease = $this->getLatestRelease();

        return version_compare($version, isset($latestRelease['tag_name']) ? $latestRelease['tag_name'] : $version, '<');
    }

    /**
     * Remove a directory recursively.
     * 
     * @param string $directory
     */
    public function removeDirectory($directory)
    {
        $files = glob("$directory/*");

        foreach ($files as $file)
            is_file($file) && !is_link($file) ? unlink($file) : $this->removeDirectory($file);
    }

    /**
     * Retrieve latest release data from Github Mobbex repository.
     * 
     * @return string|false
     * 
     * @throws \PrestaShopException
     */
    public function getLatestRelease()
    {
        if ($this->latestRelease)
            return $this->latestRelease;

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL            => "$this->githubApi/repos/$this->repo/releases/latest",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_HTTPHEADER     => ['user-agent: php'],
        ]);

        $response = curl_exec($curl);
        $error    = curl_error($curl);

        curl_close($curl);

        if ($error)
            throw new \PrestaShopException('Error getting latest release data #' . $error);

        return $this->latestRelease = json_decode($response, true);
    }

    /**
     * Download an asset from a Github release.
     * 
     * @param array $release
     * 
     * @return string|false
     * 
     * @throws \PrestaShopException
     */
    public function downloadAsset($release)
    {
        if (empty($release['assets'][0]['browser_download_url']))
            return false;

        // Get asset content
        $downloadUrl  = $release['assets'][0]['browser_download_url'];
        $assetContent = file_get_contents($downloadUrl);

        // Put content into a file
        $assetPath = _PS_MODULE_DIR_ . basename($downloadUrl);
        $result    = file_put_contents($assetPath, $assetContent);

        if (!$result || !is_file($assetPath))
            throw new \PrestaShopException('Error downloading Mobbex release');

        return $assetPath;
    }
}