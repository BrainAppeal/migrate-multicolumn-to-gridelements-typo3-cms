<?php
/* * *************************************************************
 *  Copyright notice
 *
 *  (c) 2014 Brain Appeal GmbH <info@brain-appeal.com>
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
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
/**
 *
 *
 * @package brainmulticolumntogridelements
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 *
 */
class Tx_Brainmulticolumntogridelements_Controller_MigrationController extends Tx_Extbase_MVC_Controller_ActionController
{

    /**
     * Mapping of Multicolumn configurations to Gridelements layouts
     *
     * @var array
     */
    protected $mapping;

    /**
     * The migration utility.
     *
     * @var Tx_Brainmulticolumntogridelements_Utility_MigrationUtility
     */
    protected $util = null;

    /**
     * Name of the migration utility
     *
     * @var string
     */
    protected $utilName = 'Tx_Brainmulticolumntogridelements_Utility_MigrationUtility';

    /**
     * action prepare
     *
     * @param array $mcToGeMapping Into which Gridelements layouts should
     * the multicolumn layouts be turned?
     * @return void
     */
    public function confirmAction($mcToGeMapping)
    {
        $this->util = $this->objectManager->create($this->utilName);
        /* @var $this->util Tx_Brainmulticolumntogridelements_Utility_MigrationUtility */
        $vars = $this->util->getData($mcToGeMapping);

        $standardLayoutNumber = $vars['standardLayoutNumber'];

        $availability = $vars['availability'];
        if (in_array('hidden', $availability)
            || in_array('unavailable', $availability)) {
            $this->view->assign('availability', $availability);
        }

        $this->view->assign('ffConfig', $vars['ffConfig']);
        $this->view->assign('results', $vars['results']);
        $this->view->assign('mcToGeMapping', $mcToGeMapping);
        $this->view->assign('standardLayoutNumber', $standardLayoutNumber);
    }

    /**
     * action execute
     *
     * @return void
     */
    public function executeAction()
    {
        $this->util = $this->objectManager->create($this->utilName);
        /* @var $this->util Tx_Brainmulticolumntogridelements_Utility_MigrationUtility */
        $vars = $this->util->getData();

        // Change button was clicked
        $reqArgs = $this->request->getArguments();
        if (array_key_exists('change', $reqArgs)) {
            $args = array('restoreVals' => array(
                'mcToGeMapping' => $vars['mcToGeMapping'],
                'standardLayoutNumber' => $vars['standardLayoutNumber'],
            ));
            $this->redirect('prepare', null, null, $args);
        }

        $results = $this->util->execMigration($vars['mcToGeMapping']);
        $error = null;
        if (isset($results['error'])) {
            $error = $results['error'];
            unset($results['error']);
        }
        $this->view->assign('error', $error);
        $this->view->assign('results', $results);

        unset($this->util);
    }

    /**
     * action prepare
     *
     * @param array $restoreVals If comming here from executeAction
     * @dontvalidate $restoreVals
     * @return void
     */
    public function prepareAction($restoreVals = array())
    {
        $this->util = $this->objectManager->create($this->utilName);
        /* @var $this->util Tx_Brainmulticolumntogridelements_Utility_MigrationUtility */
        $vars = $this->util->getData();

        $restored = false;
        // We DO return here by clicking the Change-button
        if (!empty($restoreVals)) {
            $restored = true;
        }

        $ffConfig = $vars['ffConfig'];
        $mcToGeMapping = $restoreVals['mcToGeMapping'];
        $standardLayoutNumber = $restoreVals['standardLayoutNumber'];
        $results = $vars['results'];

        $this->view->assign('ffConfig', $ffConfig);
        $this->view->assign('mcToGeMapping', $mcToGeMapping);
        $this->view->assign('restored', $restored);
        $this->view->assign('results', $results);
        $this->view->assign('standardLayoutNumber', $standardLayoutNumber);
    }
}
?>
