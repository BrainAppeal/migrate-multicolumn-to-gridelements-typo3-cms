<?php
/* * *************************************************************
 *  Copyright notice
 *
 *  (c) 2014 Brain Appeal <info@brain-appeal.com>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 * ************************************************************* */

// require_once(PATH_tslib . 'class.tslib_pibase.php');
/**
 * Migration utility for the 'brainmulticolumntogridelements' extension.
 *
 * @author  Brain Appeal <info@brain-appeal.com>
 * @package TYPO3
 * @subpackage tx_brainmulticolumntogridelements
 */
class Tx_Brainmulticolumntogridelements_Utility_MigrationUtility
{

    /**
     * The results to display to the user.
     *
     * @var array
     */
    protected $results = array('error' => '');

    /**
     * Debug flag
     *
     * @var boolean
     */
    private $debug = false;

    /**
     * Execute migration as configured
     *
     * @param array $mapping Array which maps Multicolumn configurations
     * to Gridelements layouts. Unmapped configurations are set
     * to Gridelement layout 1.
     * @return array Results
     */
    public function execMigration($mapping = array())
    {
        $this->results = array('error' => '');
        $this->copyParentIds();

        if (!empty($this->results['error'])) {
            return $this->returnResults();
        }

        $this->changeContainerType($mapping);
        if (!empty($this->results['error'])) {
            return $this->returnResults();
        }

        $this->transferNestedColumnPosition();
        if (!empty($this->results['error'])) {
            return $this->returnResults();
        }

        $this->countGridelementChildren();
        return $this->returnResults();
    }

    /**
     * Allow debug output
     *
     * @param mixed $var
     */
    private function debug($var)
    {
        if ($this->debug) {
            $debugFile = PATH_site . 'debug.log';
            file_put_contents($debugFile, print_r($var, true), FILE_APPEND);
            file_put_contents($debugFile, print_r(PHP_EOL, true), FILE_APPEND);
        }
    }

    /**
     * Show information about the system.
     * Is migration necessary? Possible?
     */
    private function showInfo()
    {
        $this->results = array('error' => '');
        $this->countMulticolumnElements();
        $this->findDifferentMCElementConfigs();
        $this->isGridelementsInstalled();
        return $this->returnResults();
    }

    /**
     * Return system information
     *
     * @param array $mcToGeMapping Into which Gridelements layouts should
     * the multicolumn layouts be turned?
     *
     * @return array
     */
    public function getData($mcToGeMapping = null)
    {
        $vars = $this->getSessionVars();

        if (empty($vars)) {
            $vars = array();
        }

        $results = $this->showInfo();

        $flatResults = array();

        $vars['ffConfig'] = array();

        foreach ($results as $varKey => $val) {

            // Remove array values from $results
            if (!is_array($val)) {
                $flatResults[$varKey] = $val;
            }
        }

        $mcConfKeys = array_keys($results['configsOnPages']);

		// Detect frequency of flexform occurrence. The user can then see on which pages
		// only one flexform configuration is used so he doesn't mix up different configurations
		// when looking in FE.
		$allPids = array();
		$it = new RecursiveIteratorIterator(new RecursiveArrayIterator($results['configsOnPages']));
        foreach($it as $v) {
            $allPids[] = $v;
        }
		$allPids;
		$pidFrequency = array();
		foreach($allPids as $pid) {
			++$pidFrequency[$pid];
		}

        foreach ($mcConfKeys as $mcConfKey) {

            $ffStr = '';
            $ffArr = $results['flexformValues'][$mcConfKey];
            foreach ($ffArr as $fKey => $ffValue) {
                $ffStr .= $fKey . ': ' . $ffValue . "\n";
            }
            $ffStr = trim($ffStr);
            if (empty($ffStr)) {
                $ffStr = false;
            }
            // Reset session variables, just in case
			foreach($results['configsOnPages'][$mcConfKey] as &$val) {
				if ($pidFrequency[$val] == 1) {
					$val = '<i><b>' . $val . '</b></i>';
				}
			}
            $vars['ffConfig'][$mcConfKey] = array(
                'pages' => implode(', ', $results['configsOnPages'][$mcConfKey]),
                'flexform' => trim($ffStr),
            );
        }


        $vars['results'] = $flatResults;
        if (!empty($mcToGeMapping)) {

            $availability = $this->testMappingPossible($mcToGeMapping);

            $standardLayoutNumber = !empty($mcToGeMapping['standardLayoutNumber']) ?
                (string) $mcToGeMapping['standardLayoutNumber'] : 1;

            $vars['mcToGeMapping'] = $mcToGeMapping;
            $vars['standardLayoutNumber'] = $standardLayoutNumber;
            $vars['availability'] = $availability;
            $this->setSessionVars($vars);
        }

        return $vars;
    }

