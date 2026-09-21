<?php

namespace Naoned\OaiPmhServerBundle\Manager;

use Naoned\OaiPmhServerBundle\Exception\BadArgumentException;
use Naoned\OaiPmhServerBundle\Exception\BadResumptionTokenException;
use Naoned\OaiPmhServerBundle\Exception\CannotDisseminateFormatException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class OaiPmhRuler
{
    /** @var string */
    const CACHE_PREFIX = 'oaipmh_';

    /** @var int */
    const DEFAULT_STARTS = 0;

    /** @var FilesystemAdapter */
    private FilesystemAdapter $cacheSystem;

    /** @var int */
    private int $countPerLoad = 50;

    /** @var array|array[] */
    private static array $availableMetadata = [
        // This server currently supports only oai_dc Data format
        'oai_dc' => [
            'schema'            => 'http://www.openarchives.org/OAI/2.0/oai_dc.xsd',
            'metadataNamespace' => 'http://www.openarchives.org/OAI/2.0/oai_dc/',
        ]
    ];

    /**
     * @param ParameterBagInterface $oParams
     */
    public function __construct(ParameterBagInterface $oParams)
    {
        $a =  $oParams->all();
        $this->countPerLoad = $oParams->get('naoned.oaipmh_server.count_per_load');
        $this->cacheSystem = new FilesystemAdapter(self::CACHE_PREFIX);
    }

    /**
     * @param int $countPerLoad
     * @return void
     */
    public function setCountPerLoad(int $countPerLoad): void
    {
        $this->countPerLoad = $countPerLoad;
    }

    /**
     * @param string $sToken
     * @return string
     */
    private function getCacheKey(string $sToken): string
    {
        return self::CACHE_PREFIX . $sToken;
    }

    /**
     * @return array|array[]
     */
    public function getAvailableMetadata(): array
    {
        return self::$availableMetadata;
    }

    /**
     * @param array $aQueryParams
     * @return array
     * @throws BadResumptionTokenException|\Psr\Cache\InvalidArgumentException
     */
    public function getSearchParams(array $aQueryParams): array
    {
        if (array_key_exists('resumptionToken', $aQueryParams) && !empty($aQueryParams['resumptionToken'])) {
            $oCacheItem = $this->cacheSystem->getItem($this->getCacheKey($aQueryParams['resumptionToken']));

            $aSearchParams = $oCacheItem->get();
            if (!$aSearchParams
                || !isset($aSearchParams['verb'], $aQueryParams['verb'])
                || ($aSearchParams['verb'] !== $aQueryParams['verb'])
            ) {
                throw new BadResumptionTokenException();
            }
        } else {
            $aSearchParams = $aQueryParams;
            $aSearchParams['starts'] = self::DEFAULT_STARTS;
            $aSearchParams['ends']   = self::DEFAULT_STARTS + $this->countPerLoad - 1;
        }

        return $aSearchParams;
    }

    /**
     * @return string
     */
    public function generateResumptionToken(): string
    {
        return uniqid();
    }

    /**
     * @param array $aItems
     * @param array $aSearchParams
     * @return array
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getResumption(array $aItems, array $aSearchParams): array
    {
        $iMax = count($aItems) - 1;

        $aResumptionData = [];
        $aResumptionData['next'] = false;

        if ($aSearchParams['ends'] < $iMax) {
            $aResumptionData['next'] = true;
            $aResumptionData['token'] = $this->generateResumptionToken();
            $aResumptionData['expiresOn'] = time() + 604800;

            $oCacheItem = $this->cacheSystem->getItem($this->getCacheKey($aResumptionData['token']));
            $oCacheItem->set(array_merge(
                $aSearchParams,
                array(
                    'starts' => $aSearchParams['starts'] + $this->countPerLoad,
                    'ends'   => $aSearchParams['starts'] + $this->countPerLoad * 2,
                )
            ));
            $this->cacheSystem->save($oCacheItem);
        }

        $aResumptionData['starts'] = $aSearchParams['starts'];
        $aResumptionData['ends'] = min($iMax, $aSearchParams['starts'] + ($this->countPerLoad - 1));
        $aResumptionData['totalCount'] = count($aItems);
        $aResumptionData['items'] = $aItems;
        $aResumptionData['isFirst'] = ($aResumptionData['starts'] == self::DEFAULT_STARTS);
        $aResumptionData['isLast'] = ($aResumptionData['ends'] == $iMax);

        return $aResumptionData;
    }

    /**
     * @param array $aQueryParams
     * @return void
     * @throws CannotDisseminateFormatException
     */
    public function checkMetadataPrefix(array $aQueryParams): void
    {
        if (!in_array($aQueryParams['metadataPrefix'], array_keys(self::$availableMetadata))) {
            throw new cannotDisseminateFormatException();
        }
    }

    /**
     * Retrieve arguments and check requirements are fulfilled
     * @param array $aArguments
     * @param array $aRequired
     * @param array $aOptional
     * @param array $aExclusive
     * @return array
     * @throws BadArgumentException
     */
    public function retrieveAndCheckArguments(array $aArguments, array $aRequired = [], array $aOptional = [], array $aExclusive = []): array
    {
        $bFound = false;
        $aQueryParams = [];
        foreach ($aExclusive as $name) {
            if (array_key_exists($name, $aArguments)) {
                $aQueryParams[$name] = $aArguments[$name];
                $bFound = true;
            }
        }

        if (!$bFound) {
            foreach ($aRequired as $name) {
                if (!array_key_exists($name, $aArguments)) {
                    throw new BadArgumentException('The request is missing required arguments');
                }
                $aQueryParams[$name] = $aArguments[$name];
            }
            foreach ($aOptional as $name) {
                if (array_key_exists($name, $aArguments)) {
                    $aQueryParams[$name] = $aArguments[$name];
                }
            }
        }

        $this->checkNoOtherArguments($aQueryParams, $aArguments);

        if (array_key_exists('verb', $aArguments)) {
            $aQueryParams['verb'] = $aArguments['verb'];
        }

        return $aQueryParams;
    }

    /**
     * @param array $aQueryParams
     * @param array $aArguments
     * @return void
     * @throws BadArgumentException
     */
    private function checkNoOtherArguments(array $aQueryParams, array $aArguments): void
    {
        unset($aArguments['verb']);
        if (count(array_diff(array_keys($aArguments), array_keys($aQueryParams)))) {
            throw new BadArgumentException('The request includes illegal arguments');
        }
    }

    /**
     * @param string $sDate
     * @return \DateTime
     * @throws BadArgumentException
     */
    public function checkGranularity(string $sDate): \DateTime
    {
        if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $sDate)) {
            throw new BadArgumentException('Date boundaries is/are not correct');
        }
        return new \DateTime($sDate);
    }

    /**
     * @param string|null $sQueryString
     * @return void
     * @throws BadArgumentException
     */
    public function checkParamsUnicity(string $sQueryString = null): void
    {
        if (!$sQueryString) {
            return;
        }

        $aParams = [];
        foreach (explode('&', $sQueryString) as $sParam) {
            $sName = str_contains('=', $sParam) ? explode('=', $sParam, 2)[0] : $sParam;

            if (isset($aParams[$sName])) {
                throw new BadArgumentException('The request includes a repeated argument.');
            }
            $aParams[$sName] = true;
        }
        unset($aParams);
    }
}
