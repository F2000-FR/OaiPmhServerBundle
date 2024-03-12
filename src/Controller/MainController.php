<?php

namespace Naoned\OaiPmhServerBundle\Controller;

use Naoned\OaiPmhServerBundle\Manager\OaiPmhRuler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Naoned\OaiPmhServerBundle\Exception\OaiPmhServerException;
use Naoned\OaiPmhServerBundle\Exception\BadVerbException;
use Naoned\OaiPmhServerBundle\Exception\NoRecordsMatchException;
use Naoned\OaiPmhServerBundle\Exception\NoSetHierarchyException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Naoned\OaiPmhServerBundle\Exception\IdDoesNotExistException;
use Naoned\OaiPmhServerBundle\DataProvider\DataProviderInterface;
use Symfony\Component\Routing\Annotation\Route;

class MainController extends AbstractController
{
    /** @var string[] */
    private array $availableVerbs = [
        'GetRecord',
        'Identify',
        'ListIdentifiers',
        'ListMetadataFormats',
        'ListRecords',
        'ListSets',
    ];

    /** @var array */
    private array $queryParams = [];

    public function __construct(readonly OaiPmhRuler $oOaiPmhRuler, readonly DataProviderInterface $oDataProvider)
    {
    }

    /**
     * @Route("", defaults={"_format"="xml"})
     * @param Request $oRequest
     * @return Response
     */
    public function index(Request $oRequest): Response
    {
        try {
            $this->oOaiPmhRuler->checkParamsUnicity($oRequest->getQueryString());

            $aArgs = $this->getAllArguments($oRequest);
            if (!array_key_exists('verb', $aArgs)) {
                throw new BadVerbException('The verb argument is missing');
            }

            $sVerb = $aArgs['verb'];
            if (!in_array($sVerb, $this->availableVerbs)) {
                throw new BadVerbException('Value of the verb argument is not a legal OAI-PMH verb.');
            }

            $sMethodName = $sVerb . 'Verb';

            return $this->$sMethodName($oRequest);
        } catch (\Exception $e) {
            $sCode = 'unknownError';
            if ($e instanceof OaiPmhServerException) {
                // remove «Exception» at end of class namespace
                $sCode = substr((new \ReflectionClass($e))->getShortName(), 0, -9);
                // lowercase first char
                $sCode[0] = strtolower(substr($sCode, 0, 1));
            } elseif ($e instanceof NotFoundHttpException) {
                $sCode = 'notFoundError';
            }

            return $this->error($sCode, $e->getMessage());
        }
    }

    /**
     * @param Request $oRequest
     * @return array
     */
    private function getAllArguments(Request $oRequest): array
    {
        return array_merge(
            $oRequest->query->all(),
            $oRequest->request->all()
        );
    }

    /**
     * @param string $sCode
     * @param string $sMessage
     * @return Response
     */
    private function error(string $sCode, string $sMessage = ''): Response
    {
        if (!$sMessage) {
            $sMessage = 'Unknown error';
        }

        return $this->render('@NaonedOaiPmhServer/error.xml.twig', [
            'code' => $sCode,
            'message' => $sMessage,
            'queryParams' => $this->queryParams,
        ]);
    }

    /**
     * @param Request $oRequest
     * @return Response
     * @throws \Naoned\OaiPmhServerBundle\Exception\BadArgumentException
     */
    private function identifyVerb(Request $oRequest): Response
    {
        return $this->render('@NaonedOaiPmhServer/identify.xml.twig', [
            'dataProvider' => $this->oDataProvider,
            'queryParams' => $this->oOaiPmhRuler->retrieveAndCheckArguments($this->getAllArguments($oRequest)),
        ]);
    }

    /**
     * @param Request $oRequest
     * @return Response
     * @throws OaiPmhServerException
     */
    private function getRecordVerb(Request $oRequest): Response
    {
        $this->queryParams = $this->oOaiPmhRuler->retrieveAndCheckArguments(
            $this->getAllArguments($oRequest),
            array(
                'metadataPrefix',
                'identifier',
            )
        );

        $this->oOaiPmhRuler->checkMetadataPrefix($this->queryParams);

        return $this->render('@NaonedOaiPmhServer/getRecord.xml.twig', [
            'record' => $this->retrieveRecord($this->queryParams['identifier']),
            'queryParams' => $this->queryParams,
            'metadataPrefix' => $this->queryParams['metadataPrefix'],
        ]);
    }

