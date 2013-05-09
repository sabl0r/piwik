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
    private $keySets = null;
    
    /**
     * TODO
     */
    public function __construct($dataNames, $dataType, $defaultRow = null, $keySets = null)
    {
        $this->dataNames = $dataNames;
        $this->dataType = $dataType;
        
        if ($defaultRow === null) {
            $defaultRow = array_fill_keys(array_keys($dataNames), 0);
        }
        $this->defaultRow = $defaultRow;
        $this->keySets = $keySets;
    }
    
    /**
     * TODO
     */
    public function get($idSite, $period)
    {
        if (!isset($this->data[$idSite][$period])) {
            $this->data[$idSite][$period] = $this->makeNewDataRow($idSite, $period);
        }
        return $this->data[$idSite][$period];
    }
    
    /**
     * TODO
     */
    public function addKey($keyName, $keyValue, &$row)
    {
        $row['_'.$keyName] = $keyValue;
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
        $result = array();
        foreach ($this->data as $idSite => $rowsByPeriod) {
            foreach ($rowsByPeriod as $period => $row) {
                $indexKeys = $this->getRowKeys(array_keys($resultIndices), $indexKeys);
                
                $this->setIndexRow($result, $indexKeys, $row);
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
        $dataTable = $this->convertToDataTable($resultIndices, $index, array($nameWithSubtable), $expanded = true,
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
    private function createDataTable($dataIndex, $archiveNames, $expanded, $addMetadataSubtableId)
    {
        if (!is_int($dataIndex)) {
            throw new Exception("Piwik_Archive_DataCollection: creating datatable w/ the wrong number of indices.");
        }
        
        $data = $this->data[$dataIndex];
        
        if ($this->dataType == 'blob') {
            if (count($archiveNames) === 1) { // only one record
                $recordName = reset($archiveNames);
                $table = Piwik_DataTable::fromBlob($data[$recordName]);
                
                if ($expanded) {
                    $this->fillSubtables($recordName, $table, $data, $addMetadataSubtableId);
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
            $table = new Piwik_DataTable();
            
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
        foreach ($table->getRows() as $row) {
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
