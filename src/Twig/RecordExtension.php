<?php

namespace Naoned\OaiPmhServerBundle\Twig;

use Naoned\OaiPmhServerBundle\DataProvider\DataProviderInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class RecordExtension extends AbstractExtension
{
    public function __construct(readonly DataProviderInterface $oDataProvider)
    {
    }

    /**
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return array(
            new TwigFunction('get_record_sets', [$this, 'getRecordSets']),
            new TwigFunction('dublinize_record', [$this, 'dublinizeRecord']),
            new TwigFunction('get_record_id', [$this, 'getRecordId']),
            new TwigFunction('get_record_updated', [$this, 'getRecordUpdated']),
            new TwigFunction('get_record_thumb', [$this, 'getRecordThumb']),
        );
    }

    /**
     * @param array $aRecord
     * @return array
     */
    public function getRecordSets(array $aRecord): array
    {
        return $this->oDataProvider->getSetsForRecord($aRecord);
    }

    /**
     * @param array $aRecord
     * @return array
     */
    public function dublinizeRecord(array $aRecord): array
    {
        return $this->oDataProvider->dublinizeRecord($aRecord);
    }

    /**
     * @param array $aRecord
     * @return string
     */
    public function getRecordId(array $aRecord): string
    {
        return $this->oDataProvider->getRecordId($aRecord);
    }

    /**
     * @param array $aRecord
     * @return \DateTime|string
     */
    public function getRecordUpdated(array $aRecord): \DateTime|string
    {
        return $this->oDataProvider->getRecordUpdated($aRecord);
    }

    /**
     * @param array $aRecord
     * @return string
     */
    public function getRecordThumb(array $aRecord)
    {
        return $this->oDataProvider->getRecordThumb($aRecord);
    }

    // for a service we need a name
    public function getName(): string
    {
        return 'oaipmh_record';
    }
}
