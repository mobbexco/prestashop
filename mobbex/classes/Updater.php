<?php

class MobbexUpdater
{
    /** @var ZipArchive */
    public $zip;

    public $githubApi = 'https://api.github.com/';

    public $latestRelease;

    public function __construct()
    {
        $this->zip = new \ZipArchive();
    }

    /**
     * Update the plugin to latest version.
     * 
     * @param Module $module
     */
    public function updateVersion($module)
    {
        $module->disable(true);

        $release   = $this->getLatestRelease();
        $assetPath = $this->downloadAsset($release);

        if ($this->zip->open($assetPath) !== true)
            throw new PrestaShopException('Error extracting Mobbex release');

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
        return version_compare($version, $this->getLatestRelease()['tag_name'], '<');
    }

    /**
     * Retrieve latest release data from Github Mobbex repository.
     * 
     * @return string|false
     * 
     * @throws PrestaShopException
     */
    public function getLatestRelease()
    {
        if ($this->latestRelease)
            return $this->latestRelease;

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL            => "{$this->githubApi}repos/mobbexco/prestashop/releases/latest",
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
            throw new PrestaShopException('Error getting latest release data #' . $error);

        return $this->latestRelease = json_decode($response, true);
    }

    /**
     * Download an asset from a Github release.
     * 
     * @param array $release
     * 
     * @return string|false
     * 
     * @throws PrestaShopException
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
            throw new PrestaShopException('Error downloading Mobbex release');

        return $assetPath;
    }
}