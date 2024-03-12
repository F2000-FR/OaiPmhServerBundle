<?php

namespace Naoned\OaiPmhServerBundle\DataProvider;

interface DataProviderInterface
{
    /**
     * @return string Repository name
     */
    public function getRepositoryName(): string;

    /**
     * @return string Repository admin email
     */
    public function getAdminEmail(): string;

    /**
     * @return \DateTime|string     Repository earliest update change on data
     */
    public function getEarliestDatestamp(): \DateTime|string;

    /**
     * @param string $identifier [description]
     * @return array
     */
    public function getRecord(string $id): array;

    /**
     * Search for records
     * @param String|null $set Title of wanted set
     * @param \DateTime|null $from Date of last change «from»
     * @param \DateTime|null $until Date of last change «until»
     * @return array        List of items
     */
    public function getRecords(string $set = null, \DateTime $from = null, \DateTime $until = null): array;

    /**
     * must return an array of arrays with keys «identifier» and «name»
     * @return array List of all sets, with identifier and name
     */
    public function getSets(): array;

    /**
     * Tell me, this «record», in which «set» is it ?
     * @param array $aRecord An item of elements furnished by getRecords method
     * @return array         List of sets, the record belong to
     */
    public function getSetsForRecord(array $aRecord): array;

    /**
     * Transform the provided record in an array with Dublin Core, «dc_title»  style
     * @param array $aRecord An item of elements furnished by getRecords method
     * @return array         Dublin core data
     */
    public function dublinizeRecord(array $aRecord): array;

    /**
     * Check if sets are supported by data provider
     * @return boolean check
     */
    public function checkSupportSets(): bool;

    /**
     * Get identifier of id
     * @param array $aRecord An item of elements furnished by getRecords method
     * @return string        Record Id
     */
    public static function getRecordId(array $aRecord): string;

    /**
     * Get thumb of id
     * @param array $aRecord An item of elements furnished by getRecords method
     * @return string        Record thumb URI
     */
    public static function getRecordThumb(array $aRecord): string;

    /**
     * Get last change date
     * @param array $aRecord An item of elements furnished by getRecords method
     * @return \DateTime|string     Record last change
     */
    public static function getRecordUpdated(array $aRecord): \DateTime|string;
}