    /**
     * @param Request $oRequest
     * @return array
     * @throws OaiPmhServerException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    private function listCommon(Request $oRequest): array
    {
        $this->queryParams = $this->oOaiPmhRuler->retrieveAndCheckArguments(
            $this->getAllArguments($oRequest),
            ['metadataPrefix'],
            ['from', 'until', 'set'],
            ['resumptionToken']
        );

        if (!array_key_exists('resumptionToken', $this->queryParams)) {
            $this->oOaiPmhRuler->checkMetadataPrefix($this->queryParams);
        }

        $aSearchParams = $this->oOaiPmhRuler->getSearchParams($this->queryParams);
        if (isset($aSearchParams['set']) && !$this->oDataProvider->checkSupportSets()) {
            throw new NoSetHierarchyException();
        }

        $oFrom = isset($aSearchParams['from']) ? $this->oOaiPmhRuler->checkGranularity($aSearchParams['from']) : null;
        $oUntil = isset($aSearchParams['until']) ? $this->oOaiPmhRuler->checkGranularity($aSearchParams['until']) : null;

        $aRecords = $this->oDataProvider->getRecords($aSearchParams['set'] ?? null, $oFrom, $oUntil);
        if (!$aRecords) {
            throw new noRecordsMatchException();
        }

        $aResumptionData = $this->oOaiPmhRuler->getResumption($aRecords, $aSearchParams);

        return [
            'resumption' => $aResumptionData,
            'metadataPrefix' => $aSearchParams['metadataPrefix'],
            'queryParams' => $this->queryParams,
        ];
    }

    /**
     * @param Request $oRequest
     * @return Response
     * @throws NoRecordsMatchException|NoSetHierarchyException
     */
    private function listRecordsVerb(Request $oRequest): Response
    {
        return $this->render('@NaonedOaiPmhServer/listRecords.xml.twig', $this->listCommon($oRequest));
    }

    /**
     * @param Request $oRequest
     * @return Response
     * @throws NoRecordsMatchException|NoSetHierarchyException
     */
    private function listIdentifiersVerb(Request $oRequest): Response
    {
        return $this->render('@NaonedOaiPmhServer/listIdentifiers.xml.twig', $this->listCommon($oRequest));
    }

    /**
     * @param Request $oRequest
     * @return Response
     * @throws OaiPmhServerException
     */
    private function listMetadataFormatsVerb(Request $oRequest): Response
    {
        $this->queryParams = $this->oOaiPmhRuler->retrieveAndCheckArguments(
            $this->getAllArguments($oRequest),
            [],
            ['identifier']
        );

        // This is just for checking the record exists
        if (array_key_exists('identifier', $this->queryParams)) {
            $this->retrieveRecord($this->queryParams['identifier']);
        }

        return $this->render('@NaonedOaiPmhServer/listMetadataFormats.xml.twig', [
            'availableMetadata' => $this->oOaiPmhRuler->getAvailableMetadata(),
            'queryParams' => $this->queryParams,
        ]);
    }

    /**
     * @param Request $oRequest
     * @return Response
     * @throws OaiPmhServerException|\Psr\Cache\InvalidArgumentException
     */
    private function listSetsVerb(Request $oRequest): Response
    {
        $this->queryParams = $this->oOaiPmhRuler->retrieveAndCheckArguments(
            $this->getAllArguments($oRequest),
            [],
            [],
            ['resumptionToken']
        );

        if (!$this->oDataProvider->checkSupportSets()) {
            throw new NoSetHierarchyException();
        }

        $aSearchParams = $this->oOaiPmhRuler->getSearchParams($this->queryParams);
        $aResumptionData = $this->oOaiPmhRuler->getResumption($this->oDataProvider->getSets(), $aSearchParams);

        return $this->render('@NaonedOaiPmhServer/listSets.xml.twig', [
            'query' => $this->queryParams,
            'resumption' => $aResumptionData,
            'searchParams' => $aSearchParams,
            'queryParams' => $this->queryParams,
        ]);
    }

    /**
     * @param $sId
     * @return array
     * @throws IdDoesNotExistException|\Exception
     */
    private function retrieveRecord($sId): array
    {
        // Extract relevant identifier part
        $aParts = explode(':', $sId);
        $iId = end($aParts);

        $aRecord = $this->oDataProvider->getRecord($iId);
        if (!$aRecord) {
            throw new IdDoesNotExistException();
        }

        return $aRecord;
    }
}
