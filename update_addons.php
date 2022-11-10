<?php

declare(strict_types = 1);

define('_PATH_ADDONS_', __DIR__ . DIRECTORY_SEPARATOR . 'AddOns');
define('_PATH_DOWNLOADS_', __DIR__ . DIRECTORY_SEPARATOR . 'Downloads');
define('_API_CONTEXT_', [
    'http' => [
            'method' => 'GET',
            'header' => [
                    'User-Agent: PHP'
            ]
    ]
]);

// project => user
$addOns_repos = [
    'DBM-WoTLKC' => 'DeadlyBossMods',
//    'Bagnon' => 'tullamods',
    'tullaRange' => 'tullamods',
    'OmniCC' => 'tullamods',
    'Questie' => 'Questie',
    'leatrix-plus-wrath' => 'leatrix',
    'leatrix-maps-wrath' => 'leatrix',
    'ClassicLootManager' => 'ClassicLootManager',
];

class Files
{
    public static function checkDir(string $path) : bool
    {
        if ($path !== false AND is_dir($path)) return true;
        return false;
    }

    public static function checkFile(string $path) : bool
    {
        if (file_exists($path)) return true;
        return false;
    }

    public static function makeDir(string $path)
    {
        mkdir($path, 0777, true);
    }

    public static function extractZip(string $from, string $to) : bool
    {
        $zip = new ZipArchive;
        if ($zip->open($from) === TRUE) {
            $zip->extractTo($to);
            $zip->close();
            return true;
        } else {
            return false;
        }
    }
}

class GitRepo
{
    private string $git_project;
    private string $git_user;
    private array $api_content;

    function __construct(string $git_project, string $git_user)
    {
        $this->git_project = $git_project;
        $this->git_user = $git_user;
    }

    public function getReleaseUrl() : array
    {
        $result = [
            'error' => false,
            'msg' => '',
        ];

        $this->SetContent();

        if (empty($this->api_content['assets'])) {
            $result['error'] = true;
            $result['msg'] = 'Assets not found';
            return $result;
        }

        $result['msg'] = $this->findZip($this->api_content['assets']);

        if (empty($result['msg'])) {
            $result['error'] = true;
            $result['msg'] = 'Zipfile not found!';
            return $result;
        }

        return $result;
    }

    private function SetContent()
    {
        $context = stream_context_create(_API_CONTEXT_);
        $url = 'https://api.github.com/repos/'.$this->git_user.'/'.$this->git_project.'/releases/latest';

        $this->api_content = json_decode(file_get_contents($url, false, $context), true);
    }

    private function findZip(array $assets) : array
    {
        foreach ($assets as $asset) {
            if (str_contains($asset['name'], '.zip')) return [
                'name' => $asset['name'],
                'url' => $asset['browser_download_url']
            ];
        }
        return [];
    }

}

// check prerequisites
if (!Files::checkDir(_PATH_ADDONS_)) exit(_PATH_ADDONS_ . ' not found!');
if (!Files::checkDir(_PATH_DOWNLOADS_)) Files::makeDir(_PATH_DOWNLOADS_);

// start updating addons
echo 'Updating'.PHP_EOL;

foreach ($addOns_repos as $git_project => $git_user) {

    echo PHP_EOL . 'Addon: ' . $git_project . PHP_EOL;

    $gitRepo = new GitRepo($git_project, $git_user);

    $api_result = $gitRepo->getReleaseUrl();

    if ($api_result['error']) {
        echo ($api_result['msg'].PHP_EOL);
        continue;
    }

    $addon_download_dir =  _PATH_DOWNLOADS_ . DIRECTORY_SEPARATOR . $git_project;
    if (!Files::checkDir($addon_download_dir)) Files::makeDir($addon_download_dir);

    $addon_download_file = $addon_download_dir . DIRECTORY_SEPARATOR . $api_result['msg']['name'];
    if (Files::checkFile($addon_download_file)) {
        echo 'Up to date!'.PHP_EOL;
        continue;
    }

    echo 'Downloading'.PHP_EOL;

    // TODO handle file_get/put_content errors
    $context = stream_context_create(_API_CONTEXT_);
    $zip_content = file_get_contents($api_result['msg']['url'], false, $context);
    file_put_contents($addon_download_file, $zip_content);

    echo 'Extracting'.PHP_EOL;

    $unzip_result = Files::extractZip($addon_download_file, _PATH_ADDONS_);

    if ($unzip_result) {
        echo 'Update compete!' . PHP_EOL;
    } else {
        echo 'Extracting error' . PHP_EOL;
    }
}

echo PHP_EOL . 'All done! \(^-^)/';