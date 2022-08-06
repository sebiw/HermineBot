<?php

namespace App\Service;

use App\Core\Config as AppConfig;
use App\Core\RestClient;
use App\Core\StashcatMediator;
use App\Stashcat\ApiClient;
use App\Stashcat\Config;
use App\Stashcat\CryptoBox;

class AppService {

    private ApiClient $stashcatApiClient;

    private CryptoBox $stashcatCryptoBox;

    private StashcatMediator $stashcatMediator;

    private AppConfig $appConfig;

    /**
     * @throws \Exception
     */
    public function __construct()
    {
        // Stashcat API Setup
        $restClient = new RestClient( RestClient::RESPONSE_FORMAT_JSON );
        $this->appConfig = new AppConfig(include BASE_PATH . '/config/legacy/env.app.php');
        $stashcatConfig = new Config(include BASE_PATH . '/config/legacy/env.api.php');
        $this->stashcatApiClient = new ApiClient( $stashcatConfig , $restClient );
        $this->stashcatCryptoBox = new CryptoBox( $stashcatConfig );
        $this->stashcatMediator = new StashcatMediator( $this->appConfig , $this->stashcatApiClient , $this->stashcatCryptoBox );
    }

    /**
     * @return ApiClient
     */
    public function getApiClient(): ApiClient
    {
        return $this->stashcatApiClient;
    }

    /**
     * @return CryptoBox
     */
    public function getCryptoBot(): CryptoBox
    {
        return $this->stashcatCryptoBox;
    }

    /**
     * @return AppConfig
     */
    public function getAppConfig() : AppConfig
    {
        return $this->appConfig;
    }

    /**
     * @return StashcatMediator
     */
    public function getMediator(): StashcatMediator
    {
        return $this->stashcatMediator;
    }

}