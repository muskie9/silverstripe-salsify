<?php

namespace Dynamic\Salsify\Model;

use Dynamic\Salsify\Task\ImportTask;
use Exception;
use JsonMachine\JsonMachine;
use SilverStripe\ORM\DataObject;

/**
 * Class Mapper
 * @package Dynamic\Salsify\Model
 */
class Mapper extends Service
{
    /**
     * @var
     */
    private $file;

    /**
     * @var JsonMachine
     */
    private $productStream;

    /**
     * @var JsonMachine
     */
    private $assetStream;

    /**
     * @var array
     */
    private $currentUniqueFields;

    /**
     * @var int
     */
    private $importCount = 0;

    /**
     * Mapper constructor.
     * @param string $importerKey
     * @param $file
     * @throws \Exception
     */
    public function __construct($importerKey, $file)
    {
        parent::__construct($importerKey);
        if (!$this->config()->get('mapping')) {
            throw  new Exception('A Mapper needs a mapping');
        }

        $this->file = $file;
        $this->productStream = JsonMachine::fromFile($file, '/4/products');
        $this->resetAssetStream();
    }

    /**
     * Maps the data
     * @throws \Exception
     */
    public function map()
    {
        foreach ($this->productStream as $name => $data) {
            foreach ($this->config()->get('mapping') as $class => $mappings) {
                $this->mapToObject($class, $mappings, $data);
                $this->currentUniqueFields = [];
            }
        }
        ImportTask::echo("Imported and updated $this->importCount products.");
    }

    /**
     * @param string|DataObject $class
     * @param array $mappings The mapping for a specific class
     * @param array $data
     * @throws \Exception
     */
    private function mapToObject($class, $mappings, $data)
    {
        $object = $this->findObjectByUnique($class, $mappings, $data);
        if (!$object) {
            $object = $class::create();
        }

        $fieldTypes = $this->config()->get('field_types');

        $firstUniqueKey = array_keys($this->uniqueFields($class, $mappings))[0];
        $firstUniqueValue = $data[$mappings[$firstUniqueKey]['salsifyField']];
        ImportTask::echo("Updating $firstUniqueKey $firstUniqueValue");

        foreach ($mappings as $dbField => $salsifyField) {
            $type = 'Raw';
            if (is_array($salsifyField)) {
                if (!array_key_exists('salsifyField', $salsifyField)) {
                    continue;
                }

                if (array_key_exists('type', $salsifyField)) {
                    if (array_key_exists($salsifyField['type'], $fieldTypes)) {
                        $type = $fieldTypes[$salsifyField['type']];
                    }
                }

                $salsifyField = $salsifyField['salsifyField'];
            }

            if (!array_key_exists($salsifyField, $data)) {
                ImportTask::echo("Skipping mapping for field $salsifyField for $firstUniqueKey $firstUniqueValue");
                continue;
            }

            // TODO - handle has_many and many_many fields
            $object->$dbField = $this->handleType($type, $data[$salsifyField], $dbField, $class);
        }

        if ($object->isChanged()) {
            $object->write();
            $this->importCount++;
        } else {
            ImportTask::echo("$firstUniqueKey $firstUniqueValue was not changed.");
        }
    }

    /**
     * @param string $class
     * @param array $mappings
     * @param array $data
     *
     * @return \SilverStripe\ORM\DataObject
     */
    private function findObjectByUnique($class, $mappings, $data)
    {
        $uniqueFields = $this->uniqueFields($mappings);
        // creates a filter
        $filter = [];
        foreach ($uniqueFields as $dbField => $salsifyField) {
            // adds unique fields to filter
            $filter[$dbField] = $data[$salsifyField];
        }

        return DataObject::get($class)->filter($filter)->first();
    }

    /**
     * Gets a list of all the unique field keys
     *
     * @param array $mappings
     * @return array
     */
    private function uniqueFields($mappings)
    {
        // cached after first map
        if (!empty($this->currentUniqueFields)) {
            return $this->currentUniqueFields;
        }

        $uniqueFields = [];
        foreach ($mappings as $dbField => $salsifyField) {
            if (!is_array($salsifyField)) {
                continue;
            }

            if (!array_key_exists('unique', $salsifyField) ||
                !array_key_exists('salsifyField', $salsifyField)) {
                continue;
            }

            if ($salsifyField['unique'] !== true) {
                continue;
            }

            $uniqueFields[$dbField] = $salsifyField['salsifyField'];
        }

        $this->currentUniqueFields = $uniqueFields;
        return $uniqueFields;
    }

    /**
     * @param int $type
     * @param string|int $value
     * @param string $dbField
     * @return mixed
     * @throws \Exception
     */
    private function handleType($type, $value, $dbField, $class)
    {
        $fieldTypes = $this->config()->get('field_types');
        if ($this->hasMethod("handle{$type}Type")) {
            return $this->{"handle{$type}Type"}($value, $dbField, $class);
        } else {
            ImportTask::echo("{$type} is not a valid type. skipping.");
        }
        return '';
    }

    /**
     * @return \JsonMachine\JsonMachine
     */
    public function getAssetStream()
    {
        return $this->assetStream;
    }

    /**
     *
     */
    public function resetAssetStream()
    {
        $this->assetStream = JsonMachine::fromFile($this->file, '/3/digital_assets');
    }
}