    /**
     * Test if all Gridelements layouts the user tries to map to a
     * multicolumn layout are available.
     *
     * @param array $mcToGeMapping
     * @return array $availability.
     * Empty if no Gridelements are configured yet (or all are deleted).
     * Filled with values 'visible' or 'hidden' for every layout
     * according to its visibility, or with 'unavailable' if no layout
     * was found for that id.
     */
    private function testMappingPossible($mcToGeMapping)
    {
        $geIds = array(); // Grid element layout IDs
        foreach ($mcToGeMapping as $id) {
            if (!empty($id)) {
                $geIds[] = (string) $id;
            } else {
                $geIds[] = 1;
            }
        }
        $ids = array_unique($geIds, SORT_NUMERIC);

        $select_fields = 'uid, hidden';
        $where = 'uid IN(' . implode(',', $ids) . ') AND deleted = 0';
        $orderBy = '';
        $table = 'tx_gridelements_backend_layout';

        $availability = array();
        $resource = $this->execSelect($select_fields, $where, $orderBy, $table);
        if ($resource === false) {
            return $availability;
        }

        foreach ($ids as $id) {
            $availability[$id] = 'unavailable';
        }
        while ($row = $GLOBALS["TYPO3_DB"]->sql_fetch_assoc($resource)) {
            $hidden = (int) $row['hidden'];
            $availability[$row['uid']] = $hidden ? 'hidden' : 'visible';
        }
        $GLOBALS["TYPO3_DB"]->sql_free_result($resource);
        return $availability;
    }

    /**
     * Transform Multicolumn container to Gridelement container.
     *
     * @param array $mapping Array which maps Multicolumn configurations
     * to Gridelements layouts. Unmapped configurations are set
     * to Gridelement layout 1.
     */
    protected function changeContainerType($mapping = array())
    {
        $defaults = array(
            'CType' => "gridelements_pi1",
            'tx_gridelements_backend_layout' => 1,
        );
        $where = "CType = 'multicolumn'";

        if (empty($mapping)) {
            $this->execUpdate($where, $defaults);
        } else {
            $meVars = $this->getMultiColumnElementsGroupedByConfigurationType();
            $groupedEltIds = $meVars['groupedTtContentIds'];
            $keys = array_keys($mapping);
            $i = 0;
            foreach ($groupedEltIds as $idGroup) {
                $layout = $defaults['tx_gridelements_backend_layout'];
				if ((in_array($i, $keys) && !empty($mapping[$i]))) {
                    $layout = (string) $mapping[$i];
				}
                $fieldValues = array(
                    'CType' => "gridelements_pi1",
                    'tx_gridelements_backend_layout' => $layout,
                );
                $andWhere = $where . " AND uid IN ('"
                    . implode('\', \'', $idGroup) . "')";
                $this->execUpdate($andWhere, $fieldValues);
                ++$i;
            }
        }
        $this->results['changeContainerType'] = true;
    }

