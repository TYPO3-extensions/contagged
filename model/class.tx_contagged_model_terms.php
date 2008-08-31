<?php
/***************************************************************
	*  Copyright notice
	*
	*  (c) 2007 Jochen Rau <j.rau@web.de>
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
	***************************************************************/
require_once (t3lib_extMgm::extPath('contagged') . 'model/class.tx_contagged_model_mapper.php');

/**
 * The model of contagged.
 *
 * @author	Jochen Rau <j.rau@web.de>
 * @package	TYPO3
 * @subpackage	tx_contagged_model_terms
 */
class tx_contagged_model_terms {
	var $conf; // the TypoScript configuration array
	var $cObj;
	var $controller;
	var $tablesArray; // array of all tables in the database

	function tx_contagged_model_terms($controller) {
		$this->controller = $controller;
		$this->conf = $controller->conf;
		$this->cObj = $controller->cObj;

		$mapperClassName = t3lib_div::makeInstanceClassName('tx_contagged_model_mapper');
		$this->mapper = new $mapperClassName($this->controller);

		// build an array of tables in the database
		$tablesArray = array();
		$tablesResult = mysql_list_tables(TYPO3_db);
		if (!mysql_error()) {
			while($table = mysql_fetch_assoc($tablesResult)) {
				$this->tablesArray[] = current($table);
			}
		}
	}

	/**
	 * [Describe function...]
	 *
	 * @return	[type]		...
	 */
	function getTermsArray() {
		$typesArray = $this->conf['types.'];
		$termsArray = array();
		$dataSourceArray = array();
		foreach ($typesArray as $type=>$typeConfigArray) {
			$storagePidsArray = $this->mapper->getStoragePidsArray($typeConfigArray);
			$dataSource = $typeConfigArray['dataSource'] ? $typeConfigArray['dataSource'] : 'default';
			foreach ($storagePidsArray as $pid) {
				// if there is an entry for the data source: check for duplicates before adding the pid
				// otherwise: create a new entry and add the pid
				if ($dataSourceArray[$dataSource]) {
					if ( !in_array($pid,$dataSourceArray[$dataSource]) ) {
						$dataSourceArray[$dataSource][] = $pid;
					}
				} else {
					$dataSourceArray[$dataSource][] = $pid;
				}
			}
		}
		// get an array of all data rows in the configured tables
		foreach ($dataSourceArray as $dataSource=>$storagePidsArray ) {
			$termsArray = array_merge($termsArray,$this->getTerms($dataSource,$storagePidsArray));
		}

		uasort($termsArray,array($this,'sortByTermAscending'));

		return $termsArray;
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$termArrayA: ...
	 * @param	[type]		$termArrayB: ...
	 * @return	[type]		...
	 */
	function sortByTermAscending($termArrayA,$termArrayB) {
		// TODO: improve sorting (UTF8, configurable, localized->hook)
		// strcasecmp() internally converts the two strings it is comparing to lowercase, based on the server locale settings. As such, it
		// cannot be relied upon to be able to convert appropriate multibyte characters in UTF-8 to lowercase and, depending on the actual
		// locale, may have internally corrupted the UTF-8 strings it is comparing, having falsely matched byte sequences. It won’t actually
		// damage the UTF-8 string but the result of the comparison cannot be trusted. (Ref. http://www.phpwact.org/php/i18n/utf-8)
		// TODO remove; just a hack
		$sortFieldA = $this->getSortField($termArrayA);
		$sortFieldB = $this->getSortField($termArrayB);
		$termsArray = array($termArrayA[$sortFieldA],$termArrayB[$sortFieldB]);
		// $GLOBALS['TSFE']->csConvObj->convArray($termsArray,'utf-8','iso-8859-1');
		$termsArrayBefore = $termsArray;
		sort($termsArray,SORT_LOCALE_STRING);
		$termsArrayAfterwards = $termsArray;
		// debug($termsArrayBefore,'before');debug($termsArrayAfterwards,'after');
		if ($termsArrayBefore[0]==$termsArrayAfterwards[0]) {
			$result = -1;
		} else {
			$result = 1;
		}

		return $result;
	}

	/**
	 * Build an array of the entries in the tables
	 *
	 * @param	[type]		$dataSource: ...
	 * @param	[type]		$storagePids: ...
	 * @return	An		array with the terms an their configuration
	 */
	function getTerms($dataSource,$storagePidsArray=NULL) {
		$dataArray = array();
		$terms = array();
		$storagePidsList = implode(',',$storagePidsArray);
		$dataSourceConfigArray = $this->conf['dataSources.'][$dataSource.'.'];
		$sourceName = $dataSourceConfigArray['sourceName'];

		// check if the table exists in the database
		if (t3lib_div::inArray($this->tablesArray,$sourceName) ) {
			// Build WHERE-clause
			$whereClause = '1=1';
			$whereClause .= $storagePidsList ? ' AND pid IN ('.$storagePidsList.')' : '';
			$whereClause .= $dataSourceConfigArray['hasSysLanguageUid'] ? ' AND (sys_language_uid='.intval($GLOBALS['TSFE']->sys_language_uid) . ' OR sys_language_uid=-1)' : '';
			$whereClause .= tslib_cObj::enableFields($sourceName);

			// execute SQL-query
			$result = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
				'*', // SELECT ...
				$sourceName, // FROM ...
				$whereClause // WHERE ..
				);

			// map the fields
			$dataArray = $this->mapper->getDataArray($result,$dataSource);
		}
		
		// TODO piVars as a data source

		return $dataArray;
	}
	
	function getSortField($termArray) {
		if ($this->conf['types.'][$termArray['term_type'].'.']['sortField']) {
			$sortField = $this->conf['types.'][$termArray['term_type'].'.']['sortField'];
		} elseif ($this->conf['sortField']) {
			$sortField = $this->conf['sortField'];
		} else {
			$sortField = 'term';
		}
		return $sortField;
	}

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/contagged/model/class.tx_contagged_model_terms.php']) {
	include_once ($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/contagged/model/class.tx_contagged_model_terms.php']);
}
?>