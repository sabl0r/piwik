<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * @category Piwik
 * @package Piwik
 */

/**
 * TODO
 */
class Piwik_Archive_DataCollection
{
    /**
     * TODO
     */
    public $data = array(); // TODO: make private
    
    /**
     * TODO
     */
    private $dataNames;
    
    /**
     * TODO
     */
    private $dataType;
    
    /**
     * TODO
     */
    private $defaultRow;
    
    /**
     * TODO
     */
    private $sites; // TODO: should not be optional. (same w/ below)
    
    /**
     * TODO
     */
    private $periods;
    
    /**
     * TODO
     */
    public function __construct($dataNames, $dataType, $sites, $periods, $defaultRow = null)
    {
        $this->dataNames = $dataNames;
        $this->dataType = $dataType;
        
        if ($defaultRow === null) {
            $defaultRow = array_fill_keys($dataNames, 0);
        }
        $this->sites = $sites;
        $this->periods = $periods;
        $this->defaultRow = $defaultRow;
    }
    
    /**
     * TODO
     */
    public function get($idSite, $period)
    {
        if (!isset($this->data[$idSite][$period])) {
            $this->data[$idSite][$period] = $this->makeNewDataRow(); // TODO: code redundancy w/ below
        }
        return $this->data[$idSite][$period];
    }
    
    /**
     * TODO
     */
    public function set($idSite, $period, $name, $value)
    {
        if (!isset($this->data[$idSite][$period])) {
            $this->data[$idSite][$period] = $this->makeNewDataRow();
        }
        $this->data[$idSite][$period][$name] = $value;
    }
    
    /**
     * TODO
     */
    public function addKey($idSite, $period, $keyName, $keyValue)
    {
        $this->set($idSite, $period, '_'.$keyName, $keyValue);
    }
    
    /**
     * TODO
     */
    public function getArray($resultIndices)
    {
        if (empty($resultIndices)) {
            if (empty($this->data)) {
                return false;
            }
            
            return $this->getFirstDataRow();
        }
        
        return $this->createIndex($resultIndices);
    }
    
    /**
     * TODO
     */
    public function getDataTable($resultIndices, $expanded = false, $addMetadataSubtableId = false)
    {
        $dataTableFactory = new Piwik_Archive_DataTableFactory(
            $this->dataNames, $this->dataType, $this->sites, $this->periods);
        
        if (empty($resultIndices)) {
            return $this->getNonIndexedDataTable($dataTableFactory);
        }
        
        $index = $this->createIndex($resultIndices);
        return $dataTableFactory->make($index, $resultIndices);
    }
    
    /**
     * TODO
     */
    private function getNonIndexedDataTable($dataTableFactory) // TODO: move private functions away
    {
        if (empty($this->data)) {
            if ($this->dataType == 'blob') {
                $result = new Piwik_DataTable();
            } else {
                $result = new Piwik_DataTable_Simple();
                $result->addRow(new Piwik_DataTable_Row(array(
                    Piwik_DataTable_Row::COLUMNS => $this->defaultRow
                )));
            }
        } else {
            if ($this->dataType == 'blob') {
                $result = $dataTableFactory->makeFromBlobRow($this->getFirstDataRow());
            } else {
                $result = new Piwik_DataTable_Simple();
                $result->addRow(new Piwik_DataTable_Row(array(
                    Piwik_DataTable_Row::COLUMNS => $this->getFirstDataRow()
                )));
            }
        }
        
        $result->setMetadata('site', reset($this->sites));
        
        reset($this->periods);
        $result->setMetadata('period', key($this->periods));
        
        return $result;
    }
    
    /**
     * TODO
     */
    private function getFirstDataRow()
    {
        $firstSite = reset($this->data);
        return reset($firstSite);
    }
    
    /**
     * TODO
     */
    private function createIndex($resultIndices) // TODO: can just make this getArray
    {
        $indexKeys = array_keys($resultIndices);
        
        $result = $this->initializeIndex($indexKeys);
        foreach ($this->data as $idSite => $rowsByPeriod) {
            foreach ($rowsByPeriod as $period => $row) {
                $indexRowKeys = $this->getRowKeys($indexKeys, $row, $idSite, $period);
                
                $this->setIndexRow($result, $indexRowKeys, $row);
            }
        }
        return $result;
    }
    
    /**
     * TODO
     */
    private function initializeIndex($indexKeys)
    {
        $result = array();
        
        if (!empty($indexKeys)) {
            $index = array_shift($indexKeys);
            if ($index == 'site') {
                foreach ($this->sites as $idSite) {
                    $result[$idSite] = $this->initializeIndex($indexKeys);
                }
            } else if ($index == 'period') {
                foreach ($this->periods as $period => $periodObject) {
                    $result[$period] = $this->initializeIndex($indexKeys);
                }
            }
        }
        
        return $result;
    }
    
    /**
     * TODO
     */
    private function setIndexRow(&$result, $keys, $row)
    {
        $firstKey = array_shift($keys);
        
        if (empty($keys)) {
            $result[$firstKey] = $row;
        } else {
            $this->setIndexRow($result[$firstKey], $keys, $row);
        }
    }
    
    /**
     * TODO
     */
    public function getExpandedDataTable($resultIndices, $idSubtable = null, $addMetadataSubtableId = false)
    {
        if ($this->dataType != 'blob') {
            throw new Exception("Piwik_Archive_DataCollection: cannot call getExpandedDataTable with "
                               . "{$this->dataType} data types. Only works with blob data.");
        }
        
        if (count($this->dataNames) !== 1) {
            throw new Exception("Piwik_Archive_DataCollection: cannot call getExpandedDataTable with "
                               . "more than one record.");
        }
        
        $dataTableFactory = new Piwik_Archive_DataTableFactory($this->dataNames, 'blob', $this->sites, $this->periods);
        $dataTableFactory->expandDataTable($addMetadataSubtableId);
        $dataTableFactory->useSubtable($idSubtable);
        
        if (empty($resultIndices)) {
            return $this->getNonIndexedDataTable($dataTableFactory);
        }
        
        $index = $this->createIndex($resultIndices);
        return $dataTableFactory->make($index, $resultIndices);
    }
    
    /**
     * TODO
     */
    private function getRowKeys($keyNames, $row, $idSite, $period)
    {
        $result = array();
        foreach ($keyNames as $name) {
            if ($name == 'site') {
                $result['site'] = $idSite;
            } else if ($name == 'period') {
                $result['period'] = $period;
            } else {
                $result[$name] = $row['_'.$name];
            }
        }
        return $result;
    }
    
    /**
     * TODO
     */
    private function makeNewDataRow()
    {
        $row = $this->defaultRow;
        return $row;
    }
}