    /**
     * The Multicolumn containers are to be turned into Gridelement containers.
     * Nested content elements store their parent container's ID.
     * Copy the multicolumn parent id to the gridelement parent id.
     */
    protected function copyParentIds()
    {
        $select_fields = 'tx_multicolumn_parentid';
        $where = 'tx_multicolumn_parentid > 0';
        $resource = $this->execSelect($select_fields, $where);
        if ($resource === false) {
            $this->results['error'] = 'error_no_mc_containers';
            return false;
        }

        while ($row = $GLOBALS["TYPO3_DB"]->sql_fetch_assoc($resource)) {
            $fields_values = array(
                'tx_gridelements_container' => $row['tx_multicolumn_parentid'],
            );
            $where = 'tx_multicolumn_parentid = '
                . $row['tx_multicolumn_parentid'];
            $this->execUpdate($where, $fields_values);
        }
        $GLOBALS["TYPO3_DB"]->sql_free_result($resource);

        $this->results['copyParentIds'] = true;
    }

    /**
     * Count number of children and store it in the grid element containers.
     *
     * @return boolean Success
     */
    protected function countGridelementChildren()
    {
        $select_fields = 'uid, CType';
        $where = 'CType LIKE "%gridelements_pi1%"';
        $resource = $this->execSelect($select_fields, $where);
        if ($resource === false) {
            $this->results['error'] = 'error_no_ge_containers';
            return false;
        }

        // Go through all containers
        while ($row = $GLOBALS["TYPO3_DB"]->sql_fetch_assoc($resource)) {

            // Find children
            $select_fields = 'tx_gridelements_container';
            $where = 'tx_gridelements_container = ' . $row['uid'];
            $res = $this->execSelect($select_fields, $where);

            $i = 0;
            if ($res !== false) {
                while ($GLOBALS["TYPO3_DB"]->sql_fetch_assoc($res)) {
                    ++$i;
                }
                $where = 'uid = ' . $row['uid'];
                $fields_values = array('tx_gridelements_children' => $i);
                $this->execUpdate($where, $fields_values);
                $GLOBALS["TYPO3_DB"]->sql_free_result($res);
            }
        }
        $GLOBALS["TYPO3_DB"]->sql_free_result($resource);
        $this->results['countGridelementChildren'] = true;
        return true;
    }

    /**
     * Find multicolumn elements, if any.
     * Deleted elements should be irrelevant and are not counted.
     *
     * @return int Number of elements
     */
    protected function countMulticolumnElements()
    {
        $select_fields = 'uid, CType';
        $where = 'CType LIKE "%multicolumn%" AND deleted = 0';
        $resource = $this->execSelect($select_fields, $where);
        if ($resource === false) {
            $this->results['countMulticolumnElements'] = 0;
            return 0;
        }

        $i = 0;
        while ($row = $GLOBALS["TYPO3_DB"]->sql_fetch_assoc($resource)) {
            ++$i;
        }
        $GLOBALS["TYPO3_DB"]->sql_free_result($resource);
        $this->results['countMulticolumnElements'] = $i;
        return $i;
    }

    /**
     * Execute a select query on tt_content
     *
     * @param array $select_fields
     * @param string $where
     * @param string $orderBy
     * @param string $table
     * @return boolean Success
     */
    protected function execSelect($select_fields, $where, $orderBy = '', $table = 'tt_content')
    {
        $groupBy = '';
        if (empty($orderBy)) {
            $orderBy = $select_fields;
        }
        $limit = '1000000';
        $resource = $GLOBALS["TYPO3_DB"]->exec_SELECTquery($select_fields, $table, $where, $groupBy, $orderBy, $limit);
        return $resource;
    }

    /**
     * Execute an update query on tt_content
     * @param string $where
     * @param array $fields_values
     */
    protected function execUpdate($where, $fields_values)
    {
        $table = 'tt_content';
        $no_quote_fields = FALSE;
        $GLOBALS["TYPO3_DB"]->exec_UPDATEquery($table, $where, $fields_values, $no_quote_fields);
    }

    /**
     * Multicolumn configuration is done on a per-element-basis,
     * the values are found in the field 'pi_flexform'.
     * That does NOT mean that these elements must look completely
     * different or have different numbers or orders of columns.
     *
     * @return int Number of different configs.
     */
    protected function findDifferentMCElementConfigs()
    {
        $meVars = $this->getMultiColumnElementsGroupedByConfigurationType();
        $pageCount = count(array_keys($meVars['configsOnPages']));
        $this->results = array_merge($this->results, $meVars);
        $this->results['countConfigsOnPages'] = $pageCount;
        return $this->results['countConfigsOnPages'];
    }

