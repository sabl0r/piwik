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
class Piwik_Archive_DataTableFactory
{
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
    private $expandDataTable = false;
    
    /**
     * TODO
     */
    private $addMetadataSubtableId = false;
    
    /**
     * TODO
     */
    private $sites;
    
    /**
     * TODO
     */
    private $periods;
    
    /**
     * TODO
     */
    private $idSubtable = null;
    
    /**
     * TODO
     */
    private $defaultRow;
    
    /**
     * TODO
     */
    public function __construct($dataNames, $dataType, $sites, $periods, $defaultRow)
    {
        $this->dataNames = $dataNames;
        $this->dataType = $dataType;
        $this->sites = $sites;
        $this->periods = $periods;
        $this->defaultRow = $defaultRow;
    }
    
    /**
     * TODO
     */
    public function expandDataTable($addMetadataSubtableId = false)
    {
        $this->expandDataTable = true;
        $this->addMetadataSubtableId = $addMetadataSubtableId;
    }
    
    /**
     * TODO
     */
    public function useSubtable($idSubtable)
    {
        $this->idSubtable = $idSubtable;
    }
    
    /**
     * TODO
     */
    public function make($index, $resultIndices)
    {
        if (empty($resultIndices)) {
            if (empty($index)
                && $this->dataType == 'numeric'
            ) {
                $index = $this->defaultRow;
            }
            
            $dataTable = $this->createDataTable($index, $keyMetadata = array());
        } else {
            $dataTable = $this->createDataTableArrayFromIndex($index, $resultIndices);
        }
        
        $this->transformMetadata($dataTable);
        return $dataTable;
    }

    /**
     * TODO
     */
    public function makeFromBlobRow($blobRow)
    {
        if ($blobRow === false) {
            return new Piwik_DataTable();
        }
        
        if (count($this->dataNames) === 1) { // only one record
            $recordName = reset($this->dataNames);
            if ($this->idSubtable !== null) {
                $recordName .= '_' . $this->idSubtable;
            }
            
            if (!empty($blobRow[$recordName])) {
                $table = Piwik_DataTable::fromBlob($blobRow[$recordName]);
            } else {
                $table = new Piwik_DataTable();
            }
            
            // set metadata
            foreach ($blobRow as $name => $value) {
                if (substr($name, 0, 1) == '_') {
                    $table->setMetadata(substr($name, 1), $value);
                }
            }
            
            if ($this->expandDataTable) {
                $table->enableRecursiveFilters();
                $this->setSubtables($table, $blobRow);
            }
            
            return $table;
        } else { // multiple records, index by name
            $table = new Piwik_DataTable_Array();
            $table->setKeyName('recordName');
            
            foreach ($blobRow as $name => $blob) {
                $newTable = Piwik_DataTable::fromBlob($blob);
                $table->addTable($newTable, $name);
            }
            
            return $table;
        }
    }
    
    /**
     * TODO
     */
    private function createDataTableArrayFromIndex($index, $resultIndices, $keyMetadata = array())
    {
        $resultIndexLabel = reset($resultIndices);
        $resultIndex = key($resultIndices);
        
        array_shift($resultIndices);
        
        $result = new Piwik_DataTable_Array();
        $result->setKeyName($resultIndexLabel);
        
        foreach ($index as $label => $value) {
            $keyMetadata[$resultIndex] = $label;
            
            if (empty($resultIndices)) {
                $newTable = $this->createDataTable($value, $keyMetadata);
            } else {
                $newTable = $this->createDataTableArrayFromIndex($value, $resultIndices, $keyMetadata);
            }
            
            $result->addTable($newTable, $this->prettifyIndexLabel($resultIndex, $label));
        }
        
        return $result;
    }
    
    /**
     * TODO
     */
    private function createDataTable($data, $keyMetadata)
    {
        if ($this->dataType == 'blob') {
            $result = $this->makeFromBlobRow($data);
        } else {
            $table = new Piwik_DataTable_Simple();
            
            if (!empty($data)) {
                $row = new Piwik_DataTable_Row();
                foreach ($data as $name => $value) {
                    if (substr($name, 0, 1) == '_') {
                        $table->setMetadata(substr($name, 1), $value);
                    } else {
                        $row->setColumn($name, $value);
                    }
                }
                $table->addRow($row);
            }
            
            $result = $table;
        }
        
        if (!isset($keyMetadata['site'])) {
            $keyMetadata['site'] = reset($this->sites);
        }
        
        if (!isset($keyMetadata['period'])) {
            reset($this->periods);
            $keyMetadata['period'] = key($this->periods);
        }
        
        foreach ($keyMetadata as $name => $value) {
            $result->setMetadata($name, $value);
        }
        
        return $result;
    }
    
    /**
     * TODO
     */
    private function setSubtables($dataTable, $blobRow)
    {
        $dataName = reset($this->dataNames);
        
        foreach ($dataTable->getRows() as $row) {
            $sid = $row->getIdSubDataTable();
            if ($sid === null) {
                continue;
            }
            
            $blobName = $dataName."_".$sid;
            if (isset($blobRow[$blobName])) {
                $subtable = Piwik_DataTable::fromBlob($blobRow[$blobName]);
                $this->setSubtables($subtable, $blobRow);
                
                // we edit the subtable ID so that it matches the newly table created in memory
                // NB: we dont overwrite the datatableid in the case we are displaying the table expanded.
                if ($this->addMetadataSubtableId) {
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
    private function transformMetadata($table)
    {
        $periods = $this->periods;
        $table->filter(function ($table) use($periods) {
            $table->metadata['site'] = new Piwik_Site($table->metadata['site']);
            $table->metadata['period'] = $periods[$table->metadata['period']];
        });
    }
    
    /**
     * TODO
     */
    private function prettifyIndexLabel($labelType, $label)
    {
        if ($labelType == 'period') { // prettify period labels
            return $this->periods[$label]->getPrettyString();
        }
        return $label;
    }
}
