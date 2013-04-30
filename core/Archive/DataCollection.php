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
    public function __construct($dataNames, $dataType, $defaultRow = null)
    {
        $this->dataNames = $dataNames;
        $this->dataType = $dataType;
        
        if ($defaultRow === null) {
            $defaultRow = array_fill_keys(array_keys($dataNames), 0);
        }
        $this->defaultRow = $defaultRow;
    }
    
    /**
     * TODO
     */
    public function get($keys)
    {
        $index = $this->getIndex(array_keys($keys));
        $rowIndex = $this->getRowFromIndex($index, $keys, null);
        return $this->data[$rowIndex];
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
        $index = $this->getIndex($resultIndices);
        
        $resultIndices = array_flip($resultIndices);
        
        $result = $index; // copy array
        return $this->copyIndexAndSetRows($result);
    }
    
    /**
     * TODO
     */
    public function getDataTable($resultIndices)
    {
        if (count($this->dataNames) > 1) {
            $resultIndices['name'] = 'recordName';
        }
        
        $resultIndices = array_flip($resultIndices);
        
        $index = $this->getIndex($resultIndices);
        return $this->convertToDataTable($resultIndices, $index, $this->dataNames);
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
        $dataTable = $this->convertToDataTable($resultIndices, $index, array($nameWithSubtable));
        $this->fillSubtables(reset($this->dataNames), $dataTable); // TODO can't do it here...
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
        
        foreach ($this->data as $rowIndex => $row) {
            $rowKeys = $this->getRowKeys($keyNames, $row);
            $this->getRowFromIndex($this->indices[$indexName], $rowKeys, $rowIndex);
        }
        
        return $this->indices[$indexName];
    }
    
    /**
     * TODO
     */
    private function &copyIndexAndSetRows(&$index)
    {
        if (is_int($index)) {
            return &$this->data[$index];
        }
        
        foreach ($index as $name => &$child) {
            $index[$name] = $this->copyIndexAndSetRows($child);
        }
        return $index;
    }
    
    /**
     * TODO
     */
    private function convertToDataTable($resultIndices, $index, $archiveNames)
    {
        if (empty($resultIndices)) {
            return $this->createDataTable($index, $archiveNames);
        } else {
            $resultIndex = reset($resultIndices);
            $resultIndexLabel = key($resultIndices);
            
            array_shift($resultIndices);
            
            return $this->createDataTableArrayFromIndex($resultIndexLabel, $resultIndices, $index, $archiveNames);
        }
    }// TODO: NEED TO ADD EMPTY ROWS!!! (use $keySets parameter and supply in Archive.php)
    // TODO: Need to transform metadata as well.
    // TODO: need timestamp metadata.
    
    /**
     * TODO
     */
    private function createDataTable($dataIndex, $archiveNames)
    {
        $data = $this->data[$dataIndex];
        
        if ($this->dataType == 'blob') {
            if (is_string($data)) {
                $blobData = $data;
            } else {
                $blobData = $data[reset($archiveNames)];
            }
            
            $table = new Piwik_DataTable();
            $table->addRowsFromSerializedArray($blobData);
            return $table;
        } else {
            $row = new Piwik_DataTable_Row();
            foreach ($data as $name => $value) {
                if (substr($name, 0, 1) == '_') {
                    $row->setMetadata(substr($name, 1), $value); // TODO: FAILURE!!!
                } else {
                    $row->setColumn($name, $value);
                }
            }
            
            $table = new Piwik_DataTable();
            $table->addRow($row);
            return $table;
        }
    }
    
    /**
     * TODO
     */
    private function createDataTableArrayFromIndex($keyName, $resultIndices, $index, $archiveNames)
    {
        $result = new Piwik_DataTable_Array();
        $result->setKeyName($keyName);
        
        foreach ($index as $label => $value) {
            $newTable = $this->convertToDataTable($resultIndices, $value, $archiveNames);
            $result->addTable($newTable, $label);
        }
        
        return $result;
    }
    
    /**
     * TODO
     */
    private function fillSubtables($dataName, $dataTable, $blobRow) // TODO
    {
        foreach ($table->getRows() as $row) {
            $sid = $row->getIdSubDataTable();
            if ($sid === null) {
                continue;
            }
            
            $blobName = $dataName."_".$sid;
            if (isset($blobCache[$idSite][$dateRange][$blobName])) {
                $blob = $blobCache[$idSite][$dateRange][$blobName];
            
                $subtable = new Piwik_DataTable();
                $subtable->addRowsFromSerializedArray($blob);
                $this->setSubTables($subtable, $name, $idSite, $dateRange, $blobCache, $addMetadataSubtableId);
                
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
        // TODO
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
        return key($this->data);
    }
}