    private function getMultiColumnElementsGroupedByConfigurationType()
    {
        $configsOnPages = array();
        $select_fields = 'uid, pi_flexform, pid, CType';
        $where = 'CType LIKE "%multicolumn%" AND deleted = 0';
        $resource = $this->execSelect($select_fields, $where);
        if ($resource === false) {
            $this->results['findDifferentMCElementConfigs'] = $configsOnPages;
            return 0;
        }

        $ffValues = array();
        // Go through all configuration kinds
        $groupedTtContentIds = array();
        while ($row = $GLOBALS["TYPO3_DB"]->sql_fetch_assoc($resource)) {
            $xmlData = $row['pi_flexform'];
            $ffArray = $this->getFlexFormValues($xmlData);

            $key = md5(trim(serialize($ffArray)));

            $groupedTtContentIds[$key][] = $row['uid'];
            $configsOnPages[$key][$row['pid']] = $row['pid'];
            $ffValues[$key] = $ffArray;
        }
        $GLOBALS["TYPO3_DB"]->sql_free_result($resource);

        foreach ($configsOnPages as &$pidArr) {
            sort($pidArr, SORT_NUMERIC);
        }

        $meVars = array(
            'groupedTtContentIds' => $groupedTtContentIds,
            'configsOnPages' => $configsOnPages,
            'flexformValues' => $ffValues,
        );

        return $meVars;
    }

    private function getFlexFormValues($xmlData)
    {
        $ffArray = array();
        if (class_exists('t3lib_div')) {
            $ffArray = t3lib_div::xml2array($xmlData);
        } else {
            $ffArray = \TYPO3\CMS\Core\Utility\GeneralUtility::xml2array($xmlData);
        }
        $ffArray = $this->cleanAndFlattenFlexFormArray($ffArray);
        return $ffArray;
    }

    private function cleanAndFlattenFlexFormArray($ffArray)
    {
        $ffData = $ffArray['data'];
        // Remove these flexform variables if the value is 0
        $removeKeyIfZeroList = array(
            'advancedLayout.disableImageShrink',
            'advancedLayout.disableStyles',
            'advancedLayout.makeEqualElementBoxHeight',
            'advancedLayout.makeEqualElementColumnHeight',
        );
        $cArr = array();
        foreach ($ffData as $groupKey => $groupData) {
            $groupVals = $groupData['lDEF'];
            foreach ($groupVals as $varKey => $varData) {
                $value = $varData['vDEF'];
                $groupVarKey = $groupKey.'.'.$varKey;
                if (strlen($value) > 0 && ($value != '0'
                    || !in_array($groupVarKey, $removeKeyIfZeroList))) {
                    $cArr[$groupVarKey] = $value;
                }
            }
        }
        ksort($cArr);
        return $cArr;
    }


    /**
     * Search each layout for its configured colPos values
     *
     * @return array Key: layout uid. Value: Available colPos values.
     */
    protected function getLayoutColPosVals()
    {
        $select_fields = 'uid, config';
        $where = 'deleted = 0';
        $orderBy = 'uid';
        $table = 'tx_gridelements_backend_layout';
        $resource = $this->execSelect($select_fields, $where, $orderBy, $table);
        if ($resource === false) {
            $this->results['error'] = 'error_no_ge_config';
            return 0;
        }

        $layoutColPosVals = array();
        while ($row = $GLOBALS["TYPO3_DB"]->sql_fetch_assoc($resource)) {
            $pattern = '/colPos\s*=\s*\d+/';
            $matches = array();
            preg_match_all($pattern, $row['config'], $matches);

            $colPosVals = preg_replace('/colPos\s*=\s*/', '', $matches[0]);
            $layoutColPosVals[$row['uid']] = $colPosVals;
        }
        return $layoutColPosVals;
    }

    /**
     * Check if gridelements is installed
     *
     * @return boolean
     */
    protected function isGridelementsInstalled()
    {
        $geLoaded = t3lib_extMgm::isLoaded('gridelements');
        $this->results['isGridelementsInstalled'] = $geLoaded;
        return $geLoaded;
    }

