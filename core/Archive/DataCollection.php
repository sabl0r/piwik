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

class Piwik_Archive_DataCollection
{
    /**
     * TODO
     */
    private $data = array();
    
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
    private $sites = null; // TODO: should not be optional. (same w/ below)
    
    /**
     * TODO
     */
    private $periods = null;
    
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
            $this->data[$idSite][$period] = $this->makeNewDataRow($idSite, $period); // TODO: code redundancy w/ below
        }
        return $this->data[$idSite][$period];
    }
    
    /**
     * TODO
     */
    public function set($idSite, $period, $name, $value)
    {
        if (!isset($this->data[$idSite][$period])) {
            $this->data[$idSite][$period] = $this->makeNewDataRow($idSite, $period);
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
        return $this->createIndex($resultIndices);
    }
    
    /**
     * TODO
     */
    public function getDataTable($resultIndices)
    {
        $index = $this->createIndex($resultIndices);
        return $this->convertToDataTable($resultIndices, $index, $this->dataNames);
    }
    
    /**
     * TODO
     */
    private function createIndex($resultIndices)
    {
        if (empty($resultIndices)) {
            if (empty($this->data)) {
                return false;
            }
            
            $firstSite = reset($this->data);
            return reset($firstSite);
        }
        
        $result = $this->initializeIndex($resultIndices);
        foreach ($this->data as $idSite => $rowsByPeriod) {
            foreach ($rowsByPeriod as $period => $row) {
                $indexKeys = $this->getRowKeys(array_keys($resultIndices), $row);
                
                $this->setIndexRow($result, $indexKeys, $row);
            }
        }
        return $result;
    }
    
    /**
     * TODO
     */
    private function initializeIndex($resultIndices, $keys = array())
    {
        $result = array();
        
        if (!empty($resultIndices)) {
            $index = array_shift($resultIndices);
            if ($index == 'site') {
                foreach ($this->sites as $idSite) {
                    $keys['site'] = $idSite;
                    $result[$idSite] = $this->initializeIndex($resultIndices, $keys);
                }
            } else if ($index == 'period') {
                foreach ($this->periods as $period) {
                    $keys['period'] = $period;
                    $result[$period] = $this->initializeIndex($resultIndices, $keys);
                }
            }
        } else {
            foreach ($keys as $name => $value) {
                $result['_'.$name] = $value;
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
        
        $dataName = reset($this->dataNames);
        if ($idSubtable !== null) {
            $dataName .= '_' . $idSubtable;
        }
        
        $index = $this->createIndex($resultIndices);
        $dataTable = $this->convertToDataTable($resultIndices, $index, array($dataName), $expanded = true,
                                               $addMetadataSubtableId);
        return $dataTable;
    }
    
    /**
     * TODO
     */
    private function convertToDataTable($resultIndices, $index, $archiveNames, $expanded = false, 
                                          $addMetadataSubtableId = false)
    {
        if (empty($resultIndices)) {
            return $this->createDataTable($index, $archiveNames, $expanded, $addMetadataSubtableId);
        } else {
            $resultIndexLabel = reset($resultIndices);
            $resultIndex = key($resultIndices);
            
            array_shift($resultIndices);
            
            return $this->createDataTableArrayFromIndex(
                $resultIndexLabel, $resultIndices, $index, $archiveNames, $expanded, $addMetadataSubtableId);
        }
    }
    
    /**
     * TODO
     */
    private function createDataTable($data, $archiveNames, $expanded, $addMetadataSubtableId)
    {
        if ($this->dataType == 'blob') {
            if (count($archiveNames) === 1) { // only one record
                $recordName = reset($archiveNames);
                if (isset($data[$recordName])) {
                    $table = Piwik_DataTable::fromBlob($data[$recordName]);
                } else {
                    $table = new Piwik_DataTable();
                }
                
                // set metadata
                foreach ($data as $name => $value) {
                    if (substr($name, 0, 1) == '_') {
                        $table->setMetadata(substr($name, 1), $value);
                    }
                }
                
                if ($expanded) {
                    $this->setSubtables($recordName, $table, $data, $addMetadataSubtableId);
                }
                
                return $table;
            } else { // multiple records, index by name
                $table = new Piwik_DataTable_Array();
                $table->setKeyName('recordName');
                
                foreach ($data as $name => $blob) {
                    $newTable = Piwik_DataTable::fromBlob($blob);
                    $table->addTable($newTable, $name);
                }
                
                return $table;
            }
        } else {
            $table = new Piwik_DataTable_Simple();
            
            if ($data === false) {
                $data = $this->defaultRow;
            }
            
            $row = new Piwik_DataTable_Row();
            foreach ($data as $name => $value) {
                if (substr($name, 0, 1) == '_') {
                    $table->setMetadata(substr($name, 1), $value);
                } else {
                    $row->setColumn($name, $value);
                }
            }
            $table->addRow($row);
            
            return $table;
        }
    }
    
    /**
     * TODO
     */
    private function createDataTableArrayFromIndex($keyName, $resultIndices, $index, $archiveNames,
                                                     $addMetadataSubtableId)
    {
        $result = new Piwik_DataTable_Array();
        $result->setKeyName($keyName);
        
        foreach ($index as $label => $value) {
            $newTable = $this->convertToDataTable($resultIndices, $value, $archiveNames, $addMetadataSubtableId);
            $result->addTable($newTable, $label);
        }
        
        return $result;
    }
    
    /**
     * TODO
     */
    private function setSubtables($dataName, $dataTable, $blobRow, $addMetadataSubtableId)
    {
        foreach ($dataTable->getRows() as $row) {
            $sid = $row->getIdSubDataTable();
            if ($sid === null) {
                continue;
            }
            
            $blobName = $dataName."_".$sid;
            if (isset($blobRow[$blobName])) {
                $subtable = Piwik_DataTable::fromBlob($blobRow[$blobName]);
                $this->setSubtables($dataName, $subtable, $blobRow, $addMetadataSubtableId);
                
                // we edit the subtable ID so that it matches the newly table created in memory
                // NB: we dont overwrite the datatableid in the case we are displaying the table expanded.
                if ($addMetadataSubtableId) {
                    // this will be written back to the column 'idsubdatatable' just before rendering,
                    // see Renderer/Php.php
                    $row->addMetadata('idsubdatatable_in_db', $row->getIdSubDataTable());
                }
                
                $row->setSubtable($subtable);
            }
        }
    }
    
    /**
     * TODO
     */
    private function getRowKeys($keyNames, $row)
    {
        $result = array();
        foreach ($keyNames as $name) {
            $result[$name] = $row['_'.$name];
        }
        return $result;
    }
    
    /**
     * TODO
     */
    private function makeNewDataRow($idSite, $period)
    {
        $row = $this->defaultRow;
        $row['_site'] = $idSite;
        $row['_period'] = $period;
        return $row;
    }
}
