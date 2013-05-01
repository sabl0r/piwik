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
    private $indices = array();
    
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
    public function get($keys)
    {
        $index = $this->getIndex(array_keys($keys));
        return $this->getRowFromIndex($index, $keys, null);
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
        $resultIndices = array_flip($resultIndices);
        $index = $this->getIndex($resultIndices);
        
        $result = $index; // copy array
        return $this->copyIndexAndSetRows($result);
    }
    
    /**
     * TODO
     */
    public function getDataTable($resultIndices)
    {
        $resultIndices = array_flip($resultIndices);
        $index = $this->getIndex($resultIndices);
        return $this->convertToDataTable($resultIndices, $asArray, $this->dataNames);
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
        
        $resultIndices = array_flip($resultIndices);
        
        $index = $this->getIndex($resultIndices);
        $dataTable = $this->convertToDataTable($resultIndices, $index, array($nameWithSubtable), $expanded = true,
                                               $addMetadataSubtableId);
        return $dataTable;
    }
    
    /**
     * TODO
     */
    private function getIndex($keyNames)
    {
        $indexName = $this->getIndexNameFromKeys($keyNames);
        
        if (!isset($this->indices[$indexName])) {
            $this->indices[$indexName] = array();
        }
        
        if ($this->keySets !== null) {
            $this->initializeIndex($this->indices[$indexName], $keyNames, $this->keySets);
        }
        
        foreach ($this->data as $rowIndex => $row) {
            $rowKeys = $this->getRowKeys($keyNames, $row);
            $this->getRowFromIndex($this->indices[$indexName], $rowKeys, $rowIndex);
        }
        
        return $this->indices[$indexName];
    }
    
    /**
     * TODO
     */
    private function initializeIndex(&$index, $keyNames, $keySets)
    {
        $keyName = array_shift($keyNames);
        
        foreach ($keySets[$keyName] as $key) {
            if (!empty($keyNames)) {
                $index[$key] = array();
                $this->initializeIndex($index[$key], $keyNames, $keySets);
            } else {
                $index[$key] = null;
            }
        }
    }
    
    /**
     * TODO
     */
    private function &copyIndexAndSetRows(&$index)
    {
        if (is_int($index)) {
            return $this->data[$index];
        }
        
        foreach ($index as $name => &$child) {
            $index[$name] = $this->copyIndexAndSetRows($child);
        }
        return $index;
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
            $resultIndex = reset($resultIndices);
            $resultIndexLabel = key($resultIndices);
            
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
    private function getRowFromIndex(&$index, $keys, $defaultRowIndex)
    {
        end($keys);
        $lastKeyName = key($keys);
        
        $result = &$index;
        foreach ($keys as $name => $value) {
            if (!isset($result[$value])) {
                // on the last key, we want to create a new data row, not a new index row
                if ($name == $lastKeyName) {
                    if ($defaultRowIndex === null) {
                        $result[$value] = $this->makeNewDataRow($keys);
                    } else {
                        $result[$value] = $defaultRowIndex;
                    }
                } else {
                    $result[$value] = array();
                }
            }
            
            $result = &$result[$value];
        }
        
        // $result is now an int index into $this->data
        return $this->data[$result];
    }
    
    /**
     * TODO
     */
    private function getIndexNameFromKeys($keys)
    {
        return implode(',', array_keys($keys));
    }
    
    /**
     * TODO
     */
    private function makeNewDataRow($keys)
    {
        $this->data[] = $this->defaultRow;
        
        end($this->data);
        $rowId = key($this->data);
        
        foreach ($keys as $name => $value) {
            $this->data[$rowId]['_'.$name] = $value;
        }
        
        return $rowId;
    }
}