    /**
     * Clean up the results and return them
     *
     * @return array
     */
    protected function returnResults()
    {
        $true = Tx_Extbase_Utility_Localization::translate(
                'TRUE', 'Brainmulticolumntogridelements');
        $false = Tx_Extbase_Utility_Localization::translate(
                'FALSE', 'Brainmulticolumntogridelements');

        if (empty($this->results['error'])) {
            unset($this->results['error']);
        }
        foreach ($this->results as &$val) {
            if (is_bool($val)) {
                $val = $val ? $true : $false;
            }
        }

        return $this->results;
    }

    /**
     * Change the colPos values of nested elements from the
     * Multicolumn standard to the Gridelements standard.
     * This means that the actual colPos value will become negative
     * while the position inside the Gridelements container will be stored
     * in tx_gridelements_columns.
     *
     * @return boolean Success
     */
    protected function transferNestedColumnPosition()
    {
        $select_fields = 'tx_multicolumn_parentid, colPos';
        $where = 'tx_multicolumn_parentid > 0 AND deleted = 0';
        $resource = $this->execSelect($select_fields, $where);
        if ($resource === false) {
            $this->results['error'] = 'error_no_mc_contents';
            return false;
        }

        // Find colPos values used for elements in multicolum containers
        $mcColPosValsPerParent = array();
        while ($row = $GLOBALS["TYPO3_DB"]->sql_fetch_assoc($resource)) {
            $mcpid = $row['tx_multicolumn_parentid'];
            if (!isset($mcColPosValsPerParent[$mcpid])) {
                $mcColPosValsPerParent[$mcpid] = array(
                    'colPosVals' => array(),
                    'geLayout' => '',
                );
            }
            $mcColPosValsPerParent[$mcpid]['colPosVals'][] = $row['colPos'];
        }
        $GLOBALS["TYPO3_DB"]->sql_free_result($resource);

        // Update colPos values
        foreach ($mcColPosValsPerParent as $mcpid => &$array) {
            $array['colPosVals'] = array_unique($array['colPosVals']);
            $select_fields = 'uid, tx_gridelements_backend_layout';
            $where = 'uid = ' . $mcpid;
            $resource = $this->execSelect($select_fields, $where);
            if ($resource === false) {
                continue; // Parent container doesn't exist anymore.
            }

            while ($row = $GLOBALS["TYPO3_DB"]->sql_fetch_assoc($resource)) {
                $array['geLayout'] = $row['tx_gridelements_backend_layout'];
            }
            $GLOBALS["TYPO3_DB"]->sql_free_result($resource);
        }

        $layoutColPosVals = $this->getLayoutColPosVals();
        foreach ($mcColPosValsPerParent as $mcpid => $array) {
            $i = 0;
            foreach ($array['colPosVals'] as $colPos) {
                $where = 'tx_multicolumn_parentid = ' . $mcpid
                    . ' AND colPos = ' . $colPos;
                $geColumn = $i + 100;
                $geColPos = -2; // Column NOT available in GE layout
                if (isset($layoutColPosVals[$array['geLayout']][$i])) {
                    $geColumn = $layoutColPosVals[$array['geLayout']][$i];
                    $geColPos = -1; // Column available in GE layout
                }
                $fields_values = array(
                    'colPos' => $geColPos,
                    'backupColPos' => $colPos,
                    'tx_gridelements_columns' => $geColumn,
                );
                $this->execUpdate($where, $fields_values);
                ++$i;
            }
        }
        $this->results['transferNestedColumnPosition'] = true;
        return true;
    }

    /**
     * Get the session variables for this extension
     *
     * @return mixed
     */
    protected function getSessionVars()
    {
        return $GLOBALS["BE_USER"]->getSessionData($this->extensionName);
    }

    /**
     * Set the session variables for this extension
     *
     * @param mixed $vars (Usually an array)
     */
    protected function setSessionVars($vars)
    {
        $GLOBALS["BE_USER"]->setAndSaveSessionData($this->extensionName, $vars);
    }
}
?>
